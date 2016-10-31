<?php

namespace Drupal\Prod\Stats\Drupal;

use Drupal\Prod\Stats\StatsProviderInterface;
use Drupal\Prod\ProdObserverInterface;
use Drupal\Prod\ProdObservable;
use Drupal\Prod\ProdObject;
use Drupal\Prod\Monitoring\Cacti;
use Drupal\Prod\Stats\TaskInterface;
use Drupal\Prod\Stats\Drupal\DrupalTask;
use Drupal\Prod\Stats\Queue;
use Drupal\Prod\Stats\Stat;
use Drupal\Prod\Error\StatTaskException;
use Drupal\Prod\Error\DevelopperException;

/**
 */
class Bootstrap extends DrupalTask implements TaskInterface, StatsProviderInterface, ProdObserverInterface
{

    /**
     *
     * @var \Drupal\Prod\Stats\Bootstrap object (for Singleton)
     */
    protected static $instance;

    // Stat provider id. This comes from Task
    protected $id;

    protected $task_module='Drupal\\Prod\\Stats\\Drupal\\Bootstrap';
    // running task collector function
    protected $task_name='collect';

    protected $known_times = array(
            'bootstrap_conf' => array('value' => 0, 'timestamp' => 0),
            'bootstrap_page_cache' => array('value' => 0, 'timestamp' => 0),
            'bootstrap_page_cache_abs' => array('value' => 0, 'timestamp' => 0),
            'bootstrap_db' => array('value' => 0, 'timestamp' => 0),
            'bootstrap_db_abs' => array('value' => 0, 'timestamp' => 0),
            'bootstrap_query' => array('value' => 0, 'timestamp' => 0),
            'bootstrap_query_abs' => array('value' => 0, 'timestamp' => 0),
            'bootstrap_variables' =>array('value' => 0, 'timestamp' => 0),
            'bootstrap_variables_abs' => array('value' => 0, 'timestamp' => 0),
            'bootstrap_session' => array('value' => 0, 'timestamp' => 0),
            'bootstrap_session_abs' => array('value' => 0, 'timestamp' => 0),
            'bootstrap_page_header' => array('value' => 0, 'timestamp' => 0),
            'bootstrap_page_header_abs' => array('value' => 0, 'timestamp' => 0),
            'bootstrap_language' => array('value' => 0, 'timestamp' => 0),
            'bootstrap_language_abs' => array('value' => 0, 'timestamp' => 0),
            'bootstrap_full' => array('value' => 0, 'timestamp' => 0),
            'bootstrap_full_abs' => array('value' => 0, 'timestamp' => 0),
    );

    /**
     * Singleton implementation
     *
     * @return \Drupal\Prod\Stats\Bootstrap
     */
    public static function getInstance()
    {

        if (!isset(self::$instance)) {

            self::$instance = new Bootstrap();
        }

        return self::$instance;
    }

    public function __construct()
    {
        return $this->initHelpers();
    }

    /**
     * Ensure all Helpers (log) are loaded into this object
     *
     */
    public function initHelpers()
    {

        parent::initHelpers();

        $this->logger->log(__METHOD__, NULL, WATCHDOG_DEBUG);

        // register in the Queue of StatsProviders
        $prodQueue = Queue::getInstance();
        $prodQueue->attach($this);

        // register our callback for Cacti Output
        $cactiObservable = Cacti::getInstance();
        $cactiObservable->attach($this);

        return $this;
    }


    /**
     * This is the function called when we need to recompute the stats
     */
    public function collect()
    {

        $this->logger->log(__METHOD__, NULL, WATCHDOG_DEBUG);

        // exec external bootstraper.php file (you need php cli and exec rights on this file)

        $path = DRUPAL_ROOT .'/'. drupal_get_path('module', 'prod') . '/bootstrapper.php';

        $this->logger->log('External Bootstrapper file path is :path',
                array(':path' => $path), WATCHDOG_DEBUG);

        $out = array();
        // Ensure executions rights (scripts may remove theses rights on a regular basis)
        drupal_chmod( $path , '0775' );
        // EXECUTE External command!
        if (exec( $path , $out)) {

            $first = TRUE;
            foreach ($out as $line) {

                $this->logger->log("Exec output line : " . $line, NULL, WATCHDOG_DEBUG);

                if ($first) {
                     if ( 'bootstrapper success run' !== $line ) {
                        $this->logger->log("Bootstrapper time monitor script failure, first line of output is: :line",
                             array(':line' => $line), WATCHDOG_WARNING);
                        break;
                     }
                     $first =FALSE;
                } else {
                    $line_split = explode('=',$line);
                    if ( array_key_exists($line_split[0], $this->known_times)) {

                        // Note: no * 1000 because value is already in milliseconds
                        // a bootstrap_full_abs=127 means 0.127s
                        $this->known_times[$line_split[0]]['value'] = ((int) $line_split[1]);
                        $this->known_times[$line_split[0]]['timestamp'] = REQUEST_TIME;

                    }
                }
            } // end result loop
        }

        $this->save();

        $this->manageRRD();

    }

