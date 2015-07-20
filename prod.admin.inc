<?php


use Drupal\Prod\ProdRegistry;
use Drupal\Prod\Stats\Queue;
use Drupal\Prod\Stats\Rrd\Manager;
use Drupal\Prod\Log\LogFactory;
use Drupal\Prod\Error\StatTaskException;

/**
 * Form builder; Configure Prod module.
 *
 * @ingroup forms
 * @see system_settings_form()
 */
function prod_admin_config_rrd_form() {
    
    $form['global'] = array(
            '#type' => 'fieldset',
            '#title' => t('Connexion settings'),
    );
    $form['global']['prod_rrd_enabled'] = array(
            '#type' => 'checkbox',
            '#title' => t('Enable RRD storage'),
            '#default_value' => variable_get('prod_rrd_enabled', FALSE),
            '#description' => t('TODO'),
    );
    return system_settings_form($form);
}


function prod_add_report_pages_js() {
    drupal_add_library('system', 'drupal.collapse');
    drupal_add_js("https://cdnjs.cloudflare.com/ajax/libs/d3/3.5.5/d3.min.js");
}

/**
 * Main Ajax entry point for extracting graphs in json format
 * 
 * @param unknown $dataid
 */
function prod_admin_ajax_json_result( $data_id ) {
    $data = '';
    
    switch ($data_id) {
    
        case 'db_top':
            $db_reader = \Drupal\Prod\Db\Reader::getInstance();
            $data = $db_reader->getTopTables(20, 'fullsize', 'all', 'json');
            break;

        default:
            throw new Exception('GFY' . $data_id);
    }
    
    drupal_json_output($data);
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

  // Top tables
  $db_reader = \Drupal\Prod\Db\Reader::getInstance();
  $data = $db_reader->getTopTables();

  $results['db-tables-top'] = array(
      'title' => t('Top Tables'),
      'table' => array(
        '#header'    => $data['meta']['headers'],
        '#rows'      => $data['rows'],
        '#caption'   => t('Raw data table'),
        '#colgroups' => array(),
        '#theme'     => 'table',
        '#empty'     => t('No statistic data available. Check filters and check that associated trackers are enabled.'),
      )
  );
  
  /*
  $results['db-tables-list'] = array(
    'title' => t('Databases Tables'),
    'table' => array(
      '#header' => array('foo2','bar2'),
      '#rows' => array(array('foo2','bar2')),
      '#caption'=>'',
      '#colgroups'=>array(),
      '#theme' => 'table',
      '#empty' => t('No statistic data available. Check filters and check that associated trackers are enabled.'),
    )
  );
  */

  return theme('prod_admin_display_results', array('results' => $results));
}