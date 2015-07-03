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
                $otable = TableFactory::get( $this->getDbDriver(), $this->getDbIdentifier(), $this->getDbName(), $table );
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
        $query->addExpression('COUNT(*)', 'tablecount');

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
                    + variable_get('prod_default_rrd_interval', 300)
            );
        }
    }
}
