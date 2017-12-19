<?php

namespace Drupal\Prod\Stats\Rrd;

use Drupal\Prod\ProdObject;
use Drupal\Prod\Stats\StatInterface;
use Drupal\Prod\Error\InvalidStatException;
use Drupal\Prod\Error\InvalidRRDException;
use Drupal\Prod\Error\UndefinedRRDDefinitionException;

/**
 * RRD Definition, which is also the object performing the RRD rotations
 *
 */
class Definition Extends ProdObject
{

    /**
     * Stat definition Id
     *
     * @var int
     */
    private $id;

    /**
     * Stat Task id (part of the Stat key)
     *
     * @var int
     */
    private $stat_task_id;

    /**
     * Stat provider id (part of the Stat key)
     *
     * @var int
     */
    private $stat_pid;

    /**
     * Stat column (part of the Stat key)
     *
     * @var int
     */
    private $stat_col;

    /**
     * Last update on this RRD
     *
     * @var int
     */
    private $last_timestamp;

    /**
     * Interval between regular RRD entries (we apply a 50%/150% rule
     * to avoid entries or add missing ones)
     *
     * @var int
     */
    private $interval;

    /**
     * Number of RRD entries per graph for this Stat
     *
     * @var int
     */
    private $points_per_graph;

    /**
     * iMax Number of RRD entries for this Stat
     *
     * @var int
     */
    private $max_points;

    /**
     * Points of graph G used to make an aggregate on graph G+1
     *
     * @var int
     */
    private $points_per_aggregate;

    /**
     * Missing points_per_aggregate on graphs (2,3,4,5)
     *
     * @var array
     */
    private $points_before_level;

    /**
     * Internal storage of numbers of created entires per graph level
     *
     * @var array
     */
    private $new_points;

    /**
     * Internal storage of last known records
     * Used to compute aggregates or detect broken databases
     *
     * @var StatInterface object
     */
    private $last_entries;

    /**
     * Internal storage of RRD points that should be saved
     * at the end
     *
     * @var PointCollection set of Points
     */
    private $points;

    /**
     * Constructor, given an arry with fields usually stored on the definition
     * table, we should be able to create a good representation.
     *
     * @param int $stat_task_id Stat Task Identifier
     *
     * @param int $stat_pid Stat Provider Identifier
     *
     * @param string $stat_col Stat Column name
     *
     * @param int $interval, Stat Provider Identifier
     *
     * @param int $points_per_aggregate, Number of points to build upper level aggregate
     *
     * @param int $points_per_graph, Number of points on each graph
     *
     * @throws InvalidRRDException
     *
     * @return \Drupal\Prod\Stats\Rrd\Definition
     */
    public function __construct($stat_task_id, $stat_pid, $stat_col, $interval, $points_per_aggregate, $points_per_graph)
    {
        $this->stat_task_id = (int) $stat_task_id;

        $this->stat_pid = (int) $stat_pid;

        $this->stat_col = $stat_col;

        $this->interval = (int) $interval;

        if ( ! $this->interval > 0) {
            throw new InvalidRRDException('interval must be a positive number.');
        }

        $this->points_per_aggregate = (int) $points_per_aggregate;

        if ( !  $this->points_per_aggregate > 0) {
            throw new InvalidRRDException('points_per_aggregate must be a positive number.');
        }

        $this->points_per_graph = (int) $points_per_graph;

        if ( ! $this->points_per_graph > 0) {
            throw new InvalidRRDException('points_per_graph must be a positive number.');
        }

        $this->max_points = $this->points_per_graph * 5;

        // Some extra checks
        if ($this->points_per_aggregate > $this->points_per_graph) {
            throw new InvalidRRDException('points_per_graph cannot be greater than points_per_aggregate.');
        }

        if (0 !== ($this->points_per_graph % $this->points_per_aggregate)) {
            throw new InvalidRRDException('points_per_graph must be a multiple of points_per_aggregate.');
        }

        // Note that this MEANS IT IS A NEW RECORD!! unless you set something in it later
        // For existing records this is used to detect problems on the data
        $this->last_timestamp = NULL;

        $this->id = NULL;

        $this->points_before_level = array(
            0=>0, /* not used */
            1=>0,
            2=>0,
            3=>0,
            4=>0,
            5=>0,
        );

        // Storage of RRD points (not all of them)
        $this->points = new PointCollection();

        // load the helpers (like $this->logger)
        $this->initHelpers();

        return $this;
    }

