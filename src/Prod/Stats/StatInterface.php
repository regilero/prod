<?php

namespace Drupal\Prod\Stats;

use Drupal\Prod\Error\InvalidStatException;

/**
 * Statistic record
 */
interface StatInterface
{
    /**
     * constructor of StatInterface object
     *
     * @param int $stat_id Stat Provider id
     *
     * @param string $stat_col Stat Column key
     *
     * @param int $value Value with two decimal added while still being an int
     *
     * @param int UNIX timestamp of this stat
     *
     * @return StatInterface
     */
    public function __construct($stat_id, $stat_col, $value, $timestamp);

    /**
     * Get the stat Timestamp
     *
     * @return int the UNIX timestamp
     * 
     */
    public function getTimestamp();

    /**
     * Get the stat Value
     *
     * @return int the Stat Value int '2-decimal-as-int' form
     * 
     */
    public function getValue();


    /**
     * Get the Stat Provider Id
     *
     * @return int the stat provider id
     * 
     */
    public function getProviderId();

    /**
     * Get the Stat Column Id
     *
     * @return string the Stat column key value
     * 
     */
    public function getColId();

}