    /**
     * Get the stats provider id
     *
     * @return int identifier of the stat provider, usually the first part of the Stat id
     */
    public function getStatsProviderId()
    {
        if (!isset($this->id)) {
            throw new DevelopperException('Cannot extract stat provider Id, maybe save this object before?');
        }
        return $this->id;
    }

    /**
     * Save (upsert) the stats records in prod_drupal_stats table.
     *
     * @return StatsProviderInterface
     *
     * @throws Drupal\Prod\Error\StatTaskException
     */
    public function save()
    {
        $this->logger->log('Saving StatProvider records for Bootstrap Times Stats ',NULL, WATCHDOG_DEBUG);

        // Upsert the record
        try {

            foreach ($this->known_times as $bootstrap => $time_record) {
                db_merge('prod_drupal_stats')
                  -> key( array(
                      'ptq_stat_tid' => $this->getId(),
                      'pds_name' => $bootstrap,
                  ) )
                  -> fields( array(
                      'pds_value' => $time_record['value'],
                      'pds_is_1024' => 0,
                      'pds_timestamp' => $time_record['timestamp'],
                      'pds_enable' => 1,
                  ) )
                  ->execute();
            }
        } catch (Exception $e) {
            throw new StatTaskException(__METHOD__ . ": Unable to save the Task Stat record. " . $e->getMessage());
        }

        return $this;

    }

    protected function _loadStats()
    {

        if (!isset($this->id)) {
            throw new DevelopperException('Cannot create/load stats elements before having a real Task id, maybe save this object before?');
        }

        // get the records

        try {
            $query = db_select('prod_drupal_stats', 's');
            $query->fields('s')
              ->condition('ptq_stat_tid',$this->getId());
            $result = $query->execute();

            foreach ($result as $res) {

                if (array_key_exists($res->pds_name, $this->known_times)) {

                    $this->known_times[$res->pds_name]['value'] = $res->pds_value;
                    $this->known_times[$res->pds_name]['timestamp'] = $res->pds_timestamp;

                }
            }

        } catch (Exception $e) {
            throw new DbAnalyzerException(__METHOD__ . ": Unable to reload the bootstrap times stats records. " . $e->getMessage());
        }
    }

    /**
     * Get the list of stats provided by this provider
     *
     * @return array List of StatsInterface, the array key
     *               is the stat_col.
     */
    public function getStatsList()
    {

        $this->_loadStats();

        $res = array();

        foreach( $this->known_times as $stat_key => $stat_record) {

            $stat = new Stat(
                $this->getId(),
                $stat_key,
                $stat_record['value'],
                $stat_record['timestamp'],
                'Total number of published Nodes'
            );

            $res[$stat_key] = $stat;

        }

        return $res;
    }

    /**
     * Get the default RRD settings ('interval','points_per_graph','points_per_aggregate')
     * for this provider.
     *
     * @param string $stat_col Stat column identifier in this provider.
     *
     * @return array keyed by setting name
    */
    public function getDefaultRrdSettings($stat_col)
    {
        return self::getDefaultRrdSettingsUsers();
    }

    public static function getDefaultRrdSettingsUsers()
    {
        $defaults =& drupal_static(__METHOD__);

        if (!isset($defaults)) {
            // get generic defaults
            $defaults = array(
                'interval' => (int) variable_get(
                'prod_default_rrd_interval',
                300
            ),
                'points_per_graph' => (int) variable_get(
                'prod_default_rrd_points_per_graph',
                300
            ),
            'points_per_aggregate' => (int) variable_get(
                'prod_default_rrd_points_per_aggregate',
                5
                ),
            );
            // check for nodes overrides
            $defaults['interval'] = (int) variable_get(
                'prod_default_rrd_interval_nodes',
                 $defaults['interval']
            );
            $defaults['points_per_graph'] = (int) variable_get(
                 'prod_default_rrd_points_per_graph_nodes',
                 $defaults['points_per_graph']
            );
            $defaults['points_per_aggregate'] = (int) variable_get(
                   'prod_default_rrd_points_per_aggregate_nodes',
                   $defaults['points_per_aggregate']
            );
        }
        return $defaults;
    }
}

