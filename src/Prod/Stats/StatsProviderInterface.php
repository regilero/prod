<?php

namespace Drupal\Prod\Stats;

use Drupal\Prod\Error\DbAnalyzerException;

/**
 * Stats Provider Interface.
 *
 * A Stats Provider is an object which can provides one or more (id+col)=>value stat
 * For example a Db\Table record can store several stats per table.
 */
interface StatsProviderInterface
{
    /**
     * Get the list of stats provided by this provider
     *
     * @return array List of StatsInterface, the array key
     *               is the stat_col.
     */
    public function getStatsList();
    
    /**
     * Get the stats provider id
     *
     * @return int identifier of the stat provider, usually the first part of the Stat id
     */
    public function getStatsProviderId();
    
    /**
     * Get the default RRD settings ('interval','points_per_graph','points_per_aggregate')
     * for this provider.
     *
     * @param string $stat_col Stat column identifier in this provider.
     *
     * @return array keyed by setting name
     */
    public function getDefaultRrdSettings($stat_col);


}