    /**
     * Set the Definition Id
     *
     * @param int $id
     * @return \Drupal\Prod\Stats\Rrd\Definition
     */
    public function setId($id)
    {
        $this->id = (int) $id;
        return $this;
    }

    /**
     * Definition's Id Getter.
     * Note that an unsaved definition does not have an Id yet.
     * Check the hasId() function.
     *
     * @throws UndefinedRRDDefinitionException if the Definition Id is unknown
     * @return number
     */
    public function getId()
    {
        if (!isset($this->id)) {
            throw new UndefinedRRDDefinitionException();
        }
        return $this->id;
    }

    /**
     * Does this Definition has an RRD id?
     * If so it means it was saved at least once.
     *
     * @return boolean
     */
    public function hasId()
    {
        return  (isset($this->id));
    }

    public function getTaskId()
    {
        return $this->stat_task_id;
    }

    public function getProviderId()
    {
        return $this->stat_pid;
    }

    public function getColId()
    {
        return $this->stat_col;
    }

    /**
     * Return a list of Drupal\Prod\Stats\Rrd\Definitions from the Database.
     *
     * All known definitions for the given set of providers are returned.
     * Note that this function can only retrieve providers attached to the same
     * StatTask (as providers ids are managed by each stat task).
     *
     * @param int $task_id id of the Stat task for the given providers
     *
     * @param array $providers_ids list of Providers id
     *
     * @return multitype:\Drupal\Prod\Stats\Rrd\Definition
     */
    public static function loadDefinitionsByProviders($task_id, $providers_ids) {

        if (count($providers_ids) > 0) {
            $results = db_select('prod_rrd_settings','s')
                ->fields('s')
                ->condition('ptq_stat_tid', $task_id, '=')
                ->condition('prs_stat_pid', $providers_ids, 'IN')
                ->execute();
        } else {
            $results = array();
        }

        $objects = array();

        foreach ($results as $result) {
            $rrdDef = new Definition(
                $result->ptq_stat_tid,
                $result->prs_stat_pid,
                $result->prs_stat_col,
                $result->prs_interval,
                $result->prs_points_per_aggregate,
                $result->prs_points_per_graph
            );
            $rrdDef->setLastTimestamp($result->prs_last_timestamp)
                ->setPointsBeforeLevel(
                    $result->prs_points_before_level_2,
                    $result->prs_points_before_level_3,
                    $result->prs_points_before_level_4,
                    $result->prs_points_before_level_5
                )
                ->setId($result->prs_id);
            $objects[] = $rrdDef;
        }

        return $objects;
    }

    /**
     * Set the last timestamp of created RRD entry for this Definition.
     *
     * @param int $timestamp
     * @return \Drupal\Prod\Stats\Rrd\Definition
     */
    public function setLastTimestamp($timestamp)
    {
        $this->last_timestamp = (int) $timestamp;
        return $this;
    }

    /**
     * Set the number of point needed before aggregate point generation for the 4 given levels.
     * There is no need to set a number of points for level 1, which is not an aggregate level.
     *
     * @param int $level2
     * @param int $level3
     * @param int $level4
     * @param int $level5
     * @return \Drupal\Prod\Stats\Rrd\Definition
     */
    public function setPointsBeforeLevel($level2, $level3, $level4, $level5)
    {
        // TODO: check positive numbers

        $this->points_before_level[2] = (int) $level2;

        $this->points_before_level[3] = (int) $level3;

        $this->points_before_level[4] = (int) $level4;

        $this->points_before_level[5] = (int) $level5;

        return $this;
    }

