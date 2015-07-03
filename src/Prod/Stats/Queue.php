<?php

namespace Drupal\Prod\Stats;

use Drupal\Prod\Stats\TaskInterface;
use Drupal\Prod\Stats\TaskFactory;
use Drupal\Prod\Error\EmptyStatTaskQueueException;
use Drupal\Prod\Error\StatTaskQueueException;
use Drupal\Prod\ProdObservable;

/**
 * Stat Collectors Queue class
 * 
 * This queue is storing all collectors and is managing the order of stats
 * collection by theses collectors (which are Tasks).
 */
class Queue extends ProdObservable
{

    /**
     * 
     * @var \Drupal\Prod\Stats\Queue object (for Singleton)
     */
    protected static $instance;

    /**
     * Internal queue
     * 
     * @var array
     */
    protected $queue;
    protected $queue_idx;
    
    /**
     * Singleton implementation
     * 
     * @return \Drupal\Prod\Stats\Queue
     */
    public static function getInstance()
    {
        
        if (!isset(self::$instance)) {
            
            self::$instance = new Queue();
        }
        
        return self::$instance;
    }
    
    /**
     * constructor of Queue object
     * 
     * @return \Drupal\Prod\Stats\Queue
     */
    public function __construct()
    {
        $this->queue = array();
        $this->queue_idx = array();
        return $this->initHelpers();
    }
    
    /**
     * Main function
     *
     */
    public function run()
    {

        // get list of all observers
        $this->_loadObservers();

        // load current queue records
        $this->_loadQueue();
        
        // observers should register tasks using $this->queueTask()
        $this->notify(ProdObservable::SIGNAL_STAT_TASK_INFO);
        
        // loop on the queue to run what should be launched
        $this->_runValidPastTasks();
        
        return $this;
    }

    /**
     * Pop the next task that should be done
     * 
     * @throws EmptyStatTaskQueueException
     * @return TaskInterface object
     */
    public function popTask()
    {
        if ( 0 === count($this->queue) ) {
            throw new EmptyStatTaskQueueException();
        }
        $task = array_shift($this->queue);
        
        return $task;
    }

    
    protected function _loadObservers()
    {
        
        // TODO, hooks module_implements fot external objects
    }
    
    protected function _loadQueue()
    {
        $query = db_select('prod_stats_provider_queue', 'q');
        $query->fields('q', array(
            'ppq_stat_pid',
            'ppq_module',
            'ppq_name',
            'ppq_timestamp',
            'ppq_enable',
            'ppq_is_internal',
          ))
          ->orderBy('ppq_timestamp', 'ASC');
        $results = $query->execute();
        
        foreach( $results as $result) {
            $task = TaskFactory::get(
                $result->ppq_module,
                $result->ppq_name,
                $result->ppq_is_internal,
                $result->ppq_stat_pid
            );
            $task->setScheduling($result->ppq_timestamp)
              ->flagEnabled($result->ppq_enable);
            $this->_insertOnQueueWithTimestamp($task);
        }
    }
    

    /**
     * Stack a task on the queue if it is not already present.
     * Note that the task should already know the next scheduling.
     *
     * @param TaskInterface $task
     * 
     * @param bool $force_update used internally by the running queue
     *                           to enforce a save even if the task is already
     *                           in the queue (after re-scheduling)
     */
    public function queueTask(TaskInterface $task, $force_update = FALSE)
    {
        $id = $task->getId();
        
        if ($force_update || !array_key_exists($id, $this->queue_idx)) {

            $this->_insertOnQueueWithTimestamp($task);

        }
    }
    
    protected function _insertOnQueueWithTimestamp(TaskInterface $task)
    {

        $timestamp = $task->getScheduling();
        if (array_key_exists( $timestamp, $this->queue)) {
            
            $this->queue[$timestamp][] = $task;
            
        } else {
            
            $this->queue[$timestamp] = array($task);
            
        }

        // Ensure the queue array is always sorted
        ksort($this->queue, SORT_NUMERIC);
        
        if ( $task->isNew() ) {
            
            $this->logger->log("Stats Task is new, make a queue insert for task :module :name.", array(
                    ':module' => $task->getTaskModule(),
                    ':name' => $task->getTaskName()
            ), WATCHDOG_DEBUG);
            
            // This task has no running time scheduled yet.
            $task->scheduleNextRun();
            
            // Enforce a save of this new task, this will create the task Id
            // by inserting the task in the db queue, the db queue Id will become
            // this task Id.
            $this->_saveTask($task);
            
        }
        
        // also save an index of task present in queue
        $this->queue_idx[$task->getId()] = 1;
    }
    
    protected function _saveTask(TaskInterface $task)
    {
        try {
            db_merge('prod_stats_provider_queue')
              -> key( array(
                    'ppq_module' => $task->getTaskModule(),
                    'ppq_name' => $task->getTaskName(),
              ) )
              -> fields( array(
                    'ppq_timestamp' => $task->getScheduling(),
                    'ppq_enable' => (int) $task->isEnabled(),
                    'ppq_is_internal' => (int) $task->isInternal(),
              ) )
              ->execute();
        } catch (Exception $e) {
            throw new StatTaskQueueException('An error occured while creating or updating a Stat Task queue record.' . $e->getmessage());
        }
            
        // get the record id
        try {
            $id = null;
            $query = db_select('prod_stats_provider_queue', 'q');
            $query->fields('q',array('ppq_stat_pid'))
                ->condition('ppq_module',$task->getTaskModule())
                ->condition('ppq_name',$task->getTaskName());
            $result = $query->execute();
            foreach ($result as $res) {
                $id = $res->ppq_stat_pid;
            }
            
            if (is_null($id)) {
                throw new StatTaskQueueException('Record not found.');
            }
            $task->setId($id);
            return $id;
            
        } catch (Exception $e) {
            throw new StatTaskQueueException('Unable to reload the Stat Task queue record.' . $e->getmessage());
        }
    }
    
    protected function _runValidPastTasks()
    {
        // We add a small amount of seconds to decide what is the PAST
        // PAST is in fact PAST+PRESENT+10sFUTURE
        $current = REQUEST_TIME +10;
        
        // Loop on the queue which is indexed on the timestamps
        foreach ($this->queue as $timestamp => $records) {
            
            if ($current >= $timestamp) {
            
                foreach ($records as $k => $task) {
                
                    if ( $task->isEnabled() ) {
                        
                        // This task should be running now! *******
                        $this->logger->log("Launching a Stat task run for :module :: :name.", array(
                                ':module' => $task->getTaskModule(),
                                ':name' => $task->getTaskName()
                            ), WATCHDOG_INFO);
                        $task->run();
                        
                        // And then we reschedule for next run
                        // Note that we do not remove old record from $this->queue
                        // because the run is done only one time in the lifetime of this object
                        $task->scheduleNextRun();
                        
                        // check that the rescheduling is at least a loop-break valid condition
                        if ( $current >= $task->getScheduling()) {
                            
                            $this->logger->log("Stat Task :module :: :name rescheduling was planified way too short in the future, enforcing timestamp :timestamp.", array(
                                    ':module' => $task->getTaskModule(),
                                    ':name' => $task->getTaskName(),
                                    ':timestamp' => $current +1
                            ), WATCHDOG_WARNING);
                            $task->setScheduling( $current +1 );
                            
                        }
                        
                        $this->queueTask($task, TRUE);
                    }
                    
                }
                
            } else {
                
                // Premature ending, all other timestamper task records are in the future
                // maybe because we've just been running/re-sched it before.
                break;
                
            }
            
        }
        
        
    }
}
