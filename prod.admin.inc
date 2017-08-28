<?php


use Drupal\Prod\ProdRegistry;
use Drupal\Prod\Stats\Queue;
use Drupal\Prod\Stats\Rrd\Manager;
use Drupal\Prod\Log\LogFactory;
use Drupal\Prod\Error\ProdException;

/**
 * Form builder; Configure Prod module.
 *
 * @ingroup forms
 * @see system_settings_form()
 */
function prod_admin_config_form() {

    $form['global'] = array(
        '#type' => 'fieldset',
        '#title' => t('Prod Module settings'),
    );

    $form['global']['prod_stats_enabled'] = array(
        '#type' => 'checkbox',
        '#title' => t('Enable Stats'),
        '#default_value' => variable_get('prod_stats_enabled', TRUE),
        '#description' => t('TODO'),
    );
    $form['global']['prod_log_level'] = array(
        '#type' => 'select',
        '#title' => t('Module internal log level?'),
        '#default_value' => variable_get('prod_log_level', WATCHDOG_NOTICE),
        '#options' => array(
            WATCHDOG_ALERT => 'WATCHDOG_ALERT',
            WATCHDOG_CRITICAL => 'WATCHDOG_CRITICAL',
            WATCHDOG_ERROR => 'WATCHDOG_ERROR',
            WATCHDOG_WARNING => 'WATCHDOG_WARNING',
            WATCHDOG_NOTICE => 'WATCHDOG_NOTICE',
            WATCHDOG_INFO => 'WATCHWATCHDOG_INFO',
            WATCHDOG_DEBUG => 'WATCHDOG_DEBUG',
        ),
        '#description' => t('TODO'),
    );
    return system_settings_form($form);

}

/**
 * Form builder; Configure Prod module.
 *
 * @ingroup forms
 * @see system_settings_form()
 */
function prod_admin_config_collectors_form() {

    $form['global'] = array(
        '#type' => 'fieldset',
        '#title' => t('Collector settings'),
    );
    $form['global']['prod_stats_batch_limit'] = array(
        '#type' => 'textfield',
        '#title' => t('Number of stats to collect per batch'),
        '#default_value' => variable_get('prod_stats_batch_limit', 100),
        '#description' => t('TODO'),
    );
    $form['global']['prod_db_stats_indexer_use_slave'] = array(
        '#type' => 'checkbox',
        '#title' => t('Use slave Db connection for stats if available'),
        '#default_value' => variable_get('prod_db_stats_indexer_use_slave', TRUE),
        '#description' => t('TODO'),
    );
    return system_settings_form($form);
}

/**
 * Form builder; Configure Prod module.
 *
 * @ingroup forms
 * @see system_settings_form()
 */
function prod_admin_config_rrd_form() {

    $form['global'] = array(
        '#type' => 'fieldset',
        '#title' => t('RRD settings'),
    );
    $form['global']['prod_stats_rrd_enabled'] = array(
        '#type' => 'checkbox',
        '#title' => t('Enable RRD storage'),
        '#default_value' => variable_get('prod_stats_rrd_enabled', FALSE),
        '#description' => t('TODO'),
    );
    $form['global']['prod_default_rrd_interval'] = array(
        '#type' => 'textfield',
        '#title' => t('Interval in seconds between RRD points.'),
        '#default_value' => variable_get('prod_default_rrd_interval', 300),
        '#description' => t('TODO'),
    );
    $form['global']['prod_default_rrd_points_per_graph'] = array(
        '#type' => 'textfield',
        '#title' => t('Number of RRD points per graphic'),
        '#default_value' => variable_get('prod_default_rrd_points_per_graph', 300),
        '#description' => t('TODO'),
    );
    $form['global']['prod_default_rrd_points_per_aggregate'] = array(
        '#type' => 'textfield',
        '#title' => t('Number of points per RRD aggregate point'),
        '#default_value' => variable_get('prod_default_rrd_points_per_aggregate', 5),
        '#description' => t('TODO'),
    );
    return system_settings_form($form);
}


