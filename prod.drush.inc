<?php

/**
 * DRUSH SUPPORT
 */

/**
 * Implementation of hook_drush_command().
 */
function prod_drush_command() {
  $items = array();

  $items['prod-fs-index'] = array(
    'description' => dt("prod stats: index a small part of this website filesystem"),
    'arguments' => array(),
    'callback' => 'drush_prod_filesystem_watcher_indexer',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL, // Full bootstrap.
    'aliases' => array('profsi'),
  );
  $items['prod-fs-cleanup'] = array(
    'description' => dt("prod stats: cleanup of the filsystem watcher tables"),
    'arguments' => array(),
    'callback' => 'drush_prod_filesystem_watcher_cleaner',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL, // Full bootstrap.
    'aliases' => array('profsc'),
  );
  $items['prod-drupal-index'] = array(
    'description' => dt("prod stats: Collect Drupal objects informations"),
    'arguments' => array(),
    'callback' => 'drush_prod_drupal_stats_indexer',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL, // Full bootstrap.
    'aliases' => array('prodri'),
  );
  $items['prod-report'] = array(
    'description' => dt("prod stats: generate report and send it"),
    'arguments' => array(),
    'callback' => 'drush_prod_report',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL, // Full bootstrap.
    'aliases' => array('prodri'),
  );
  $items['prod-monitor-check'] = array(
    'description' => dt("Nagios checker"),
    'arguments' => array(),
    'callback' => 'drush_prod_monitor',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL, // Full bootstrap.
    'aliases' => array('prodmon'),
  );
  $items['prod-monitor-stats'] = array(
    'description' => dt("prod stats: monitoring output with several stats values line by line, for cacti-like collectors"),
    'arguments' => array(),
    'callback' => 'drush_prod_monitor_stats',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL, // Full bootstrap.
    'aliases' => array('prodmonstat'),
  );
  return $items;
}

