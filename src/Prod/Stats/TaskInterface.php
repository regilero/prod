<?php

namespace Drupal\Prod\Stats;


/**
 * Statistic Collector's Task Interface
 */
Interface TaskInterface
{

    /**
     * Singleton implementation
     *
     * @return \Drupal\Prod\Stats\StatInterface
     */
    public static function getInstance();
    
    /**
     * Constructor
     * @return \Drupal\Prod\Stats\TaskInterface
     */
    public function __construct();

    /**
     * Get the Stat Task Unique Id
     *
     * @return int the stat task id
     *
     */
    public function getId();

    /**
     * 
     * @param int $id
     */
    public function setId($id);
    
    /**
     * Get the stat run scheduling timestamp
     *
     * @return int the UNIX timestamp
     * 
     */
    public function getScheduling();
    
    /**
     * 
     * @param int $timestamp
     * @return \Drupal\Prod\Stats\TaskInterface
     */
    public function setScheduling($timestamp);

    /**
     * Setter for enabled boolean
     * 
     * @param boolean $bool
     * @return \Drupal\Prod\Stats\TaskInterface
     */
    public function flagEnabled($bool);
    
    /**
     * Setter for is_internal boolean. This should be true only for objects
     * Using the PordObserver and TaskInterface patterns, non Internal objects
     * are instead using Drupal hooks to get called.
     *  
     * @param boolean $bool
     * @return \Drupal\Prod\Stats\TaskInterface
     */
    public function flagInternal($bool);
    
    /**
     * 
     * @return boolean
     */
    public function isEnabled();
    
    /**
     * 
     * @return boolean
     */
    public function isInternal();
    
    /**
     * 
     * @return string
     */
    public function getTaskModule();
    
    /**
     * 
     * @return string
     */
    public function getTaskName();
    
    public function setTaskModule($name);
    
    /**
     * 
     * @param string $name
     * @return \Drupal\Prod\Stats\TaskInterface
     */
    public function setTaskName($name);
    
    /**
     * Is this record a new record -- no id yet -- ?
     * @return boolean
     */
    public function isNew();
    

    /**
     * Internally set the next scheduling time
     */
    public function scheduleNextRun();
    
    /**
     * Run the task, this is our main goal in fact!
     */
    public function run();
    
}
