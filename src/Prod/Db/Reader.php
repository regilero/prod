<?php

namespace Drupal\Prod\Db;

use Drupal\Prod\Error\DevelopperException;
use Drupal\Prod\Error\ProdException;
use Drupal\Prod\Output\Formatter;
use Drupal\Prod\Output\Column;
use Drupal\Prod\ProdObject;

/**
 * Drupal\Prod\Db\Reader class
 *
 * reader of stored statictics data concerning the database.
 */
class Reader extends ProdObject
{

    /**
     *
     * @var array of \Drupal\Prod\Db\Reader object (for sort-of-Singletons)
     * keyed by data_id
     */
    protected static $instances;

    /**
     * Data id of the Reader instance (can be used in caches of definitions)
     */
    protected $id;

    /**
     * Singleton implementation
     *
     * @return \Drupal\Prod\Db\Reader
     */
    public static function getInstance($data_id)
    {
        if (!isset(self::$instances)) {
            self::$instances = array();
        }

        if (!array_key_exists($data_id, self::$instances)) {

            self::$instances[$data_id] = new Reader($data_id);
        }

        return self::$instances[$data_id];
    }

    public function __construct( $id )
    {
        $this->id = $id;
        return $this->initHelpers();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAvailableDatabases() {
        $query = db_select('prod_db_stats', 's')
            ->fields('s',array(
                'pdb_id',
                'pdb_identifier',
                'pdb_db_name',
            ))
            ->condition('pdb_is_database', 1)
            ->condition('pdb_enable', 1);;

        $results = $query->execute();

        $dbs = array();
        foreach($results as $result) {
            $dbs[ $result->pdb_id ] = $result->pdb_db_name
                . '('
                . $result->pdb_identifier
                . ')';
        }

        return $dbs;
    }

    protected function _getTopTablesFormatter()
    {
        $formatter = new Formatter();

        $formatter->setGraphType('2StackedBars1Line')
                ->setGraphLeftAxis('full_size', t('Full Size'))
                ->setGraphRightAxis('rows', t('Nb Rows'))
                ->setGraphBottomAxis('table');

        $col = new Column();
        $col->mapColumn('pdb_id')
            ->setLabel('id')
            ->addFormat('id')
            ->setTitle('id');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->setMultipleMap( array(
                'type' => 'concat',
                'pdb_db_name' => 'field',
                ' (' => 'str',
                'pdb_identifier' => 'field',
                ')' => 'str'
        ))
        ->addFormat('check_plain')
            ->setLabel('db')
            ->flagInTitle(TRUE)
            ->setTitle('Db');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_table')
            ->setLabel('table')
            ->addFormat('check_plain')
            ->flagInTitle(TRUE)
            ->setTitle('Db Table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_full_size')
            ->setLabel('full_size')
            ->addFormat('undo_factor1000')
            ->setTitle('Full Size')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_full_size')
            ->setLabel('full_size_h')
            ->addFormat('undo_factor1000')
            ->addFormat('human_bytes')
            ->setTitle('Full Size')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_size')
            ->setLabel('size_h')
            ->addFormat('undo_factor1000')
            ->addFormat('human_bytes')
            ->setTitle('Size')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_size')
            ->setLabel('size')
            ->addFormat('undo_factor1000')
            ->setTitle('Size')
            ->flagBase1024(TRUE)
            ->addInvalidEnv('table')
            ->flagStacked();
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_idx_size')
            ->setLabel('idx_size_h')
            ->addFormat('undo_factor1000')
            ->addFormat('human_bytes')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->setTitle('Index Size')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_idx_size')
            ->setLabel('idx_size')
            ->addFormat('undo_factor1000')
            ->setTitle('Index Size')
            ->flagBase1024(TRUE)
            ->addInvalidEnv('table')
            ->flagStacked();
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_nb_rows')
            ->setLabel('rows_h')
            ->addFormat('undo_factor1000')
            ->addFormat('human_int')
            ->setStyle('text-align: right')
            ->setTitle('Nb Rows')
            ->flagInTooltip(TRUE)
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_nb_rows')
            ->setLabel('rows')
            ->addFormat('undo_factor1000')
            ->setTitle('Nb Rows')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('average_row_size')
            ->setLabel('avg_row_size_h')
            ->addFormat('3_dec')
            ->addFormat('human_bytes')
            ->flagBase1024(TRUE)
            ->setTitle('Average Row Size')
            ->setStyle('text-align: right')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('average_row_size')
            ->setLabel('avg_row_size')
            ->addFormat('3_dec')
            ->flagBase1024(TRUE)
            ->setTitle('Average Row Size')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_ugroup')
            ->setLabel('category')
            ->addFormat('check_plain')
            ->setTitle('Category')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_timestamp')
            ->setLabel('timestamp')
            ->addFormat('interval')
            ->setTitle('Last Check')
            ->setStyle('text-align: right');
        $formatter->addColumn( $col );

        return $formatter;
    }

    protected function _getTopTablesHistoryFormatter()
    {
        $formatter = new Formatter();

        $formatter->setGraphType('nBars')
            ->setGraphLeftAxis('y', t('Full Size'))
            ->setGraphBottomAxis('time', t('Time'));
        /*
        rs.prs_id,
        d.pdb_identifier, d.pdb_db_name,
         d.pdb_table,
        r.pr_timestamp, r.pr_value,r.pr_value_max,r.pr_value_min,
        r.pr_aggregate_level,r.pr_rrd_index
        */

        $col = new Column();
        $col->mapColumn('prs_id')
            ->addFormat('id')
            ->setTitle('id')
            ->setLabel('id');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->setMultipleMap( array(
                'type' => 'concat',
                'pdb_db_name' => 'field',
                ' (' => 'str',
                'pdb_identifier' => 'field',
                ')' => 'str'
            ))
            ->addFormat('check_plain')
            ->flagInTitle(TRUE)
            ->setTitle('Db')
            ->setLabel('db');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_table')
            ->addFormat('check_plain')
            ->flagInTitle(TRUE)
            ->setTitle('Db Table')
            ->setLabel('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pr_value')
            ->setLabel('y')
            ->addFormat('undo_factor1000')
            ->setTitle('Full Size')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pr_value')
            ->setLabel('full_size_h')
            ->addFormat('undo_factor1000')
            ->addFormat('human_bytes')
            ->setTitle('Full Size')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pr_value_max')
            ->setLabel('y1')
            ->addFormat('undo_factor1000')
            ->setTitle('Max Full Size')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pr_value_max')
            ->setLabel('full_size_max_h')
            ->addFormat('undo_factor1000')
            ->addFormat('human_bytes')
            ->setTitle('Max Full Size')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pr_value_min')
            ->setLabel('y0')
            ->addFormat('undo_factor1000')
            ->setTitle('Min Full Size')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pr_value_min')
            ->setLabel('full_size_min_h')
            ->addFormat('undo_factor1000')
            ->addFormat('human_bytes')
            ->setTitle('Min Full Size')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pr_timestamp')
            ->setLabel('time')
            ->setTitle('time');
        $formatter->addColumn( $col );

        $formatter->stackByValue('table');

        return $formatter;
    }

    protected function _getTablesFullFormatter()
    {
        $formatter = new Formatter();

        $formatter->setGraphType('TreeMap')
        // Nope... TODO, variation of main data source
             ->setGraphLeftAxis('full_size', t('Full Size'));

        $col = new Column();
        $col->mapColumn('pdb_id')
            ->setLabel('id')
            ->addFormat('id')
            ->setTitle('id');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->setMultipleMap( array(
                'type' => 'concat',
                'pdb_db_name' => 'field',
                ' (' => 'str',
                'pdb_identifier' => 'field',
                ')' => 'str'
            ))
            ->addFormat('check_plain')
            ->flagInTitle(TRUE)
            ->setTitle('Db')
            ->setLabel('db');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_ugroup')
            ->addFormat('check_plain')
            ->setLabel('category')
            ->setTitle('Category')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_table')
            ->addFormat('check_plain')
            ->flagInTitle(TRUE)
            ->setTitle('Db Table')
            ->setLabel('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_full_size')
            ->setLabel('full_size')
            ->addFormat('undo_factor1000')
            ->setTitle('Full Size')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_full_size')
            ->setLabel('full_size_h')
            ->addFormat('undo_factor1000')
            ->addFormat('human_bytes')
            ->setTitle('Full Size')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );


        $col = new Column();
        $col->mapColumn('average_row_size')
            ->setLabel('avg_row_size_h')
            ->addFormat('3_dec')
            ->addFormat('human_bytes')
            ->flagBase1024(TRUE)
            ->setTitle('Average Row Size')
            ->setStyle('text-align: right')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('average_row_size')
            ->setLabel('avg_row_size')
            ->addFormat('3_dec')
            ->flagBase1024(TRUE)
            ->setTitle('Average Row Size')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_timestamp')
            ->setLabel('timestamp')
            ->addFormat('interval')
            ->setTitle('Last Check')
            ->setStyle('text-align: right');
        $formatter->addColumn( $col );

        $formatter->stackByValue('category');

        return $formatter;
    }