    /**
     * Here be Dragons.
     * Given a stat object with a value we Manage adding RRD points for this stat.
     * That is at least one point created or updated in each level graph
     * and maybe some deletions and increments for others.
     *
     * @param StatInterface $stat, the new stat value to manage for this Rotation
     *
     * @return boolean
     */
    public function manageRotation(StatInterface $stat)
    {

        if (!$this->hasId()) {

            // new definition, so new RRD record, simple case
            $this->logger->log('New definition, create default RRD records',NULL, WATCHDOG_DEBUG);
            return $this->_manageNewRecord($stat);

        }

        // Note:
        // Now the more complex case of something that needs updates and rotations
        // the definition is not new (we have an id), but there's maybe no RRD
        // point created for this entry yet (if we had problems on first run).

        //---------------------------------------------------------------------------
        // Step 1 - Detect that we really need a rotation.
        //          Is there enough time since last record?

        $timestamp = $stat->getTimestamp();

        // this is the minimal time for a new record (t+50% of interval)
        $minimal_new_record = $this->last_timestamp + floor( $this->interval * 0.5 );

        if ($timestamp < $minimal_new_record) {
            // Not enough time elapsed!
            $this->logger->log('Interval too short, break early', NULL, WATCHDOG_DEBUG);
            return FALSE;
        }

        //----------------------------------------------------------------------
        // Step 2 - preload some RRD records that will be needed later if they
        // exists to compute aggregates later
        $this->_loadPreviousRRDEntries();

        //----------------------------------------------------------------------
        // Step 2b - Detect graphs where we'll have to create new RRD points
        // We do not not add these points yet.
        $this->_detectNewPoints();

        //----------------------------------------------------------------------
        // Step 3 - Detect that we have no existing points for this record, yet
        // or a last recorded point timestamp which is not the one we think
        // or strange cases like existing points having timestamps in the
        // future (like we have a database from another install, maybe)
        // So we have to fallback to new record creation
        if ( $this->_detectProblems( $stat ) ) {
            return FALSE;
        }


        //----------------------------------------------------------------------
        // Step 4 - increment all rrd_index of previous records in database for
        //          graph 1 and all graphs where points_before_level_* is
        //          reached.
        $this->_incrementRRDIndex();

        //----------------------------------------------------------------------
        // Step 5 - Add the new points in graphs that need one
        $this->_createNewRRDEntries( $stat );

        //----------------------------------------------------------------------
        // Step 6 - Remove uneeded points in all graph levels
        $this->_removeExtraPoints();

        // Finaly save all the things that should be saved
        $this->points->setRRDId($this->getId());
        $this->logger->log('Saving RRD points for RRD id :rrd_id',
                array(':rrd_id' => $this->getId()), WATCHDOG_DEBUG);
        $this->points->save();
        $this->save();
        return TRUE;

    }

    /**
     * Remove bad records:
     *  - record having timestamp greater than the provided one
     *  - all 1st records
     * @param int $present_timestamp
     */
    protected function _cleanup_bad_records( $present_timestamp )
    {
        $query = db_delete('prod_rrd')
            ->condition('prs_id', $this->getId());
        $orcond = db_or();
        $orcond->condition('pr_rrd_index', 1);
        $orcond->condition('pr_timestamp', $present_timestamp, '>');
        $query->condition($orcond);
        $query->execute();
    }

    /**
     * Remove points that should not exists anymore on graphs
     *  (based on $this->points_per_graph)
     */
    protected function _removeExtraPoints()
    {
        $query = db_delete('prod_rrd')
            ->condition('prs_id', $this->getId())
            ->condition('pr_rrd_index', $this->points_per_graph, '>')
            ->execute();
    }

    /**
     *
     * @param StatInterface $stat
     * @return boolean : FALSE if everythin was right <- WARNING!
     */
    protected function _detectProblems( $stat )
    {
        if ( !isset($this->last_entries[1][0])
                || !isset($this->last_entries[2][0])
                || !isset($this->last_entries[3][0])
                || !isset($this->last_entries[4][0])
                || !isset($this->last_entries[5][0])
        ) {

            $this->_cleanup_bad_records( $this->last_timestamp );

            // fallback to new record creation
            $this->logger->log(
                    'Missing data, fallback to create default RRD records for '
                    . $this->getId(),
                    NULL, WATCHDOG_DEBUG);

            return $this->_manageNewRecord($stat);

        }

        if ( ($this->last_timestamp < $this->last_entries[1][0]->getTimestamp())
                || ($this->last_timestamp < $this->last_entries[2][0]->getTimestamp())
                || ($this->last_timestamp < $this->last_entries[3][0]->getTimestamp())
                || ($this->last_timestamp < $this->last_entries[4][0]->getTimestamp())
                || ($this->last_timestamp < $this->last_entries[5][0]->getTimestamp())
        ) {

            $entries = implode(',', array(
                    $this->last_entries[1][0]->getTimestamp(),
                    $this->last_entries[2][0]->getTimestamp(),
                    $this->last_entries[3][0]->getTimestamp(),
                    $this->last_entries[4][0]->getTimestamp(),
                    $this->last_entries[5][0]->getTimestamp(),
            ));
            $this->logger->log(
                    'Strange data, we have records in the future for rrd :id: :entries > last_timestamp: :last',
                    array(
                        ':id' => $this->getId(),
                        ':entries' => $entries,
                        ':last' => $this->last_timestamp,
                    ), WATCHDOG_WARNING);

            $this->_cleanup_bad_records( $this->last_timestamp );

            // fallback to new record creation
            $this->logger->log(
                    'fallback to create default RRD records for '
                    . $this->getId(),
                    NULL, WATCHDOG_DEBUG);

            return $this->_manageNewRecord($stat);

        }

        if ($this->last_timestamp != $this->last_entries[1][0]->getTimestamp()) {

            $this->_cleanup_bad_records( $this->last_timestamp );

            // fallback to new record creation
            $this->logger->log(
                    'Strange data, fallback to create default RRD records for '
                    . $this->getId(),
                    NULL, WATCHDOG_DEBUG);

            return $this->_manageNewRecord($stat);

        }

        return FALSE;
    }

