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
    private $label;

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
    public function __construct($stat_pid, $stat_col, $value, $timestamp, $label='')
    {
        $this->stat_pid = (int) $stat_pid;
        $this->stat_col = $stat_col;
        $this->value = (int) $value; //($value * 1000);
        $this->timestamp = (int) $timestamp;
        $this->label = $label;
        return $this;
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
     * @return int the Stat Value in '3-decimal-as-int' form
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