    protected function _getTablesBrowserFormatter()
    {
        $formatter = new Formatter();

        $formatter->setGraphType('Tree');

        $col = new Column();
        $col->mapColumn('prs_id')
            ->addFormat('id')
            ->setTitle('id')
            ->setLabel('id');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->setMultipleMap( array(
                'type' => 'concat',
                'pdb_db_name' => 'field',
                ' (' => 'str',
                'pdb_identifier' => 'field',
                ')' => 'str'
            ))
            ->addFormat('check_plain')
            ->flagInTitle(TRUE)
            ->setTitle('Db')
            ->setLabel('db');
        $formatter->addColumn( $col );


        $col = new Column();
        $col->mapColumn('pdb_ugroup')
            ->addFormat('check_plain')
            ->setLabel('category')
            ->setTitle('Category')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_table')
            ->addFormat('check_plain')
            ->flagInTitle(TRUE)
            ->setTitle('Db Table')
            ->setLabel('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pr_timestamp')
            ->setLabel('time')
            ->setTitle('time');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_timestamp')
            ->addFormat('interval')
            ->setLabel('timestamp')
            ->setTitle('Last Check')
            ->setStyle('text-align: right');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_enable')
            ->addFormat('bool')
            ->setLabel('enabled')
            ->setTitle('Enabled');
        $formatter->addColumn( $col );

        return $formatter;
    }