function prod_add_report_pages_js() {
    drupal_add_library('system', 'drupal.collapse');
    drupal_add_js("https://cdnjs.cloudflare.com/ajax/libs/d3/3.5.5/d3.js");
    //drupal_add_js("https://cdnjs.cloudflare.com/ajax/libs/d3/3.5.5/d3.min.js");
}

/**
 * Main Ajax entry point for extracting graphs in json format - Definitions
 *
 * @param unknown $dataid
 */
function prod_admin_ajax_json_def( $data_id ) {
    $data = '';
    try {
        switch ($data_id) {

            case 'db_dbs':
                $db_reader = \Drupal\Prod\Db\Reader::getInstance($data_id);
                $def = $db_reader->getDatabasesDefinition();
                $def['graph_url'] = url('admin/reports/prod/ajax/data/', array('alias' => TRUE,));
                break;

            case 'db_top':
                $db_reader = \Drupal\Prod\Db\Reader::getInstance($data_id);
                $def = $db_reader->getTopTablesDefinition();
                $def['graph_url'] = url('admin/reports/prod/ajax/data/', array('alias' => TRUE,));
                break;

            case 'db_top_history':
                $level = (int) $_GET['level'];
                $db_reader = \Drupal\Prod\Db\Reader::getInstance($data_id);
                $def = $db_reader->getTopTablesHistoryDefinition();
                $def['graph_url'] = url('admin/reports/prod/ajax/data/level/' . $level . '/', array('alias' => TRUE,));
                break;

            default:
                throw new ProdException('Unknown graphic data id');
        }

        $data['def'] = $def;
        $data['error'] = FALSE;
        drupal_json_output($data);

    } catch (Exception $e) {
        $data = array(
                'error' => TRUE,
                'error_msg' => $e->getMessage(),
        );
        drupal_json_output($data);
    }
    exit;
}

/**
 * Main Ajax entry point for extracting graphs in json format - Datas
 *
 * @param string $dataid
 */
function prod_admin_ajax_json_result( $data_id ) {

  $data = '';
  try {
    switch ($data_id) {

        case 'db_dbs':
            $db_reader = \Drupal\Prod\Db\Reader::getInstance($data_id);
            $data = $db_reader->getDatabasesData();
            break;

        case 'db_top':
            $db_reader = \Drupal\Prod\Db\Reader::getInstance($data_id);

            $rows_per_page= (int) $_GET['rows'];
            if (empty($rows_per_page)) $rows_per_page = 25;
            $page = (int) $_GET['page'];
            if (empty($page)) $page = 1;
            $sort=$_GET['sort'];
            // TODO filter sort
            $data = $db_reader->getTopTablesData($rows_per_page, $page, $sort, 'all');
            break;

        default:
            throw new ProdException('Unknown graphic data id');
    }

    $data['error'] = FALSE;
    drupal_json_output($data);

  } catch (Exception $e) {
    $data = array(
      'error' => TRUE,
      'error_msg' => $e->getMessage(),
    );
    drupal_json_output($data);
  }
  exit;
}

/**
 * Main Ajax entry point for extracting graphs in json format for RRD graphs
 *
 * @param int $level RRD graph level
 *
 * @param string $dataid
 */
function prod_admin_ajax_json_result_rrd( $level, $data_id ) {

    $data = '';
    try {
        switch ($data_id) {

            case 'db_top_history':
                $db_reader = \Drupal\Prod\Db\Reader::getInstance($data_id);

                $rows_per_page= (int) $_GET['rows'];
                if (empty($rows_per_page)) $rows_per_page = 25;
                $page = (int) $_GET['page'];
                if (empty($page)) $page = 1;
                $sort=$_GET['sort'];
                // TODO filter sort
                $data = $db_reader->getTopTablesHistoryData($rows_per_page, $page, $sort, 'all', $level);
                break;

            default:
                throw new ProdException('Unknown graphic data id');
        }

        $data['error'] = FALSE;
        drupal_json_output($data);

    } catch (Exception $e) {
        $data = array(
                'error' => TRUE,
                'error_msg' => $e->getMessage(),
        );
        drupal_json_output($data);
    }
    exit;
}