    protected function _manageNewRecord(StatInterface $stat)
    {

        $this->logger->log("Easy case, it's a new RRD record, we create 1 point for each of the 5 graphics.", NULL, WATCHDOG_DEBUG);

        // we also update the definition with last recorded timestamp
        $this->last_timestamp = $stat->getTimestamp();

        // Next points of the 4 last graphs will be after this number of elements
        // added in level 1.
        // In fact the last point is always updated, but this is the indicator
        // of new point (with insert and delete)
        $this->points_before_level[2] = $this->points_per_aggregate-1;
        $this->points_before_level[3] = $this->points_per_aggregate-1;
        $this->points_before_level[4] = $this->points_per_aggregate-1;
        $this->points_before_level[5] = $this->points_per_aggregate-1;

        $id = $this->save();

        // Create 1 point on each of the 5 graphs
        // In fact, we have a valid value for each graph
        $value = $stat->getValue();
        $index = 1;
        for ($i = 1; $i <= 5; $i++) {
            $point =  new Point( $index, $i );
            $point->setTimestamp($this->last_timestamp)
                ->setValue($value)
                ->setValueMax($value)
                ->setValueMin($value)
                ->flagNew(TRUE);
            $this->points->add($point);
        }

        // Save all theses new RRD points
        $this->points->setRRDId($id);
        $this->points->save();

        return TRUE;

    }

    /**
     * Load previous RRD entries for this Definition that could be used to
     * compute aggregates
     *
     * Note 1: this is done before computation of the new values for
     *  $this->points_before_level
     * Note 2: we always try to load the 1st point on different graph level
     *  to help detecting RRD definitions having no RRD points yet or having
     *  strange points (like future values)
     *
     * @return array of last entries by aggregate level
     */
    protected function _loadPreviousRRDEntries()
    {
        $this->last_entries = array(
          1 => array(),
          2 => array(),
          3 => array(),
          4 => array(),
          5 => array(),
        );

        // For each graph level --except the last one --
        // load points that could be needed for aggregates later.

        /*$max_level = 0;
        for ($level = 1; $level< 5; $level++) {

            $this->last_entries[$level] = array();

            // we'll need to load points on that level if we reach
            // the aggregate limit
            if (1 == $this->points_before_level[ $level + 1 ]) {
                $max_level = $level;
            }

        }*/

        $query = db_select('prod_rrd', 'r')
              ->fields('r')
              ->condition('prs_id', $this->getId());

        // TODO: review: 1st point not needed?
        // we will select all 1st points
        // and all needed points on differents levels if any

        // on level 5 we only need the 1st point. But query is maybe faster
        // with a simple filter
        /*$orcond = db_or();
        $andcond1 = db_and();
        $andcond1
            ->condition('pr_aggregate_level', $max_level, '<=')
            ->condition('pr_rrd_index', $this->points_per_aggregate, '<=');

        $orcond->condition($andcond1);
        $orcond->condition('pr_rrd_index', 1, '=');
        $query->condition($orcond)
        */
        $query->condition('pr_rrd_index', $this->points_per_aggregate, '<=')
          ->orderBy('pr_aggregate_level', 'ASC')
          ->orderBy('pr_rrd_index', 'ASC');
        $results = $query->execute();

        foreach ($results as $result) {

            $point = new Point(
                            $result->pr_rrd_index,
                            $result->pr_aggregate_level
            );
            $point->setValue($result->pr_value)
                ->setValueMax($result->pr_value_max)
                ->setValueMin($result->pr_value_min)
                ->setTimestamp($result->pr_timestamp)
                ->flagNew(FALSE);

            $this->last_entries[$point->getAggregateLevel()][$point->getIndex()-1] = $point;

        }

        return $this->last_entries;

    }

