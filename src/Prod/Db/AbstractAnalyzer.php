<?php

namespace Drupal\Prod\Db;

use Drupal\Prod\ProdObject;
use Drupal\Prod\Error\DevelopperException;
use Drupal\Prod\Error\DbAnalyzerException;
use Drupal\Prod\Stats\TaskInterface;

/**
 * Common base implementation for most backends
 */
abstract class AbstractAnalyzer extends ProdObject implements AnalyzerInterface
{

    /**
     * Analysed database name
     *
     * @var string
     */
    private $db_name;

    /**
     * prefix used on all the Drupal tables for this database
     *
     * @var string
     */
    private $db_prefix;

    /**
     * Drupal identifier for this database
     *
     * @var string
     */
    private $db_id;

    /**
     * Drupal driver for this database
     *
     * @var string
     */
    private $db_driver;

    /**
     * List of tables extracted from this database
     *
     * @var array
     */
    private $tables;

    public function __construct()
    {
        // Initialize helpers (like $this->logger)
        $this->initHelpers();
        return $this;
    }

    public function setDbName($name)
    {
        $this->db_name = $name;
        return $this;
    }

    public function getDbName() {
        if ( !isset($this->db_name)) {
            throw new DevelopperException('db_name is not set');
        }
        return $this->db_name;
    }

    public function setDbIdentifier($name)
    {
        $this->db_id = $name;
        return $this;
    }

    public function getDbIdentifier() {
        if ( !isset($this->db_id)) {
            throw new DevelopperException('db_id is not set yet');
        }
        return $this->db_id;
    }

    public function setDbPrefix($name)
    {
        $this->db_prefix = $name;
        return $this;
    }

    public function getDbPrefix() {
        if ( !isset($this->db_prefix)) {
            throw new DevelopperException('db_prefix is not set yet');
        }
        return $this->db_prefix;
    }

    public function setDbDriver($name)
    {
        $this->db_driver = $name;
        return $this;
    }

    public function getDbDriver() {
        if ( !isset($this->db_driver)) {
            throw new DevelopperException('db_driver is not set yet');
        }
        return $this->db_driver;
    }

    public function setTables($tables)
    {
        if (!is_array($tables) && (! $tables instanceOf Iterable) ) {
            throw new DbAnalyzerException(__METHOD__ . ': given table set is not an array');
        }

        $list = array();
        foreach($tables as $table) {

            if (is_array($table)) {

                // need to transform this in Drupal\Prod\Db\TableInterface object
                $otable = TableFactory::get(
                    $this->getDbDriver(),
                    $this->getDbIdentifier(),
                    $this->getDbName(),
                    $table
                );
                $list[] = $otable;

            } else {
                if (! $table instanceOf TableInterface) {
                  throw new DbAnalyzerException(__METHOD__ . ": received something which is not an array and not a TableInterface, don't know what to do with that.");
                }
                $list[] = $table;

            }

        }
        $this->tables = $list;
        return $this;
    }

    public function getTables() {
        if ( !isset($this->tables) || !is_array($this->tables) ) {
            throw new DevelopperException('tables list is not set yet');
        }

        return $this->tables;
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

        $query = $this->_getTablesInformationsQuery();

        $args = array(':db_name' => $this->getDbName());

        if (($limit !== 0) && $tables_list) {

            $query .= $this->_getQueryFilterTableList();
            $args[':list'] = $tables_list;

        }

        // On databases using some prefix we need to filter out the results
        if (!empty($this->getDbPrefix())) {
            $query .= $this->_getQueryFilterTableExpr();
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


    /**
     * Extract the least recently analysed tables for the current
     * database identifier. Note that this function does not
     * cover the new tables.
     *
     * @param int $limit The max number of records to handle
     *
     * @return a list (array) of table names
     */
    protected function _getOldestTables($limit)
    {
        $query = db_select('prod_db_stats', 's')
            ->fields('s', array('pdb_table'))
            ->condition('pdb_identifier', $this->getDbIdentifier())
            ->condition('pdb_db_name', $this->getDbName())
            ->condition('pdb_is_database', 0)
            ->condition('pdb_enable', 1)
            ->orderBy('pdb_timestamp', 'ASC')
            ->orderBy('pdb_db_name', 'ASC')
            ->range(0,$limit);

        $prefix = $this->getDbPrefix();
        if (!empty($prefix)) {
            $query->condition('pdb_table', db_like($prefix) . '%' , 'LIKE');
        }

        $results = $query->execute();

        $list = array();
        foreach($results as $result) {
            $list[] = $result->pdb_table;
        }

        return $list;

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
            ->expression('pdb_ugroup', $this->_getTablesGroupExpression())
            ->isNull('pdb_ugroup')
            ->execute();

        return $this;

    }


    /**
     * Add or update a db record containing summ of tables data.
     *
     * @return TableInterface object (the created or updated record)
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function ManageDbRecord()
    {

        $this->logger->log('Adding Database Record For Database ' . $this->getDbName(), NULL, WATCHDOG_DEBUG);

        $sumTable = TableFactory::get(
          $this->getDbDriver(),
          $this->getDbIdentifier(),
          $this->getDbName(),
          NULL
        )
          ->setTable($this->getDbName())
          ->flagIsDatabase(TRUE);

        $query = db_select('prod_db_stats', 's')
            //->fields('s', array())
            ->condition('pdb_identifier', $this->getDbIdentifier())
            ->condition('pdb_db_name', $this->getDbName())
            ->condition('pdb_is_database', 0);
            // no filter on enable/disable
            //->condition('pdb_enable', 1)

        $prefix = $this->getDbPrefix();
        if (!empty($prefix)) {
            $query->condition('pdb_table', db_like($prefix) . '%' , 'LIKE');
        }

        $query->addExpression('SUM(s.' . $prefix . 'pdb_nb_rows)', 'rowsum');
        $query->addExpression('COUNT(*) * 1000', 'tablecount');

        $results = $query->execute();
        foreach($results as $result) {
           $sumTable->setRows($result->rowsum);
           $sumTable->setSize($result->tablecount);
           $sumTable->setIndexSize(0);
        }

        /*
        foreach ($this->getTables() as $table) {

            $sumTable->addRows($table->getRows());
            $sumTable->addSize($table->getSize());
            $sumTable->addIndexSize($table->getIndexSize());

        }
        */
        //var_dump($sumTable); die('hard');
        $sumTable->save();

        return $sumTable;

    }

    /**
     * Internally set the next scheduling time
     */
    public function scheduleNextRun()
    {
        if (is_null($this->timestamp)) {

            // new record, schedule right now
            $this->setScheduling(REQUEST_TIME);

        } else {
            $this->setScheduling(
                    REQUEST_TIME
                    + (int) variable_get('prod_default_rrd_interval', 300)
            );
        }
    }
}