    protected function _getDatabasesFormatter()
    {
        $formatter = new Formatter();

        $formatter->setGraphType('1Bar1Line')
            ->setGraphLeftAxis('nb_tables', t('Nb Tables'))
            ->setGraphRightAxis('rows', t('Nb Rows'))
            ->setGraphBottomAxis('db');

        $col = new Column();
        $col->mapColumn('pdb_id')
            ->addFormat('id')
            ->setTitle('id')
            ->setLabel('id');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->setMultipleMap( array(
                'type' => 'concat',
                'pdb_db_name' => 'field',
                ' (' => 'str',
                'pdb_identifier' => 'field',
                ')' => 'str'
            ))
            ->addFormat('check_plain')
            ->flagInTitle(TRUE)
            ->setTitle('Db')
            ->setLabel('db');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_full_size')
            ->addFormat('undo_factor1000')
            ->setTitle('Nb Tables')
            ->setLabel('nb_tables')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_full_size')
            ->addFormat('undo_factor1000')
            ->addFormat('human_int')
            ->setTitle('Nb Tables')
            ->setLabel('nb_tables_h')
            ->setStyle('text-align: right')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_nb_rows')
            ->addFormat('undo_factor1000')
            ->setLabel('rows')
            ->setTitle('Nb Rows')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_nb_rows')
            ->addFormat('undo_factor1000')
            ->addFormat('human_int')
            ->setLabel('rows_h')
            ->setStyle('text-align: right')
            ->setTitle('Nb Rows')
            ->flagInTooltip(TRUE)
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_ugroup')
            ->addFormat('check_plain')
            ->setLabel('category')
            ->setTitle('Category')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_timestamp')
            ->addFormat('interval')
            ->setLabel('timestamp')
            ->setTitle('Last Check')
            ->setStyle('text-align: right');
        $formatter->addColumn( $col );

        return $formatter;
    }

