<?php

namespace Drupal\Prod\Stats;

use Drupal\Prod\ProdObject;
use Drupal\Prod\Stats\TaskInterface;

/**
 * Statistic Collector's Task
 */
class Task extends ProdObject implements TaskInterface
{

    protected $id;
    protected $task_module;
    protected $task_name;
    protected $is_internal;
    protected $is_enable = TRUE;
    protected $timestamp;

    /**
     *
     * @var \Drupal\Prod\Stats\Task object (for Singleton)
     */
    protected static $instance;
    
    /**
     * Singleton implementation
     *
     * @return \Drupal\Prod\Stats\StatInterface
     */
    public static function getInstance()
    {
    
        if (!isset(self::$instance)) {
    
            self::$instance = new Task();
        }
    
        return self::$instance;
    }
    
    /**
     * Constructor
     * @return \Drupal\Prod\Stats\TaskInterface
     */
    public function __construct()
    {
        return $this->initHelpers();
    }

    /**
     * Get the Stat Task Unique Id
     *
     * @return int the stat task id
     *
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * 
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }
    
    /**
     * Get the stat run scheduling timestamp
     *
     * @return int the UNIX timestamp
     * 
     */
    public function getScheduling()
    {
        return $this->timestamp;
    }
    
    /**
     * 
     * @param int $timestamp
     * @return \Drupal\Prod\Stats\TaskInterface
     */
    public function setScheduling($timestamp)
    {
        $this->timestamp = (int) $timestamp;
        return $this;
    }

    /**
     * Setter for enabled boolean
     * 
     * @param boolean $bool
     * @return \Drupal\Prod\Stats\TaskInterface
     */
    public function flagEnabled($bool)
    {
        $this->is_enable = (int) $bool;
        return $this;
    }
    
    /**
     * Setter for is_internal boolean. This should be true only for objects
     * Using the PordObserver and TaskInterface patterns, non Internal objects
     * are instead using Drupal hooks to get called.
     *  
     * @param boolean $bool
     * @return \Drupal\Prod\Stats\TaskInterface
     */
    public function flagInternal($bool)
    {
        $this->is_internal = (int) $bool;
        return $this;
    }
    
    /**
     * 
     * @return boolean
     */
    public function isEnabled()
    {
        return (bool) $this->is_enable;
    }
    
    /**
     * 
     * @return boolean
     */
    public function isInternal()
    {
        return (bool) $this->is_internal;
    }

    /**
     * 
     * @return string
     */
    public function getTaskModule()
    {
        return $this->task_module;
    }
    
    /**
     * 
     * @return string
     */
    public function getTaskName()
    {
        return $this->task_name;
    }
    public function setTaskModule($name)
    {
        $this->task_module = $name;
        return $this;
    }
    
    /**
     * 
     * @param string $name
     * @return \Drupal\Prod\Stats\TaskInterface
     */
    public function setTaskName($name)
    {
        $this->task_name = $name;
        return $this;
    }
    
    /**
     * Is this record a new record -- no id yet -- ?
     * @return boolean
     */
    public function isNew() {
        return (is_null($this->id));
    }
    

    /**
     * Internally set the next scheduling time
     */
    public function scheduleNextRun()
    {
        if (is_null($this->timestamp)) {
            
            // new record, schedule right now
            $this->setScheduling(REQUEST_TIME);
            
        } else {
            $this->setScheduling(
                    REQUEST_TIME
                    + variable_get('prod_default_rrd_interval', 300)
            );
        }
    }
    
    /**
     * Run the task, this is our main goal in fact!
     */
    public function run()
    {
        if ($this->is_enable) {

            if ($this->is_internal) {
                
                $this->logger->log("Internal call on Stat :method.", array(
                        ':method' => $this->task_name
                ), WATCHDOG_DEBUG);
                
                call_user_func(array($this,$this->task_name));
                
            }
            else {
                
                // D7 hook system
                module_invoke(
                    $this->task_module,
                    'prod_stat_task_collect',
                    $this->task_name
                );
                
            }
        } else {
            $this->logger->log("Stat :module :: :name is disabled.", array(
                    ':module' => $task->getTaskModule(),
                    ':name' => $task->getTaskName()
            ), WATCHDOG_DEBUG);
        }
        
    }
    
    /**
     * Feed the internal id from the Queue table, if we can.
     * That is only of this task as run already.
     */
    protected function _loadId()
    {
        if (isset($this->id)) {
            return TRUE;
        }
        
        $query = db_select('prod_stats_provider_queue', 'q');
        $query->fields('q', array(
                'ppq_stat_pid',
          ))
          ->condition('ppq_module', $this->getTaskModule())
          ->condition('ppq_name', $this->getTaskName());
        $results = $query->execute();
        
        foreach( $results as $result) {
            $this->id = $result->ppq_stat_pid;
        }
        
        return (!empty($this->id));
    }
}
