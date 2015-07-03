<?php


use Drupal\Prod\ProdRegistry;
use Drupal\Prod\Stats\Queue;
use Drupal\Prod\Stats\Rrd\Manager;
use Drupal\Prod\Log\LogFactory;
use Drupal\Prod\Error\StatTaskException;

/**
 * Main entry for Internal objects indexation
 */
function prod_drupal_stats_indexer() {

    $logger = LogFactory::get();

    $registry = ProdRegistry::initInstance();
    
    // number of stats to handle per run
    // Not that we will dequeue a number n of tasks in the task queue performing
    // a number m of stats. We'll stop when the number of stats will be reached.
    // But a task could perform a big number of stats and this limit is not
    // absolute, a single task could bypass the limit.
    //$limit = variable_get('prod_stats_batch_limit',100);

    $queue = Queue::getInstance();
    $queue->run();
    /*
    $done = 0;
    $tasks = array();
    
    while ( $done < $limit ) {
        
        try {

            // get next task to run
            $task = $queue->popTask();
        
        } catch ( EmptyStatTaskQueueException $e ) {
            // nothing less to do
            break;
        }
        
        // Store tasks for RRD usage
        $tasks[] = $task;
        
        try {
            
            // task will return number of stats elements managed
            $done += $task->run();
            
        } catch (StatTaskException $e) {
            
            // TODO: manage internal tracking of errors, as a stat
            $logger->log('Error while running Stat task :task : :msg ' ,array(
                ':task' => $task->getLabel(),
                ':msg' => $e->getmessage()),
                WATCHDOG_WARNING);
        
        }
        
        // Re-schedule next execution of the task
        $task->schedule();
        
    }
    
    // now that we've reached the max number of stats, or all the tasks
    // let's register the tasks for next run.
    // we cannot do that in previous loop to avoid running the same task several
    // time.
    foreach ($tasks as $task) {
        
        // Store the task in the queue
        $queue->pushTask( $task );
        
    }
*/
    // RRD storage, now that all tables are at least recorded once
/* TODO:
      if (variable_get('prod_stats_rrd_enabled', FALSE)) {

        $rrd_manager = new Manager();
        $rrd_manager
        ->loadMultipleProviders($tasks)
        ->manageRotations();

    }
*/
}

