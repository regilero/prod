<?php

namespace Drupal\Prod\Stats\Rrd;

use Drupal\Prod\ProdObject;

/**
 * RRD Points Collection.
 *  All points collected MUST be attached to the same RRD id.
 *  The RRD id is managed at this level
 *
 */
class PointCollection Extends ProdObject
{

    /**
     * Points list
     *
     * @var array
     */
    private $points;
    
    /**
     * RRD identifier for all the attached points
     *
     * @var int
     */
    private $rrd_id;


    /**
     * Constructor
     * 
     * @return \Drupal\Prod\Stats\Rrd\PointCollection
     */
    public function __construct()
    {
        $this->points = array();

        // load the helpers (like $this->logger)
        $this->initHelpers();

        return $this;
    }

    /**
     * Set the RRD id for all attached points of this collection
     * 
     * @param int $id
     * 
     * @return \Drupal\Prod\Stats\Rrd\PointCollection
     */
    public function setRRDId($id)
    {
        $this->rrd_id = (int) $id;
        return $this;
    }
    
    /**
     * Return the RRD id for all points of this collection.
     * 
     * @throws InvalidRRDException
     * 
     * @return number
     */
    public function getRRDId()
    {
        if (! isset($this->rrd_id) ) {
            throw new InvalidRRDException('The RRD id for the point collection is not set.');
        }
        return $this->rrd_id;
    }
    
    /**
     * Add a point in the Collection
     * 
     * @param Point $point
     * 
     * @return \Drupal\Prod\Stats\Rrd\PointCollection
     */
    public function add(Point $point)
    {
        $this->points[] = $point;
        return $this;
    }

    /**
     * Save all points of the collection
     */
    public function save()
    {
        $ins_query = db_insert('prod_rrd')
          -> fields(array(
                'prs_id',
                'pr_timestamp',
                'pr_value',
                'pr_value_max',
                'pr_value_min',
                'pr_rrd_index',
                'pr_aggregate_level',
          ));

        foreach($this->points as $point) {
        
            if ($point->isNew()) {
                $record = array();
                
                // remap
                $record['prs_id'] = $this->getRRDId();
                $record['pr_timestamp'] = $point->getTimestamp();
                // the value is transformed in the point to allow
                // storage of decimals as ints
                $record['pr_value'] = $point->getValue();
                $record['pr_value_max'] = $point->getMaxValue();
                $record['pr_value_min'] = $point->getMinValue();
                $record['pr_rrd_index'] = $point->getIndex();
                $record['pr_aggregate_level'] = $point->getAggregateLevel();
                
                $ins_query->values($record);
                
            } else {
                
                $update_query = db_update('prod_rrd')
                  -> fields(array(
                          'pr_timestamp' => $point->getTimestamp(),
                          'pr_value' => $point->getValue(),
                          'pr_value_max' => $point->getMaxValue(),
                          'pr_value_min' => $point->getMinValue(),
                  ))
                  ->condition('prs_id', $this->getRRDId())
                  ->condition('pr_rrd_index', $point->getIndex())
                  ->condition('pr_aggregate_level', $point->getAggregateLevel());
                $update_query->execute();
                
            }
        }
        
        // we always have at least 1 insert
        $ins_query->execute();
    }

}
