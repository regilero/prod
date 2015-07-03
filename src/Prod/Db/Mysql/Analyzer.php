<?php

namespace Drupal\Prod\Db\Mysql;

use Drupal\Prod\Error\DbAnalyzerException;
use Drupal\Prod\Error\DevelopperException;
use Drupal\Prod\Db\AbstractAnalyzer;
use Drupal\Prod\Db\AnalyzerInterface;

/**
 * MySQL Analyzer
 */
class Analyzer extends AbstractAnalyzer implements AnalyzerInterface
{

    /**
     * Initialize the AnalyzerInterface object
     *
     * @param array $db_arr Drupal database definition array
     *
     * @param string $identifier Drupal's internal identifier for this database
     *
     * @return AnalyzerInterface
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function init($db_arr, $identifier)
    {

        $this->setDbDriver('mysql');

        $this->setDbIdentifier($identifier);
        
        if (!is_array($db_arr) 
          || !array_key_exists('database', $db_arr)) {
            throw new DbAnalyzerException('database key is not present in given database definition');
        }

        $this->setDbName($db_arr['database']);

        if (array_key_exists('prefix', $db_arr) 
          && !empty($db_arr['prefix'])) {

            $this->setDbPrefix($db_arr['prefix']);

        } else {

            $this->setDbPrefix('');

        }

        return $this;
    }

    /**
     * Return an iterable collection of TableInterface objects
     *
     * @param int $limit: max number of records to manage
     *
     * @return array|Traversable List of tables
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function extractTables($limit)
    {
        if ($limit !== 0) {

          // First get the list of tables to extract
          // which contains 2 pieces:
          //  1 - The new tables 
          //  2 - the oldest analyzed tables

          $tables_list = $this->_getUntrackedTables($limit);
          $nb = count($tables_list);
          $this->logger->log(
              'Extracting tables, found ' . $nb . ' new tables',
               NULL, WATCHDOG_DEBUG );

          if ( $nb < $limit ) {

              if (0==$nb) $tables_list = array();

              $updates = $this->_getOldestTables( $limit - count($tables_list) );
              $this->logger->log(
                'Extracting tables, found '. count($updates) . ' existing tables needing updates',
                NULL, WATCHDOG_DEBUG );
              $tables_list = array_merge( $tables_list, $updates );

              if (0==count($tables_list)) {
                 $this->logger->log(
                   'Nothing to extract.',
                   NULL, WATCHDOG_DEBUG );
                return array();
              }
          }

        } else {
            $this->logger->log('Extracting All tables',NULL,WATCHDOG_DEBUG);
        }

        $query = " 
            SELECT table_name,
                   table_rows,
                   data_length,  
                   index_length
            FROM information_schema.TABLES
            WHERE table_schema=:db_name
        ";
        
        $args = array(':db_name' => $this->getDbName());

        if (($limit !== 0) && $tables_list) {

            $query .= " AND table_name IN (:list)";
            $args[':list'] = $tables_list;

        }

        // On databases using some prefix we need to filter out the results
        if (!empty($this->getDbPrefix())) {
            $query .= "AND table_name like ':prefix%'";
            $args[':prefix'] = $this->getDbPrefix();
        }

        // by default we'll try to use the slave connection, but if you do not
        // use the same db name or the same prefix as in the master/default you
        // should suspend this setting
        $options = array(
            'target' => variable_get('prod_db_stats_indexer_use_slave',TRUE)? 'slave': 'default',
            'fetch' => \PDO::FETCH_ASSOC,
        );

        $result = db_query( $query, $args, $options );

        $tables = $result->fetchAll();
         $this->logger->log(
           'Extracting stats for :nb tables',
           array(':nb' => count($tables)),
           WATCHDOG_INFO
         );
        
        // this will compute the data+idx length
        $this->setTables($tables);

        return $this->getTables();

    }

    protected function _getUntrackedTables($limit)
    {

        $query = " 
            SELECT table_name
            FROM information_schema.TABLES t
            LEFT JOIN {prod_db_stats} s 
              ON (s.pdb_identifier = :identifier
                AND s.pdb_db_name = t.table_schema
                AND s.pdb_table = t.table_name)
            WHERE t.table_schema = :db_name
            AND s.pdb_db_name IS NULL
        ";
        $args = array(
            ':db_name' => $this->getDbName(),
            ':identifier' => $this->getDbIdentifier(),
        );

        // On databases using some prefix we need to filter out the results
        if (!empty($this->getDbPrefix())) {
            $query .= "AND t.table_name like ':prefix%' \n";
            $args[':prefix'] = $this->getDbPrefix();
        }

        $query .= " LIMIT " . (int) $limit;

        // by default we'll try to use the slave connection, but if you do not
        // use the same db name or the same prefix as in the master/default you
        // should suspend this setting
        $options = array(
            'target' => variable_get('prod_db_stats_indexer_use_slave',TRUE)? 'slave': 'default',
            'fetch' => \PDO::FETCH_ASSOC,
        );
        $result = db_query( $query, $args, $options );

        $tables = array();
        foreach ($result->fetchAll() as $result) {
            $tables[] = $result['table_name'];
        }

        return $tables;

    }

    /**
     * Try to guess some good human readable groups for all contained tables.
     *
     * @return AnalyzerInterface
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function setDefaultUserGroup()
    {
        
        db_update('prod_db_stats')
            ->fields(array(
                'pdb_ugroup' => 'Fields',
            ))
            ->isNull('pdb_ugroup')
            ->condition('pdb_identifier',$this->getDbIdentifier(),'=')
            ->condition('pdb_db_name',$this->getDbName(),'=')
            ->condition('pdb_table',db_like('field_').'%','LIKE')
            ->execute();

        db_update('prod_db_stats')
            ->fields(array(
                'pdb_ugroup' => 'Core',
            ))
            ->isNull('pdb_ugroup')
            ->condition('pdb_identifier',$this->getDbIdentifier(),'=')
            ->condition('pdb_db_name',$this->getDbName(),'=')
            ->condition('pdb_table',array(
                'variable',
                'users',
                'comment',
                'profile',
                'watchdog',
                'system',
                'history',
                'registry',
                'block',
                'sessions',
                'role',
                'semaphore',
                'queue',
                'batch',
                'languages',
                'sequences',
            ),'IN')
            ->execute();

        // TODO: This will not work with db prefix...
        db_update('prod_db_stats')
            ->expression(
                'pdb_ugroup' , "
                  CASE
                    WHEN  LOCATE('_',pdb_table) > 0
                    THEN 
                        CONCAT( UCASE( LEFT(pdb_table, 1)), SUBSTRING(pdb_table, 2, LOCATE('_',pdb_table) -2) )
                    ELSE 
                        CONCAT( UCASE(LEFT(pdb_table, 1)), LCASE(SUBSTRING(pdb_table,2,7)) )
                  END
                ")   
            ->isNull('pdb_ugroup')
            ->execute();

        return $this;

    }

}
