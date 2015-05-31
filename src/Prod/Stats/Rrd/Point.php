<?php

namespace Drupal\Prod\Stats\Rrd;

use Drupal\Prod\ProdObject;
use Drupal\Prod\Error\InvalidRRDPointException;

/**
 * RRD Point
 *
 * Note that the point has no knowledge of his RRD id, this is usually managed
 * at the PointCollection level instead.
 */
class Point Extends ProdObject
{

    /**
     * Point value
     *
     * @var int
     */
    private $value;

    /**
     * Point Max value
     *
     * @var int
     */
    private $max_value;

    /**
     * Point Min value
     *
     * @var int
     */
    private $min_value;

    /**
     * Timestamp of this point
     *
     * @var int
     */
    private $timestamp;

    /**
     * Point aggregate level (graph 1/2/3/4/5 ?)
     *
     * @var int
     */
    private $aggregate_level;

    /**
     * Index of the point in the graph
     *
     * @var array
     */
    private $index;

    /**
     * Create an RRD point placed at given index on the given graph.
     * 
     * @param int $index index of the point on this graph
     * 
     * @param int $level aggregate level of the graph
     * 
     * @throws InvalidRRDPointException
     * 
     * @return \Drupal\Prod\Stats\Rrd\Point
     */
    public function __construct($index, $level)
    {

        $this->index = (int) $index;

        if ($this->index < 1) {
            throw new InvalidRRDPointException('RRD Point index must be greater than 1.');
        }
        
        $this->aggregate_level = (int) $level;
        
        if ( ($this->aggregate_level < 1) || ($this->aggregate_level > 5)) {
            throw new InvalidRRDPointException('RRD Point level cannot be anything but a number between 1 and 5.');
        }

        // load the helpers (like $this->log)
        $this->initHelpers();

        return $this;
    }

    /**
     * set the RRD Point timestamp.
     * 
     * @param int $timestamp
     * 
     * @return \Drupal\Prod\Stats\Rrd\Point
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = (int) $timestamp;
        return $this;
    }

    /**
     * Set the RRD point value.
     * 
     * @param number $value It can be a float.
     *                      We will only keep the int value
     *                      
     * @return \Drupal\Prod\Stats\Rrd\Point
     */
    public function setValue($value)
    {
        // this one may be a float because of average computations
        $this->value = (int) floor($value);
        return $this;
    }

    /**
     * Set the RRD point Max value.
     * 
     * @param int $value It can be a float.
     *                   We will only keep the int value
     *                   
     * @return \Drupal\Prod\Stats\Rrd\Point
     */
    public function setValueMax($value)
    {
        $this->max_value = (int) floor($value);
        return $this;
    }

    /**
     * Set the RRD point Min value.
     * 
     * @param int $value It can be a float.
     *                   We will only keep the int value
     *                   
     * @return \Drupal\Prod\Stats\Rrd\Point
     */
    public function setValueMin($value)
    {
        $this->min_value = (int) floor($value);
        return $this;
    }

    public function getTimestamp()
    {
        return $this->timestamp;
    }
    
    /**
     * return the Point value.
     * @return number
     */
    public function getValue()
    {
        return $this->value;
    }
    
    /**
     * return the Point Max value.
     * @return number
     */
    public function getMaxValue()
    {
        return $this->max_value;
    }
    
    /**
     * return the Point Min value.
     * @return number
     */
    public function getMinValue()
    {
        return $this->min_value;
    }
    
    public function getIndex()
    {
        return $this->index;
    }
    
    public function getAggregateLevel()
    {
        return $this->aggregate_level;
    }
    
}
