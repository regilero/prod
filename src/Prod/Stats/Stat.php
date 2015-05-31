<?php

namespace Drupal\Prod\Stats;

/**
 * Statistic Record
 */
class Stat implements StatInterface
{

    private $stat_pid;
    private $stat_col;
    private $value;
    private $timestamp;

    /**
     * constructor of StatInterface object
     *
     * @param int $stat_pid Stat Provider id
     *
     * @param string $stat_col Stat Column key
     *
     * @param int $value Value with two decimal added while still being an int
     *
     * @param int UNIX timestamp of this stat
     *
     * @return StatInterface
     */
    public function __construct($stat_pid, $stat_col, $value, $timestamp)
    {
        $this->stat_pid = (int) $stat_pid;
        $this->stat_col = $stat_col;
        $this->value = (int) $value;
        $this->timestamp = (int) $timestamp;
    }

    /**
     * Get the Stat Unique Id
     *
     * @return int the stat id
     *
     */
    public function getId()
    {
    	return $this->stat_id;
    }
    
    /**
     * Get the stat Timestamp
     *
     * @return int the UNIX timestamp
     * 
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * Get the stat Value
     *
     * @return int the Stat Value int '2-decimal-as-int' form
     * 
     */
    public function getValue()
    {
        return $this->value;
    }


    /**
     * Get the Stat Provider Id
     *
     * @return int the stat provider id
     * 
     */
    public function getProviderId()
    {
        return $this->stat_pid;
    }

    /**
     * Get the Stat Column Id
     *
     * @return string the Stat column key value
     * 
     */
    public function getColId()
    {
        return $this->stat_col;
    }

}