    /**
     * Feed $this->new_points with points that will have to be added on each
     * level and adjust $this->points_before_level accordingly.
     */
    protected function _detectNewPoints()
    {
        $this->new_points = array();

        // We have only 1 point added, on the 1st graph, maybe also on others
        // Compute aggregate limits and final number of created points

        $this->new_points[1] = 1;
        $this->points_before_level[2] = $this->points_before_level[2]-1;

        for ($i = 2; $i <= 5; $i++) {

            if ($this->points_before_level[$i] == 0) {

                // new aggregate!

                $this->new_points[$i] = 1;

                if ($i < 5) {
                    $this->points_before_level[$i+1] = $this->points_before_level[$i+1]-1;
                }

                // next time we'll start with an empty aggregate
                $this->points_before_level[$i] = $this->points_per_aggregate;

            } else {

                // nothing on that level
                $this->new_points[$i] = 0;

            }

        }

    }

    protected function _incrementRRDIndex()
    {

        // FIXME: a factory and two version? ...
        global $databases;
        $driver = ucFirst($databases['default']['default']['driver']);

        foreach( $this->new_points as $level => $nb_points ) {

            if ( $nb_points > 0 ) {
                if ('Pgsql' === $driver) {
                    // deferrable constraint not available, fuck
                    /*db_query("
                      SET CONSTRAINTS prod_rrd_pkey DEFERRED;
                    ");*/
                    // so let's use an awfull hack. Pertty sure I'd better stop
                    // trying to update all rows, not efficient in terms of database
                    $results = db_query("
                        UPDATE {prod_rrd}
                        SET pr_rrd_index=-(pr_rrd_index + 1)
                        WHERE prs_id=:id
                        AND pr_aggregate_level=:level
                    ", array(
                        ':id' => $this->getId(),
                        ':level' => $level
                    ));
                    $results = db_query("
                        UPDATE {prod_rrd}
                        SET pr_rrd_index=-(pr_rrd_index)
                        WHERE prs_id=:id
                        AND pr_aggregate_level=:level
                    ", array(
                        ':id' => $this->getId(),
                        ':level' => $level
                    ));
                } else {
                    // We cannot use a db_update because of the order by.
                    // The order by pr_rrd_index DESC is there to avoid a duplicate
                    // key on unique index for MySQL while updating (no deferred
                    // index check).
                    $results = db_query("
                        UPDATE {prod_rrd}
                        SET pr_rrd_index=pr_rrd_index + 1
                        WHERE prs_id=:id
                        AND pr_aggregate_level=:level
                        ORDER BY prs_id ASC,
                                pr_aggregate_level ASC,
                                pr_rrd_index DESC
                    ", array(
                        ':id' => $this->getId(),
                        ':level' => $level
                    ));
                }
            }
        }
    }

    protected function _createNewRRDEntries(StatInterface $stat)
    {

        $value = $stat->getValue();

        $timestamp = $stat->getTimestamp();

        // Update the Definition
        $this->last_timestamp = $stat->getTimestamp();

        foreach ( $this->last_entries as $level => $old_points ) {

            // note that $old_point should always contain at least 1 entry at
            // index 0; this is ensured by _detectProblems() call

            // init aggregates
            $agg_min = null;
            $agg_max = null;
            $total_value = 0;
            $agg_value = 0;
            $nb_points = 0;
            $last_timestamp = $timestamp;

            if ( 1 == $level ) {

                // easy case, on 1st level graph we have no aggregation
                $agg_max = $value;
                $agg_min = $value;
                $nb_points = 1;
                $total_value = $value;
                $last_timestamp = $timestamp;

            } else {

                // On theses levels 2,3,4,5, points are computed from aggregates
                // the first point is an aggregate of N entries.
                // N is between 1 (lonely new point on previous level) and
                // $this->points_per_aggregate (last value for this point, next
                // turn a new entry will be created on this level).
                foreach ( $this->last_entries[ $level -1 ] as $idx => $low_point) {

                    // new points on previous level may have added one extra
                    // point that should not be computed in this aggregate
                    if ( $idx < $this->points_per_aggregate) {

                        $nb_points++;

                        $value = $low_point->getValue();
                        $min_value = $low_point->getMinValue();
                        $max_value = $low_point->getMaxValue();

                        $total_value += $value;

                        if (is_null($agg_max) || ($max_value > $agg_max) ) {
                            $agg_max = $max_value;
                        }

                        if (is_null($agg_min) || ($min_value < $agg_min) ) {
                            $agg_min = $min_value;
                        }

                        if ( 0 == $idx ) {
                            // we take timestamp of last entry only
                            $last_timestamp = $low_point->getTimestamp();
                        }
                    }

                }

            }

            // Average computation
            $agg_value = $total_value / $nb_points;

            // create the point to save
            $point = new Point( 1, $level );

            $point->setValue($agg_value)
              ->setValueMax($agg_max)
              ->setValueMin($agg_min)
              ->setTimestamp($last_timestamp);


            if ( 1 == $this->new_points[$level]) {

                // We know that this point is not an update but an insert on
                // the db storage (like 'always' for level 1 and sometimes for others).
                // Becomes the new rrd_index 1 in our local storage (i.e. 0 in this structure)
                array_unshift($this->last_entries[$level], $point);

                // new point, needs insert and not update query
                $point->flagNew(TRUE);

            } else {

                // this point will be updated on the db side, store it locally
                // so aggregates computations for next level will be exact.
                $this->last_entries[$level][0] = $point;

                // it is not a new point
                $point->flagNew(FALSE);
            }


            // record this point, with later a db save,
            // either an insert or an update
            // it will be an insert if _incrementRRDIndex has removed
            // previous record with same key.
            $this->points->add($point);

        } // end loop on graph levels

        // points created are saved later, same fr the rddDef record
    }


    /**
     * Save the RRD Definition in database and return the Defition Id
     * @return int id
     */
    protected function save()
    {
//        try {
            // upsert the record
        if ($this->hasId()) {
            db_merge('prod_rrd_settings')
            ->key( array(
                    'prs_id'  => $this->id,
            ))
            ->fields( array(
                    'ptq_stat_tid'  => $this->stat_task_id,
                    'prs_stat_pid'  => $this->stat_pid,
                    'prs_stat_col' => $this->stat_col,
                    'prs_last_timestamp' => $this->last_timestamp,
                    'prs_interval' => $this->interval,
                    'prs_points_per_graph' => $this->points_per_graph,
                    'prs_points_per_aggregate' => $this->points_per_aggregate,
                    'prs_points_before_level_2' => $this->points_before_level[2],
                    'prs_points_before_level_3' => $this->points_before_level[3],
                    'prs_points_before_level_4' => $this->points_before_level[4],
                    'prs_points_before_level_5' => $this->points_before_level[5],
            ))
            ->execute();

            return $this->id;

        } else {

            // New record
            // This should be an insert

            db_merge('prod_rrd_settings')
                ->key( array(
                    'ptq_stat_tid'  => $this->stat_task_id,
                    'prs_stat_pid'  => $this->stat_pid,
                    'prs_stat_col' => $this->stat_col,
                ))
                ->fields( array(
                   'prs_last_timestamp' => $this->last_timestamp,
                   'prs_interval' => $this->interval,
                   'prs_points_per_graph' => $this->points_per_graph,
                   'prs_points_per_aggregate' => $this->points_per_aggregate,
                   'prs_points_before_level_2' => $this->points_before_level[2],
                   'prs_points_before_level_3' => $this->points_before_level[3],
                   'prs_points_before_level_4' => $this->points_before_level[4],
                   'prs_points_before_level_5' => $this->points_before_level[5],
                ))
                ->execute();

            $result = db_select('prod_rrd_settings', 's')
                ->fields('s', array('prs_id'))
                ->condition('ptq_stat_tid', $this->stat_task_id)
                ->condition('prs_stat_pid', $this->stat_pid)
                ->condition('prs_stat_col', $this->stat_col)
                ->execute();
            foreach($result as $result) {
                $id = $result->prs_id;
            }

            $this->setId($id);

            return $id;
        }
/*        } catch (Exception $e) {
            die('ouch');
        }*/
    }
}