    public function getTopTablesDefinition()
    {
        $formatter = $this->_getTopTablesFormatter();

        return $formatter->renderDefinition();
    }

    public function getTopTablesHistoryDefinition()
    {
        $formatter = $this->_getTopTablesHistoryFormatter();

        return $formatter->renderDefinition();
    }

    public function getDatabasesDefinition()
    {
        $formatter = $this->_getDatabasesFormatter();

        return $formatter->renderDefinition();
    }

    public function getTablesFullDefinition()
    {
        $formatter = $this->_getTablesFullFormatter();

        return $formatter->renderDefinition();
    }

    public function getTablesBrowserDefinition()
    {
        $formatter = $this->_getTablesBrowserFormatter();

        return $formatter->renderDefinition();
    }

    /**
     * Return the top tables rows
     *
     * @param number $limit how many rows in the top list
     *
     * @param number $page page of result
     *
     * @param string $type 'full_size'/'nb_rows'/'idx_size'/'size'
     *
     * @param string $db 'all' or the database name
     */
    public function getTopTablesData( $limit=20, $page=1, $type='full_size', $db='all')
    {
        // some securities
        $limit = (int) $limit;
        $page = (int) $page;

        $query = db_select('prod_db_stats', 's')
            ->fields('s',array(
                'pdb_id',
                'pdb_identifier',
                'pdb_db_name',
                'pdb_table',
                'pdb_size',
                'pdb_idx_size',
                'pdb_full_size',
                'pdb_nb_rows',
                'pdb_seqscan_nb',
                'pdb_seqscan_rows',
                'pdb_idxscan_nb',
                'pdb_idxscan_rows',
                'pdb_inserts',
                'pdb_updates',
                'pdb_deletes',
                'pdb_last_autovaccuum',
                'pdb_last_autoanalyze',
                'pdb_nb_autovaccuum',
                'pdb_nb_autoanalyze',
                'pdb_timestamp',
                'pdb_ugroup'))
            ->condition('pdb_is_database', 0)
            ->condition('pdb_enable', 1);
        // this one does not return $this ...
        $query->addExpression('CASE WHEN (s.pdb_nb_rows>0) THEN s.pdb_full_size / s.pdb_nb_rows ELSE 0 END', 'average_row_size');

        if ( 'all' != $db ) {
            $query->condition('pdb_identifier', $db);
        }

        switch($type) {
            case 'full_size':
                $query->orderBy('pdb_full_size','DESC');
                break;
            case 'idx_size':
                $query->orderBy('pdb_idx_size','DESC');
                break;
            case 'size':
                $query->orderBy('pdb_size','DESC');
                break;
            case 'nb_rows':
                $query->orderBy('pdb_nb_rows','DESC');
                break;
            default:
                throw new ProdException('Unknown sort parameter used.');
        }

        $query->orderBy('pdb_identifier','ASC');
        $query->orderBy('pdb_db_name','ASC');
        $query->orderBy('pdb_table','ASC');

        $query->range(($page-1)*$limit,$limit);

        $result = $query->execute();

        $formatter = $this->_getTopTablesFormatter();

        $formatter->setData($result);

        return $formatter->render();

    }


