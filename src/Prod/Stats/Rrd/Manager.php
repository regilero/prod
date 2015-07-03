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
     * @param array|Iterable $providers, list of StatsProviderInterface objects
     *
     * @return TableInterface
     */
    public function loadMultipleProviders($providers)
    {
        if (!isset($this->providers)) {
            $this->providers = array();
        }
        if (!isset($this->definitions)) {
            $this->definitions = array();
        }
        if (!isset($this->stats)) {
        $this->stats = array();
        }
        if (!isset($this->providers_id_list)) {
            $this->providers_id_list = array();
        }
        
        foreach($providers as $provider) {

            if (! $provider instanceOf StatsProviderInterface) {
                throw new InvalidStatException(__METHOD__ . ' has received something which is not a StatsProviderInterface');
            }

            $id = $provider->getStatsProviderId();

            $this->providers_id_list[] = $id;

            if (! array_key_exists($id, $this->definitions)) {
                $this->definitions[$id] = array();
            }
            if (! array_key_exists($id, $this->stats)) {
                $this->stats[$id] = array();
            }
            
            $this->providers[$provider->getStatsProviderId()] = $provider;
        }

        // Try to preload all definitions we have for these providers
        $this->_preloadRrdDefinitions();

        // Load stats provided by theses providers
        $this->_loadProvidedStats();

        return $this;
    }


    /**
     * Preload All RRD definitions stored in database for all providers
     * listed in $this->providers_id_list (each provider can have more than
     * one RRD definition, one for each provided stat).
     */
    protected function _preloadRrdDefinitions()
    {
        $results = Definition::loadDefinitionsByProviders($this->providers_id_list);
        foreach ($results as $rrdDef) {
            $this->_storeRRDDef($rrdDef);
        }
    }

    /**
     * Store an RRD definition in the right place inside our internal
     *  storage.
     */
    protected function _storeRRDDef($rrdDef)
    {
        $provider_id = $rrdDef->getProviderId();
        $stat_col = $rrdDef->getColId();
        
        if (!array_key_exists($provider_id, $this->definitions)) {

            $this->definitions[$provider_id] = array();

        }

        $this->definitions[$provider_id][$stat_col] = $rrdDef;
    }
    
    /**
     * Load all stats provided by our providers
     */
    protected function _loadProvidedStats()
    {

        foreach ($this->providers as $provider) {

            $this->stats[$provider->getStatsProviderId()] = $provider->getStatsList();

        }

    }

    /**
     * Create an RRD definition which was not loaded by _preloadRrdDefinitions
     * @param int $stat_pid
     * @param string $stat_col
     */
    protected function _createMissingRrdDefinitions($stat_pid,$stat_col)
    {
        // Ask the provider for defaults
        $provider = $this->providers[$stat_pid];
        $defaults = $provider->getDefaultRrdSettings($stat_col);

        $rrdDef = new Definition(
            $stat_pid,
            $stat_col,
            $defaults['interval'],
            $defaults['points_per_aggregate'],
            $defaults['points_per_graph']
        );

        // The save of this new definition will be done later
        $this->definitions[$stat_pid][$stat_col] = $rrdDef;
    }

    /**
     * Manage Rotations (RRD) records for all internally loaded stats
     * This will imply a big number of writes in RRD an RRD settings tables.
     */
    public function manageRotations()
    {

        foreach($this->stats as $stat_pid => $statslist) {

            // the $stat_pid is always present, as it is created in loadMultipleProviders
            // so we do not need to check for that.

            foreach ($statslist as $stat_col => $stat) {

                if (!array_key_exists($stat_col, $this->definitions[$stat_pid])) {

                     //that's a missing one.
                     $this->_createMissingRrdDefinitions($stat_pid,$stat_col);
                }

                $rrdDef = $this->definitions[$stat_pid][$stat_col];
                $this->logger->log('RRD Rotation for :pid/:col', array(
                        ':pid' => $stat_pid,
                        ':col' => $stat_col
                    ),WATCHDOG_DEBUG);
                $rrdDef->manageRotation($stat);
                // the rrdDef may be a very big object
                // release it after usage
                unset($rrdDef);
                unset($this->definitions[$stat_pid][$stat_col]);

            }

        }
    }

}
