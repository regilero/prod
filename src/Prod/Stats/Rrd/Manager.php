<?php

namespace Drupal\Prod\Stats\Rrd;

use Drupal\Prod\Stats\StatsProviderInterface;
use Drupal\Prod\Error\InvalidStatException;
use Drupal\Prod\ProdObject;

/**
 * RRD Manager
 *
 * Central C&C point for StatsProviders, Stat, and RRD records.
 */
class Manager extends ProdObject
{


    /**
     * List of RRD Definitions stored keyed by provider id.
     *
     * @var array
     */
    private $definitions;

    /**
     * List of Stats Providers
     *
     * @var array
     */
    private $providers;

    /**
     * List of Stats provided by the providers, keyed by provider id.
     *
     * @var array
     */
    private $stats;

    /**
     * List of Stats Providers ids
     *
     * @var array
     */
    private $providers_id_list;


    public function __construct() {

        // load the helpers (like $this->logger)
        $this->initHelpers();

    }


    /**
     * Load a bunch of Stats Providers, usually the first step
     *
     * @param int $stat_task_id, Task Id
     *
     * @param array|Iterable $providers, list of StatsProviderInterface objects
     *
     * @throws InvalidStatException
     *
     * @return \Drupal\Prod\Stats\Rrd\Manager
     */
    public function loadMultipleProviders($stat_task_id, $providers)
    {
        if (!isset($this->providers)) {
            $this->providers = array();
        }
        if (!array_key_exists($stat_task_id, $this->providers)) {
            $this->providers[$stat_task_id] = array();
        }


        if (!isset($this->definitions)) {
            $this->definitions = array();
        }
        if (!array_key_exists($stat_task_id, $this->definitions)) {
            $this->definitions[$stat_task_id] = array();
        }

        if (!isset($this->stats)) {
            $this->stats = array();
        }
        if (!array_key_exists($stat_task_id, $this->stats)) {
            $this->stats[$stat_task_id] = array();
        }

        if (!isset($this->providers_id_list)) {
            $this->providers_id_list = array();
        }
        if (!array_key_exists($stat_task_id, $this->providers_id_list)) {
            $this->providers_id_list[$stat_task_id] = array();
        }

        foreach($providers as $provider) {

            if (! $provider instanceOf StatsProviderInterface) {
                throw new InvalidStatException(__METHOD__ . ' has received something which is not a StatsProviderInterface');
            }

            $pid = $provider->getStatsProviderId();

            $this->providers_id_list[$stat_task_id][] = $pid;

            if (! array_key_exists($pid, $this->definitions[$stat_task_id])) {
                $this->definitions[$stat_task_id][$pid] = array();
            }
            if (! array_key_exists($pid, $this->stats[$stat_task_id])) {
                $this->stats[$stat_task_id][$pid] = array();
            }

            $this->providers[$stat_task_id][$provider->getStatsProviderId()] = $provider;

        }

        // Try to preload all definitions we have for these providers
        $this->_preloadRrdDefinitions($stat_task_id);

        // Load stats provided by theses providers
        $this->_loadProvidedStats($stat_task_id);

        return $this;
    }


    /**
     * Preload All RRD definitions stored in database for all providers
     * listed in $this->providers_id_list (each provider can have more than
     * one RRD definition, one for each provided stat).
     */
    protected function _preloadRrdDefinitions($stat_task_id)
    {
        $results = Definition::loadDefinitionsByProviders($stat_task_id, $this->providers_id_list);
        foreach ($results as $rrdDef) {
            $this->_storeRRDDef($stat_task_id, $rrdDef);
        }
    }

    /**
     * Store an RRD definition in the right place inside our internal
     *  storage.
     */
    protected function _storeRRDDef($stat_task_id, $rrdDef)
    {
        $provider_id = $rrdDef->getProviderId();
        $stat_col = $rrdDef->getColId();

        if (!array_key_exists($provider_id, $this->definitions[$stat_task_id])) {

            $this->definitions[$stat_task_id][$provider_id] = array();

        }

        $this->definitions[$stat_task_id][$provider_id][$stat_col] = $rrdDef;
    }

    /**
     * Load all stats provided by our providers
     */
    protected function _loadProvidedStats($stat_task_id)
    {

        foreach ($this->providers[$stat_task_id] as $provider) {

            $this->stats[$stat_task_id][$provider->getStatsProviderId()] = $provider->getStatsList();

        }

    }

    /**
     * Create an RRD definition which was not loaded by _preloadRrdDefinitions
     *
     * @param int $stat_task_id : the related task/module
     * @param int $stat_pid : the stat provider id (like tables for db stats)
     * @param string $stat_col : the stat column (like index size for tables)
     */
    protected function _createMissingRrdDefinitions($stat_task_id, $stat_pid, $stat_col)
    {
        // Ask the provider for defaults
        $provider = $this->providers[$stat_task_id][$stat_pid];
        $defaults = $provider->getDefaultRrdSettings($stat_col);

        $rrdDef = new Definition(
            $stat_task_id,
            $stat_pid,
            $stat_col,
            $defaults['interval'],
            $defaults['points_per_aggregate'],
            $defaults['points_per_graph']
        );

        // The save of this new definition will be done later
        $this->definitions[$stat_task_id][$stat_pid][$stat_col] = $rrdDef;
    }

    /**
     * Manage Rotations (RRD) records for all internally loaded stats
     * This will imply a big number of writes in RRD an RRD settings tables.
     *
     * @return \Drupal\Prod\Stats\Rrd\Manager
     */
    public function manageRotations()
    {

        foreach($this->stats as $stat_task_id =>$task_record) {

            foreach($task_record as $stat_pid => $statslist) {

                // Start a new transaction
                $transaction = db_transaction();
                try {

                    // the $stat_task_id or $stat_pid are always present, as they are
                    // created in loadMultipleProviders,
                    // so we do not need to check for that.

                    foreach ($statslist as $stat_col => $stat) {

                        // But RRD definitions may not exists yet in the definitions
                        // list, if it does not exists in db yet
                        if (!array_key_exists($stat_col, $this->definitions[$stat_task_id][$stat_pid])) {

                            //that's a missing one.
                            $this->_createMissingRrdDefinitions($stat_task_id, $stat_pid, $stat_col);

                        }

                        $rrdDef = $this->definitions[$stat_task_id][$stat_pid][$stat_col];

                        $this->logger->log('RRD Rotation for :task/:pid/:col', array(
                                ':task' => $stat_task_id,
                                ':pid' => $stat_pid,
                                ':col' => $stat_col
                            ),WATCHDOG_DEBUG);

                        $rrdDef->manageRotation($stat);

                        // the rrdDef may be a very big object
                        // release it after usage
                        unset($rrdDef);
                        unset($this->definitions[$stat_task_id][$stat_pid][$stat_col]);

                    }

                } catch (Exception $e) {
                    $transaction->rollback();
                    $this->logger->log($e, NULL, WATCHDOG_CRITICAL);
                }
                try {
                   // implicit $transaction->commit();, Drupal way
                   unset($transaction);
                } catch (Exception $e) {
                    $this->logger->log($e, NULL, WATCHDOG_CRITICAL);
                    die('transaction mess');
                }

            }

        }
        return $this;
    }

}
