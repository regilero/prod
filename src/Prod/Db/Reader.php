<?php

namespace Drupal\Prod\Db;

use Drupal\Prod\Error\DevelopperException;
use Drupal\Prod\Output\Formatter;
use Drupal\Prod\Output\Column;
use Drupal\Prod\Drupal\Prod;

/**
 * Drupal\Prod\Db\Reader class
 * 
 * reader of stored statictics data concerning the database.
 */
class Reader
{

    /**
     *
     * @var \Drupal\Prod\Db\Reader object (for Singleton)
     */
    protected static $instance;
    

    /**
     * Singleton implementation
     *
     * @return \Drupal\Prod\Db\Reader
     */
    public static function getInstance()
    {
    
        if (!isset(self::$instance)) {
    
            self::$instance = new Reader();
        }
    
        return self::$instance;
    }

    /**
     * Return the top tables rows
     * 
     * @param number $limit how many rows in the top list
     * 
     * @param string $type 'fullsize' or 'rows'
     * 
     * @param string $db 'all' or the database name
     * 
     * @param string $format 'table' or 'json'
     */
    public function getTopTables( $limit=20, $type='fullsize', $db='all', $format='table')
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
            ->condition('pdb_is_database', 0)
            ->condition('pdb_enable', 1);
        // this one does not return $this ...
        $query->addExpression('s.pdb_full_size / s.pdb_nb_rows', 'average_row_size');

        if ( 'all' != $db ) {
            $query->condition('pdb_identifier', $db);
        }
        
        if ( 'fullsize' == $type ) {
            $query->orderBy('pdb_full_size','DESC');
        } else {
            $query->orderBy('pdb_nb_rows','DESC');
        }
        
        $query->orderBy('pdb_identifier','ASC');
        $query->orderBy('pdb_db_name','ASC');
        $query->orderBy('pdb_table','ASC');

        $query->range(0,$limit);
        
        $result = $query->execute();
        
        $formatter = new Formatter();
        //$formatter->setKey('pdb_id');

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
                )
            )
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
        $col->mapColumn('pdb_full_size')
            ->addFormat('undo_factor100')
            ->addFormat('human_bytes', 'table')
            ->setTitle('Full Size')
            ->setLabel('full_size')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_full_size')
            ->addFormat('undo_factor100')
            ->addFormat('human_bytes')
            ->setTitle('Full Size')
            ->setLabel('full_size_h')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );
        
        $col = new Column();
        $col->mapColumn('pdb_size')
            ->addFormat('undo_factor100')
            ->addFormat('human_bytes')
            ->setTitle('Size')
            ->setLabel('size_h')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );
        
        $col = new Column();
        $col->mapColumn('pdb_size')
            ->addFormat('undo_factor100')
            ->setTitle('Size')
            ->setLabel('size')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );
        
        $col = new Column();
        $col->mapColumn('pdb_idx_size')
            ->addFormat('undo_factor100')
            ->addFormat('human_bytes')
            ->setLabel('idx_size_h')
            ->setTitle('Index Size')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_idx_size')
            ->addFormat('undo_factor100')
            ->setLabel('idx_size')
            ->setTitle('Index Size')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('pdb_nb_rows')
        ->addFormat('undo_factor100')
        ->addFormat('human_int')
        ->setLabel('rows_h')
        ->setTitle('Nb Rows')
        ->flagInTooltip(TRUE)
        ->addInvalidEnv('table');
        $formatter->addColumn( $col );
        
        $col = new Column();
        $col->mapColumn('pdb_nb_rows')
            ->addFormat('undo_factor100')
            ->setLabel('rows')
            ->setTitle('Nb Rows')
            ->addInvalidEnv('table');
        $formatter->addColumn( $col );

        $col = new Column();
        $col->mapColumn('average_row_size')
            ->addFormat('3_dec')
            ->addFormat('human_bytes')
            ->setLabel('avg_row_size_h')
            ->setTitle('Average Row Size')
            ->flagInTooltip(TRUE);
        $formatter->addColumn( $col );
        
        $col = new Column();
        $col->mapColumn('average_row_size')
            ->addFormat('3_dec')
            ->setLabel('avg_row_size')
            ->setTitle('Average Row Size')
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
            ->setTitle('Last Check');
        $formatter->addColumn( $col );

        $formatter->setData($result);
        
        return $formatter->render($format);

    }
    
}
