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
    
    // TODO: re-handle the limit
    
    $queue = Queue::getInstance();
    $queue->run();

}

