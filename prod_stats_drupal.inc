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
    //$limit = (int) variable_get('prod_stats_batch_limit',100);

    // TODO: re-handle the limit

    $queue = Queue::getInstance();
    $queue->run();

}

/**
 * Json Db reports for drush
 */
function prod_drupal_db_report() {
  module_load_include('inc', 'prod', 'prod.admin');

  drush_print("# Databases", 0, NULL, TRUE);
  $db_reader = \Drupal\Prod\Db\Reader::getInstance('db_dbs');
  $data = $db_reader->getDatabasesData();
  foreach($data as $k => $records) {
      foreach ($records as $key2 => $db) {
          $line = "Database " . $db['db'] . " Tables: " . $db['nb_tables_h'];
          $line .= ' Rows: '. $db['rows_h'];
          drush_print($line, 2, NULL, TRUE);
      }
  }

  drush_print("# Top 50 Tables (size)", 0, NULL, TRUE);
  $db_reader = \Drupal\Prod\Db\Reader::getInstance('db_top');
  $rows_per_page= 50;
  $data = $db_reader->getTopTablesData($rows_per_page, 1, 'size', 'all');
  foreach($data as $k => $records) {
      foreach ($records as $key2 => $table) {
          $line = 'Full Size:'. $table['full_size_h'];
          $line .= ' [Data Size:'. $table['size_h'];
          $line .= ' Idx Size:'. $table['idx_size_h'] . "]";
          $line .= "\t" . $table['rows_h'] . ' rows (avg '. $table['avg_row_size_h'] . "/row)";
          $line .= "\t" . $table['db'] . "." . $table['table'];
          drush_print($line, 2, NULL, TRUE);
      }
  }
}