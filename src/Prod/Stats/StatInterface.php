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
     * @param int $stat_pid Stat Provider id
     *
     * @param string $stat_col Stat Column key
     *
     * @param float $value Value, three decimal are added and internal storage is an int
     *
     * @param int UNIX timestamp of this stat
     *
     * @param string $label Stat Column Label
     *
     * @return StatInterface
     */
    public function __construct($stat_pid, $stat_col, $value, $timestamp, $label='');

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