function prod_admin_page_db() {

  prod_add_report_pages_js();

  $output = array(
    'content' => array(
      'title' => array(
          '#type' => 'markup',
          '#markup' => t('Prod Database Statistics.'),
          '#prefix' => '<h2>',
          '#suffix' => '</h2>',
      ),
    )
  );

  $results = array();

  // Databases
  $db_reader = \Drupal\Prod\Db\Reader::getInstance('db_dbs');
  $results['db-databases'] = array(
          'title' => t('Databases'),
          'form' => null,
          'graph_id' => $db_reader->getid(),
          'graph_url' => url('admin/reports/prod/ajax/def/', array('alias' => TRUE,)),
  );

  // Top tables --------------------------
  $db_reader = \Drupal\Prod\Db\Reader::getInstance('db_top');

  $results['db-tables-top'] = array(
      'title' => t('Top Tables'),
      //'#caption'   => t('Raw data table'),
      //  '#empty'     => t('No statistic data available. Check filters and check that associated trackers are enabled.'),
      'form' => drupal_get_form('prod_report_top_table_form', 25, 1),
      'graph_id' => $db_reader->getid(),
      'graph_url' => url('admin/reports/prod/ajax/def/', array('alias' => TRUE,)),
  );
  // Feed the Databases list on the form
  $dbs = $db_reader->getAvailableDatabases();
  $results['db-tables-top']['form']['filters']['db']['#options'] = array_merge(
          array(0 => $results['db-tables-top']['form']['filters']['db']['#empty_option']),
          $dbs
  );
  $results['db-tables-top']['form']['filters']['db']['#default_value'] = 0;



  // Top tables History ----------------------

  $db_reader = \Drupal\Prod\Db\Reader::getInstance('db_top_history');
  $results['db-tables-top-history'] = array(
          'title' => t('Top Tables History'),
          //'#caption'   => t('Raw data table'),
          //  '#empty'     => t('No statistic data available. Check filters and check that associated trackers are enabled.'),
          'form' => drupal_get_form('prod_report_top_table_form', 3, 1),
          'graph_id' => $db_reader->getid(),
          'graph_url' => url('admin/reports/prod/ajax/def/', array('alias' => TRUE,)),
          'graph_rrd' => TRUE,
  );
  // Feed the Databases list on the form
  $dbs = $db_reader->getAvailableDatabases();
  $results['db-tables-top-history']['form']['filters']['nbelt']['#options'] = array(
          1 => '1',
          3 => '3',
          5 => '5',
          10 => '10',
          15 => '15',
  );
  $results['db-tables-top-history']['form']['filters']['nbelt']['#default_value'] = 3;
  $results['db-tables-top-history']['form']['filters']['db']['#options'] = array_merge(
          array(0 => $results['db-tables-top-history']['form']['filters']['db']['#empty_option']),
          $dbs
  );
  $results['db-tables-top-history']['form']['filters']['db']['#default_value'] = 0;


  return theme('prod_admin_display_results', array('results' => $results));
}

function prod_report_top_table_form($form, &$form_state, $nb_rows,$page) {
  $form['filters'] = array(
    '#type' => 'fieldset',
    '#title' => t('Filters'),
  );
  $form['filters']['db'] = array(
    '#type' => 'select',
    '#title' => t('Database'),
    '#options' => array(),
    '#empty_option' => '--' . t('All databases') . '--',
  );
  $form['filters']['nbelt'] = array(
    '#type' => 'select',
    '#title' => t('Number of tables'),
    '#options' => array(
       10 => '10',
       25 => '25',
       50 => '50',
       100 => '100'
    ),
    '#default_value' => $nb_rows
  );
  $form['filters']['sort'] = array(
    '#type' => 'select',
    '#title' => t('Sort by'),
    '#options' => array(
       'full_size' => t('Full size'),
       'idx_size' => t('Index Size'),
       'size' => t('Data Size'),
       'nb_rows' => t('Nb rows')
    ),
    '#default_value' => 'full_size'
  );
  $form['page'] = array(
          '#type' => 'hidden',
          '#value' => $page,
  );
  return $form;
}