    /**
     * Return the tables rows
     *
     * @param string $db 'all' or the database name
     */
    public function getTablesFullData($data='full_size', $db='all')
    {

        $query = db_select('prod_db_stats', 's')
            ->fields('s',array(
                'pdb_id',
                'pdb_identifier',
                'pdb_db_name',
                'pdb_table',
                'pdb_full_size',
                'pdb_timestamp',
                'pdb_ugroup'))
            ->condition('pdb_is_database', 0)
            // FIXME: UGLy HACK :-)
            //->condition('pdb_ugroup', 'Prod', '<>')
            ->condition('pdb_enable', 1);
        // this one does not return $this ...
        $query->addExpression('CASE WHEN (s.pdb_nb_rows>0) THEN s.pdb_full_size / s.pdb_nb_rows ELSE 0 END', 'average_row_size');

        if ( 'all' != $db ) {
            $query->condition('pdb_identifier', $db);
        }

        switch($data) {
            case 'full_size':
                $query->orderBy('pdb_full_size','DESC');
                break;
            case 'ratio':
                $query->orderBy('average_row_size','DESC');
                break;
            default:
                throw new ProdException('Unknown data parameter used.');
        }

        $query->orderBy('pdb_identifier','ASC');
        $query->orderBy('pdb_db_name','ASC');
        $query->orderBy('pdb_ugroup','ASC');
        $query->orderBy('pdb_table','ASC');

        $result = $query->execute();

        $formatter = $this->_getTablesFullFormatter();

        $formatter->setData($result);

        return $formatter->render();
    }

    /**
     * Return the tables rows
     *
     * @param string $db 'all' or the database name
     */
    public function getTablesBrowserData($db)
    {
        $query = db_select('prod_db_stats', 's')
            ->fields('s',array(
                'pdb_id',
                'pdb_identifier',
                'pdb_db_name',
                'pdb_table',
                'pdb_timestamp',
                'pdb_ugroup',
                'pdb_enable'))
            ->condition('pdb_is_database', 0);

        if ( 'all' != $db ) {
            $query->condition('pdb_identifier', $db);
        }


        $query->orderBy('pdb_identifier','ASC');
        $query->orderBy('pdb_db_name','ASC');
        $query->orderBy('pdb_ugroup','ASC');
        $query->orderBy('pdb_table','ASC');

        $result = $query->execute();

        $formatter = $this->_getTablesBrowserFormatter();

        $formatter->setData($result);

        return $formatter->render();
    }


    /**
     * Return the top tables rows historical data
     *
     * @param number $limit how many rows in the top list
     *
     * @param number $page page of result
     *
     * @param string $type 'full_size'/'nb_rows'/'idx_size'/'size'
     *
     * @param string $db 'all' or the database name
     *
     * @param int $level RRD level
     */
    public function getTopTablesHistoryData( $limit=20, $page=1, $type='full_size', $db='all', $level)
    {
        // some securities
        $limit = (int) $limit;
        $page = (int) $page;

        // Extract the top table ids
        $query = db_select('prod_db_stats', 's')
            ->fields('s',array(
                    'pdb_id',
                    /*'pdb_identifier',
                    'pdb_db_name',
                    'pdb_table'*/
                ))
                ->condition('pdb_is_database', 0)
                ->condition('pdb_enable', 1);

            if ( 'all' != $db ) {
                $query->condition('pdb_identifier', $db);
            }

            switch($type) {
                case 'full_size':
                    $query->orderBy('pdb_full_size','DESC');
                    break;
                case 'idx_size':
                    $query->orderBy('pdb_idx_size','DESC');
                    break;
                case 'size':
                    $query->orderBy('pdb_size','DESC');
                    break;
                case 'nb_rows':
                    $query->orderBy('pdb_nb_rows','DESC');
                    break;
                default:
                    throw new ProdException('Unknown sort parameter used.');
            }

            $query->orderBy('pdb_identifier','ASC');
            $query->orderBy('pdb_db_name','ASC');
            $query->orderBy('pdb_table','ASC');

            $query->range(($page-1)*$limit,$limit);

            $result = $query->execute();

            $tops_ids = array();
            foreach($result as $k => $record) {

                $tops_ids[] = $record->pdb_id;
            }

            if ( 0 === count($tops_ids)) {
                $data = array();
            } else {
                // Now extract historical data for theses ids
                $data=$this->_extractHistory($tops_ids, $level);
            }
            $formatter = $this->_getTopTablesHistoryFormatter();

            $formatter->setData($data);

            return $formatter->render();

        }

