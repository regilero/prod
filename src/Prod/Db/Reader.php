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
            ->addFormat('undo_factor100')
            ->setTitle('Full Size')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );
        
        $col = new Column();
        $col->mapColumn('pdb_full_size')
            ->setLabel('full_size_h')
            ->addFormat('undo_factor100')
            ->addFormat('human_bytes')
            ->setTitle('Full Size')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );
        
        $col = new Column();
        $col->mapColumn('pdb_size')
            ->setLabel('size_h')
            ->addFormat('undo_factor100')
            ->addFormat('human_bytes')
            ->setTitle('Size')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );
        
        $col = new Column();
        $col->mapColumn('pdb_size')
            ->setLabel('size')
            ->addFormat('undo_factor100')
            ->setTitle('Size')
            ->flagBase1024(TRUE)
            ->addInvalidEnv('table')
            ->flagStacked();
        $formatter->addColumn( $col );
        
        $col = new Column();
        $col->mapColumn('pdb_idx_size')
            ->setLabel('idx_size_h')
            ->addFormat('undo_factor100')
            ->addFormat('human_bytes')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->setTitle('Index Size')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );
        
        $col = new Column();
        $col->mapColumn('pdb_idx_size')
            ->setLabel('idx_size')
            ->addFormat('undo_factor100')
            ->setTitle('Index Size')
            ->flagBase1024(TRUE)
            ->addInvalidEnv('table')
            ->flagStacked();
        $formatter->addColumn( $col );
        
        $col = new Column();
        $col->mapColumn('pdb_nb_rows')
            ->setLabel('rows_h')
            ->addFormat('undo_factor100')
            ->addFormat('human_int')
            ->setStyle('text-align: right')
            ->setTitle('Nb Rows')
            ->flagInTooltip(TRUE)
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );
        
        $col = new Column();
        $col->mapColumn('pdb_nb_rows')
            ->setLabel('rows')
            ->addFormat('undo_factor100')
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
            ->setGraphLeftAxis('full_size', t('Full Size'))
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
            ->addFormat('undo_factor100')
            ->setTitle('Full Size')
            ->setLabel('full_size')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );
        
        $col = new Column();
        $col->mapColumn('pr_value')
            ->addFormat('undo_factor100')
            ->addFormat('human_bytes')
            ->setTitle('Full Size')
            ->setLabel('full_size_h')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pr_value_max')
            ->addFormat('undo_factor100')
            ->setTitle('Max Full Size')
            ->setLabel('full_size_max')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );
        
        $col = new Column();
        $col->mapColumn('pr_value_max')
            ->addFormat('undo_factor100')
            ->addFormat('human_bytes')
            ->setTitle('Max Full Size')
            ->setLabel('full_size_max_h')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pr_value_min')
            ->addFormat('undo_factor100')
            ->setTitle('Min Full Size')
            ->setLabel('full_size_min')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );
        
        $col = new Column();
        $col->mapColumn('pr_value_min')
            ->addFormat('undo_factor100')
            ->addFormat('human_bytes')
            ->setTitle('Min Full Size')
            ->setLabel('full_size_min_h')
            ->flagBase1024(TRUE)
            ->setStyle('text-align: right')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );
        
        $col = new Column();
        $col->mapColumn('pr_timestamp')
            ->setLabel('time')
            ->setTitle('time');
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
            ->addFormat('undo_factor100')
            ->setTitle('Nb Tables')
            ->setLabel('nb_tables')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );
    
        $col = new Column();
        $col->mapColumn('pdb_full_size')
            ->addFormat('undo_factor100')
            ->addFormat('human_int')
            ->setTitle('Nb Tables')
            ->setLabel('nb_tables_h')
            ->setStyle('text-align: right')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );
        
        $col = new Column();
        $col->mapColumn('pdb_nb_rows')
            ->addFormat('undo_factor100')
            ->setLabel('rows')
            ->setTitle('Nb Rows')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_nb_rows')
            ->addFormat('undo_factor100')
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
                'pdb_timestamp',
                'pdb_ugroup'))
            ->condition('pdb_is_database', 0)
            ->condition('pdb_enable', 1);
        // this one does not return $this ...
        $query->addExpression('s.pdb_full_size / s.pdb_nb_rows', 'average_row_size');

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
     * Return the top tables rows hsitorical data
     *
     * @param number $limit how many rows in the top list
     *
     * @param number $page page of result
     *
     * @param string $type 'full_size'/'nb_rows'/'idx_size'/'size'
     *
     * @param string $db 'all' or the database name
     */
    public function getTopTablesHistoryData( $limit=20, $page=1, $type='full_size', $db='all')
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
            $tops = array();
            foreach($result as $k => $record) {
                
                $tops_ids[] = $record->pdb_id;
                $tops = $record;
            }
            
            // Now extract historical data for theses ids
            $data=$this->_extractHistory($tops_ids, 3);
            
            $formatter = $this->_getTopTablesFormatter();
            
            $formatter->setData($data);
            
            return $formatter->render();
    
        }
    
    protected function _extractHistory($ids, $level) {
        /**
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
        **/
        if (!is_array($ids) || 0===count($ids)) {
            return array();
        }
        $query = db_query("
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
                  AND rs.prs_stat_pid IN (:id_list)
                  AND r.pr_aggregate_level = :level
                ORDER BY rs.prs_id, r.pr_aggregate_level, r.pr_rrd_index;
                ", array(
                    ':id_list' => implode( ",", $ids),
                    ':level' => $level,
                ));
        $results = $query->execute();
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
