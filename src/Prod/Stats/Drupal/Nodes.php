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
class Nodes extends DrupalTask implements TaskInterface, StatsProviderInterface, ProdObserverInterface
{

    /**
     *
     * @var \Drupal\Prod\Stats\Nodes object (for Singleton)
     */
    protected static $instance;

    // Stat provider id. This comes from Task
    protected $id;

    protected $task_module='Drupal\\Prod\\Stats\\Drupal\\Nodes';
    // running task collector function
    protected $task_name='collect';

    protected $total_nodes;
    protected $total_nodes_time = 0;
    protected $published_nodes;
    protected $published_nodes_time = 0;
    protected $nodes_types = array();

    /**
     * Singleton implementation
     *
     * @return \Drupal\Prod\Stats\Nodes
     */
    public static function getInstance()
    {

        if (!isset(self::$instance)) {

            self::$instance = new Nodes();
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

        // count users by status
        $result = db_query("
            select status,count(*) as counter from {node} group by status
        ");

        $total = $online = 0;
        foreach($result as $record) {

            if (0 == $record->status) {

              $total += $record->counter;

            } else {

              $online = $record->counter;
              $total += $enabled;

            }
        }

        $this->logger->log("Nodes.total: " . $total, NULL, WATCHDOG_DEBUG);
        $this->logger->log("Nodes.published: " . $online, NULL, WATCHDOG_DEBUG);

        // count nodes by types
        $this->nodes_types = array();
        $result = db_query("
            select type, status,count(*) as counter from {node} group by type,status
        ");
        foreach($result as $record) {

            if (!array_key_exists($record->type, $this->nodes_types)) {
                $this->nodes_types[$record->type] = array(
                        'published' => 0,
                        'total' =>0
                );
            }

            if (0 != $record->status) {
                $this->nodes_types[$record->type]['published'] += $record->counter;
            }
            $this->nodes_types[$record->type]['total'] += $record->counter;

        }

        // add comment counter
        $this->nodes_types['comment'] = array(
                'published' => 0,
                'total' =>0
        );

        if (db_table_exists('comment')) {
            $result = db_query("
                select status,count(*) as counter from {comment} group by status order by status
            ");
            foreach($result as $record) {
                if (0 !== $record->status) {
                    $this->nodes_types['comment']['published'] += $record->counter;
                }
                $this->nodes_types['comment']['total'] += $record->counter;
            }
        }

        foreach ($this->nodes_types as $type => $infos) {
            $this->logger->log('Nodes.' . $type . '.total: ' . $infos['total'], NULL, WATCHDOG_DEBUG);
            $this->logger->log('Nodes.' . $type . '.published: ' . $infos['published'], NULL, WATCHDOG_DEBUG);
            $this->nodes_types[$type]['total'] = $this->nodes_types[$type]['total'] * 1000;
            $this->nodes_types[$type]['published'] = $this->nodes_types[$type]['published'] * 1000;
        }

        $this->setTotalNodes($total * 1000);
        $this->setOnlineNodes($online * 1000);
        $this->save();

        $this->manageRRD();
    }



    protected function getTotalNodes()
    {
        if (!isset($this->total_nodes)) {
            throw new DevelopperException('Object is not loaded, cannot extract total_nodes stat');
        }

        return $this->total_nodes;
    }

    protected function setTotalNodes($nb)
    {
        $this->total_nodes = (int) $nb;
        return $this;
    }

    protected function getOnlineNodes()
    {
        if (!isset($this->published_nodes)) {
            throw new DevelopperException('Object is not loaded, cannot extract online_nodes stat');
        }

        return $this->published_nodes;
    }

    protected function setOnlineNodes($nb)
    {
        $this->published_nodes = (int) $nb;
        return $this;

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
        $this->logger->log('Saving StatProvider records for Nodes Stats ',NULL, WATCHDOG_DEBUG);

        // Upsert the record
        try {
            db_merge('prod_drupal_stats')
              -> key( array(
                  'ptq_stat_tid' => $this->getId(),
                  'pds_name' => 'node_total',
              ) )
              -> fields( array(
                  'pds_value' => $this->getTotalNodes(),
                  'pds_is_1024' => 0,
                  'pds_timestamp' => REQUEST_TIME,
                  'pds_enable' => 1,
              ) )
              ->execute();

            db_merge('prod_drupal_stats')
              -> key( array(
                  'ptq_stat_tid' => $this->getId(),
                  'pds_name' => 'node_published',
              ) )
              -> fields( array(
                  'pds_value' => $this->getOnlineNodes(),
                  'pds_is_1024' => 0,
                  'pds_timestamp' => REQUEST_TIME,
                  'pds_enable' => 1,
              ) )
              ->execute();

              foreach ($this->nodes_types as $type => $infos) {
                  db_merge('prod_drupal_stats')
                    -> key( array(
                        'ptq_stat_tid' => $this->getId(),
                        'pds_name' => 'node_' . $type . '_total',
                    ) )
                    -> fields( array(
                        'pds_value' => $infos['total'],
                        'pds_is_1024' => 0,
                        'pds_timestamp' => REQUEST_TIME,
                        'pds_enable' => 1,
                    ) )
                    ->execute();
                  db_merge('prod_drupal_stats')
                    -> key( array(
                        'ptq_stat_tid' => $this->getId(),
                        'pds_name' => 'node_' . $type . '_published',
                    ) )
                    -> fields( array(
                        'pds_value' => $infos['published'],
                        'pds_is_1024' => 0,
                        'pds_timestamp' => REQUEST_TIME,
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
                switch ($res->pds_name) {

                    case 'node_total':
                        $this->setTotalNodes( (int) $res->pds_value);
                        $this->total_nodes_time = $res->pds_timestamp;
                        break;

                    case 'node_published':
                        $this->setOnlineNodes( (int) $res->pds_value);
                        $this->published_nodes_time = $res->pds_timestamp;
                        break;

                    default:
                        $parts = explode('_', $res->pds_name);
                        if ( ('node' === $parts[0]) && (3 === count($parts)) ) {

                            $type = $parts[1];
                            $subtype = $parts[2];

                            if (!array_key_exists($type, $this->nodes_types)) {
                                $this->nodes_types[$type] = array(
                                        'published' => 0,
                                        'total' =>0
                                );
                            }

                            $this->nodes_types[$type][$subtype] = $res->pds_value;
                            $this->nodes_types[$type][$subtype.'_time'] = $res->pds_timestamp;

                        } else {

                            $this->logger->log(
                              'Unknown stat attached to Node Stats collector: ' . $res->pds_name,
                              NULL,WATCHDOG_WARNING
                            );

                        }
                }
            }

        } catch (Exception $e) {
            throw new DbAnalyzerException(__METHOD__ . ": Unable to reload the nodes stats records. " . $e->getMessage());
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

        if ( 0 !== $this->total_nodes_time) {
            $stat_nodes_total = new Stat(
              $this->getId(),
              'node_total',
              $this->getTotalNodes(),
              $this->total_nodes_time,
              'Total number of Nodes'
            );
            $res['node_total'] = $stat_nodes_total;
        }

        if ( 0 !== $this->published_nodes_time) {
            $stat_nodes_published = new Stat(
              $this->getId(),
              'node_published',
              $this->getOnlineNodes(),
              $this->published_nodes_time,
              'Total number of published Nodes'
            );
            $res['node_published'] = $stat_nodes_published;
        }

        foreach ($this->nodes_types as $type => $infos) {
            if ( 0 !== $infos['published_time'] ) {
                $res['node_' . $type . '_published'] = new Stat(
                        $this->getId(),
                        'node_' . $type . '_published',
                        $infos['published'],
                        $infos['published_time'],
                        'Total number of published ' . $type . ' Nodes'
                );
                $res['node_' . $type . '_total'] = new Stat(
                        $this->getId(),
                        'node_' . $type . '_total',
                        $infos['total'],
                        $infos['total_time'],
                        'Total number of ' . $type . ' Nodes'
                );
            }
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
