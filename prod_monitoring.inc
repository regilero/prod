<?php

use Drupal\Prod\ProdRegistry;
use Drupal\Prod\Monitoring\Cacti;
use Drupal\Prod\Monitoring\Nagios;
use Drupal\Prod\Log\LogFactory;
use Drupal\Prod\Error\MonitoringException;

define('PROD_MONITORING_OK', 0);
define('PROD_MONITORING_WARNING', 1);
define('PROD_MONITORING_CRITICAL', 2);
define('PROD_MONITORING_UNKNOWN', 3);
define('PROD_MONITORING_PENDING', 4);

/**
 * Nagios syntax output manager. This is an exit function!
 *
 * @param int $status
 * @param string $message
 * @param string $perfdata
 */
function prod_monitor_exit($status, $message='', $perfdata='') {
  $msg = $message;
  if (!empty($perfdata)) {
    $msg .= ' | ' . $perfdata;
  }
  // Lying to drush, we will exit abruptly witjout telling drush...
  // drush_shutdown should not add an error line because of this premature exit
  drush_set_context("DRUSH_EXECUTION_COMPLETED", TRUE);
  drush_set_context("DRUSH_USER_ABORT", FALSE);
  switch ($status) {
      case PROD_MONITORING_OK:
          drush_print('DRUPAL OK - ' . $msg , 0, NULL, TRUE);
          // this shoudl work, but not on all drush versions
          drush_set_context('DRUSH_EXIT_CODE',PROD_MONITORING_OK);
          exit(PROD_MONITORING_OK);
        break;
      case PROD_MONITORING_WARNING:
          drush_print('DRUPAL WARNING - ' . $msg , 0, NULL, TRUE);
          drush_set_context('DRUSH_EXIT_CODE',PROD_MONITORING_WARNING);
          exit(PROD_MONITORING_WARNING);
        break;
      case PROD_MONITORING_CRITICAL:
          drush_print('DRUPAL CRITICAL - ' . $msg , 0, NULL, TRUE);
          drush_set_context('DRUSH_EXIT_CODE',PROD_MONITORING_CRITICAL);
          exit(PROD_MONITORING_CRITICAL);
        break;
      case PROD_MONITORING_PENDING:
          drush_print('DRUPAL PENDING - ' . $msg , 0, NULL, TRUE);
          drush_set_context('DRUSH_EXIT_CODE',PROD_MONITORING_PENDING);
          exit(PROD_MONITORING_PENDING);
        break;
      default:
          drush_print('DRUPAL UNKNOWN - ' . $msg , 0, NULL, TRUE);
          drush_set_context('DRUSH_EXIT_CODE',PROD_MONITORING_UNKNOWN);
          exit(PROD_MONITORING_UNKNOWN);
        break;
  }
}


function prod_monitor_error_handler($errno, $errstr) {
  if (E_USER_NOTICE !== $errno) {
    prod_monitor_exit(PROD_MONITORING_CRITICAL, 'PHP ERROR: code:' .$errno . ' message:' .$errstr);
  }
  // block php error handler
  return true;
}

function prod_monitor_remove_drush_error_handler() {
  $drush_handler = set_error_handler('prod_monitor_error_handler');
}

/**
 * Main entry point for the Monitor stats report generation (cacti-like export)
 */
function prod_monitor_stats() {


  $registry = ProdRegistry::initInstance();

  prod_monitor_remove_drush_error_handler();

  try {

    $monitor = Cacti::getInstance();
    // this should be outputing lines via drush_print
    $monitor->render();
    // final line, we use Nagios syntax but this is not necessary
    prod_monitor_exit(PROD_MONITORING_OK, 'end of monitoring report');

  } catch (Exception $e) {
    prod_monitor_exit(PROD_MONITORING_UNKNOWN, 'Exception occured: ' . $e->getMessage());
  }
}


/**
 * Main entry point for the Monitor output generation (nagios-like)
 */
function prod_monitor() {

  $registry = ProdRegistry::initInstance();

  prod_monitor_remove_drush_error_handler();

  try {

    $monitor = Nagios::getInstance();

    $output = '';
    $perf = '';
    $status = $monitor->check($output, $perf);
    prod_monitor_exit($status, $output, $perf);

  } catch (Exception $e) {

    if ($e instanceof MonitoringException) {
      prod_monitor_exit(PROD_MONITORING_CRITICAL, 'Monitoring Exception occured: ' . $e->getMessage());
    } else {
      prod_monitor_exit(PROD_MONITORING_UNKNOWN, 'Exception occured: ' . $e->getMessage());
    }
  }
}


