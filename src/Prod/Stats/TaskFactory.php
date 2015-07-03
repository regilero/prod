<?php

namespace Drupal\Prod\Stats;

use Drupal\Prod\ProdObject;

/**
 * Statistic Collector's Task Factory
 */
Class TaskFactory extends ProdObject
{

    protected static $tasks = array();
    
    /**
     * Builds the right TaskInterface object for you.
     * 
     * @param string $task_module
     * @param string $tak_name
     * @param bool $is_internal
     * @param int $id, if you have it already it's better to give it
     * @return \Drupal\Prod\Stats\TaskInterface
     */
    static public function get($task_module, $task_name, $is_internal, $id=NULL)
    {
        if (!array_key_exists($task_module, self::$tasks)) {
            
            self::$tasks[$task_module] = array();
            
        } else {
            
            if (array_key_exists($task_name, self::$tasks)) {
                
                // Already have it!
                return self::$tasks[$task_module][$task_name];
                
            }
        }
        
        // we do not have it yet...
        if (! $is_internal) {
            
            // OK, so in fact this is not really a Singleton object that you
            // need. You will need an instance of Task with custom values
            $task = new Task();
            $task->setTaskModule($task_module);
            $task->setTaskName($task_name);
            $task->flagInternal(FALSE);
            
        } else {
            
            // Here any TaskInterface implementation should be a Singleton
            // coming for a class derivated from Task().
            $class = $task_module;
            $task = call_user_func(array($class,'getInstance'));
            $task->setTaskModule($task_module);
            $task->setTaskName($task_name);
            $task->flagInternal(TRUE);
            
        }
        
        // Try to get the task Id if it exists
        if (!is_null($id)) {
            
            $task->setid($id);
            
        }
        
        self::$tasks[$task_module][$task_name] = $task;
        return self::$tasks[$task_module][$task_name];
    }
}
