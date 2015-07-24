<?php

namespace Drupal\Prod\Db;

use Drupal\Prod\ProdObserverInterface;
use Drupal\Prod\ProdObservable;
use Drupal\Prod\Db\AnalyzerFactory;
use Drupal\Prod\Stats\Task;
use Drupal\Prod\Stats\TaskInterface;
use Drupal\Prod\Stats\Rrd\Manager;
use Drupal\Prod\Stats\Queue;
use Drupal\Prod\Monitoring\Cacti;

/**
 * Task Handler for Db tools, this is connecting us to the task queue
 */
class TaskHandler extends Task implements TaskInterface, ProdObserverInterface
{

    /**
     *
     * @var \Drupal\Prod\Db\TaskHandler object (for Singleton)
     */
    protected static $instance;

    // Stat provider id. This comes from Task
    protected $id;
    
    // Task informations
    // the module, here
    protected $task_module='Drupal\\Prod\\Db\\TaskHandler';
    // running task collector function
    protected $task_name='dbCollector';
    
    /**
     * Singleton implementation
     *
     * @return \Drupal\Prod\Db\TaskHandler
     */
    public static function getInstance()
    {
    
        if (!isset(self::$instance)) {
    
            self::$instance = new TaskHandler();
        }
    
        return self::$instance;
    }
    
    public function __construct()
    {
        // Initialize helpers (like $this->logger)
        $this->initHelpers();
        return $this;
    }

    /**
     * Ensure all Helpers (log) are loaded into this object
     *
     */
    public function initHelpers()
    {
    
        parent::initHelpers();
    
        $this->logger->log(__METHOD__, NULL, WATCHDOG_DEBUG);
    
        // register in the Queue of StatsProviders
        $prodQueue = Queue::getInstance();
        $prodQueue->attach($this);
    
        // register our callback for Cacti Output
        $cactiObservable = Cacti::getInstance();
        $cactiObservable->attach($this);
    
        return $this;
    }
    
    /**
     * Receive and handle a signal from an Observable object that we registered.
     *
     * @param ProdObservable $sender Sender of the signal (Obervable)
     *
     * @param int $signal one of the signal defined as consts in ProdObservable
     *
     * @param string $info optional information associated with the signal
     */
    public function signal(ProdObservable $sender, $signal, $info=null)
    {
        switch ($signal) {
            case ProdObservable::SIGNAL_MONITOR_SUMMARY:
                $this->_buildMonitorSummaryLines($sender);
                break;
            case ProdObservable::SIGNAL_STAT_TASK_INFO:
                $this->_registerTasksInQueue($sender);
                break;
        }
    
    }
    
    protected function _registerTasksInQueue(Queue $queue)
    {
        // We are just one task, so use simple add-me-to-the-queue mode
        // This will ensure we'll get a call for first and next scheduling
        // and also this will CREATE our Stat provider ID! which is the id
        // in the db queue.
        $this->flagEnabled(TRUE);
        $this->flagInternal(TRUE);
        $queue->queueTask($this);
    }
    
    protected function _buildMonitorSummaryLines(Cacti $sender)
    {
        $this->logger->log(__METHOD__ . 'TODO', NULL, WATCHDOG_DEBUG);
    }
    
    
    /**
     * This is the function called when we need to recompute the stats
     * 
     * Here we loop on each available database for Drupal and try
     * to extract a reasonable number of stats for tables inside.
     * Several run may be necessary to extract all available informations.
     * @warning: use the global $database variable from drupal core
     * to retrieve databases.
     */
    public function dbCollector()
    {
        // This is the Drupal global database record !!
        global $databases;
        
        $this->logger->log(__METHOD__, NULL, WATCHDOG_DEBUG);
        
        // number of tables to handle per run
        $limit = variable_get('prod_stats_batch_limit',50);
        
        foreach($databases as $identifier => $databases_arr) {
        
            $dbAnalyser = AnalyzerFactory::get($databases_arr,$identifier);
        
            if ($limit > 0) {
                
                // still have the right to track some tables on this run
                
                $tables = $dbAnalyser->extractTables($limit);
                
                foreach($tables as $table) {
                    
                    $table->save();
                    $limit --;
                    
                }
            }
            
            // Add a global aggregate record for the database
            // we always do that, no matter about the limits
            
            $dbRecord = $dbAnalyser->ManageDbRecord();
            
            // Add this record on the collection, so that it can be used in rrd
            $tables[] = $dbRecord;
            
            // RRD storage, now that all tables are at least recorded once
            if (variable_get('prod_stats_rrd_enabled', FALSE)) {
                
                $rrd_manager = new Manager();
                $rrd_manager
                  ->loadMultipleProviders($this->getId(), $tables)
                  ->manageRotations();
                
            }
            
            // Try to guess some default user group values for records
            // having none
            $dbAnalyser->setDefaultUserGroup();
        
        } // end database loop
    }
    
}
