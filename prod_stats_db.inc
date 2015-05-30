<?php

use Drupal\Prod\Db\AnalyzerFactory;
use Drupal\Prod\Stats\Rrd\Manager;
use Drupal\Prod\Log\LogFactory;

function prod_db_stats_indexer() {
  global $databases;
  $log = LogFactory::get();
  // TODO: transaction(s)

  // number of tables to handle per run
  $limit = variable_get('prod_stats_batch_limit',100);

  foreach($databases as $identifier => $databases_arr) {

    $dbAnalyser = AnalyzerFactory::get($databases_arr,$identifier);

    if ($limit > 0) {
      // still have some tables to track on this run

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
        $rrd_manager->loadMultipleProviders($tables)
          ->manageRotations();

    }

    // Try to guess some default user group values for records
    // having none
    $dbAnalyser->setDefaultUserGroup();

  } // end database loop

}