    protected function _extractHistory($ids, $level) {
        /*
        select rs.prs_id, d.pdb_identifier, d.pdb_db_name, d.pdb_table, rs.ptq_stat_tid, rs.prs_stat_pid, rs.prs_stat_col, r.pr_timestamp, r.pr_value,r.pr_value_max,r.pr_value_min, r.pr_aggregate_level,r.pr_rrd_index
    from prod_rrd_settings rs
        INNER JOIN prod_stats_task_queue pq ON pq.ptq_stat_tid=rs.ptq_stat_tid
        INNER JOIN prod_rrd r On r.prs_id=rs.prs_id
        INNER JOIn prod_db_stats d On d.pdb_id = rs.prs_stat_pid
    WHERE ptq_name='dbCollector'
    AND rs.prs_stat_col='full_size'
    AND rs.prs_stat_pid IN (152,136,22)
    AND r.pr_aggregate_level = 3
    ORDER BY rs.prs_id, r.pr_aggregate_level, r.pr_rrd_index
limit 50;
        */
        if (!is_array($ids) || 0===count($ids)) {
            return array();
        }
        $args =  array(
            ':level' => $level,
        );
        // no injection here, our list is only ints
        $id_list = implode( ",", $ids);
        $results = db_query("
            select rs.prs_id,
                   d.pdb_identifier, d.pdb_db_name, d.pdb_table,
                   r.pr_timestamp, r.pr_value,r.pr_value_max,r.pr_value_min,
                   r.pr_aggregate_level,r.pr_rrd_index
                FROM {prod_rrd_settings} rs
                  INNER JOIN {prod_stats_task_queue} pq ON pq.ptq_stat_tid=rs.ptq_stat_tid
                  INNER JOIN {prod_rrd} r On r.prs_id=rs.prs_id
                  INNER JOIN {prod_db_stats} d On d.pdb_id = rs.prs_stat_pid
                WHERE pq.ptq_name='dbCollector'
                  AND rs.prs_stat_col='full_size'
                  AND rs.prs_stat_pid IN ( ". $id_list ." )
                  AND r.pr_aggregate_level = :level
                ORDER BY rs.prs_id, r.pr_aggregate_level, r.pr_rrd_index DESC;
                ", $args);
        return $results;
    }


    public function getDatabasesData( )
    {
        $query = db_select('prod_db_stats', 's')
            ->fields('s',array(
                    'pdb_id',
                    'pdb_identifier',
                    'pdb_db_name',
                    'pdb_table',
                    'pdb_size',
                    'pdb_idx_size',
                    'pdb_full_size',
                    'pdb_nb_rows',
                    'pdb_timestamp',
                    'pdb_ugroup'))
                ->condition('pdb_is_database', 1)
                ->condition('pdb_enable', 1);
            // this one does not return $this ...
            $query->addExpression('s.pdb_full_size / s.pdb_nb_rows', 'average_row_size');

            $query->orderBy('pdb_identifier','ASC');
            $query->orderBy('pdb_db_name','ASC');

            $result = $query->execute();

            $formatter = $this->_getDatabasesFormatter();

            $formatter->setData($result);

            return $formatter->render();

        }
}
