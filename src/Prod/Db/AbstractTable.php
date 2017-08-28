<?php

namespace Drupal\Prod\Db;

use Drupal\Prod\Error\DbAnalyzerException;
use Drupal\Prod\Error\DevelopperException;
use Drupal\Prod\Stats\StatsProviderInterface;
use Drupal\Prod\Stats\Stat;
use Drupal\Prod\ProdObject;
use Drupal\Prod\Stats\Task;

/**
 * Common base implementation for most backends
 */
abstract class AbstractTable extends ProdObject implements TableInterface, StatsProviderInterface
{


    /**
     * Analysed database name
     *
     * @var string
     */
    private $db_name;

    /**
     * Drupal identifier for the database where we can find this table
     *
     * @var string
     */
    private $db_identifier;

    /**
     * name of the table
     *
     * @var string
     */
    private $table;

    /**
     * Size of the table
     *
     * @var int
     */
    private $size;

    /**
     * Size of the table's indexes
     *
     * @var int
     */
    private $index_size;

    /**
     * Size of the table's indexes+ size of table
     *
     * @var int
     */
    private $total_size;


    /**
     * Number of rows
     *
     * @var int
     */
    private $rows;

    /**
     * User group value
     *
     * @var string
     */
    private $user_group;

    /**
     * record id in prod_db_stats
     *
     * @var int
     */
    private $id;

    /**
     * record timestamp
     *
     * @var int
     */
    private $timestamp;


    /**
     * Flag, if TRUE this is not really a table but a fake aggregate
     * of all tables for this database
     *
     * @var boolean
     */
    private $is_database = FALSE;


    /**
     * constructor of TableInterface object
     *
     * @param string $db_identifier Drupal's internal identifier for this database
     *
     * @param string $db_name The table database
     *
     * @return TableInterface
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function __construct($identifier, $db_name)
    {
        $this->initHelpers();
        $this->setDbIdentifier($identifier)
          ->setDbName($db_name);
        return $this;
    }

    public function setDbName($name)
    {
        $this->db_name = $name;
        return $this;
    }

    public function getDbName()
    {
        if ( !isset($this->db_name)) {
            throw new DevelopperException('db_name is not set');
        }
        return $this->db_name;
    }


    public function setDbIdentifier($name)
    {
        $this->db_identifier = $name;
        return $this;
    }

    public function getDbIdentifier()
    {
        if ( !isset($this->db_identifier)) {
            throw new DevelopperException('db_identifier is not set yet');
        }
        return $this->db_identifier;
    }

    /**
     * Set the table name
     *
     * @param string $name The name
     *
     * @return TableInterface
     */
    public function setTable($name)
    {
        $this->table = $name;
        return $this;
    }

    /**
     * Get the table name
     *
     * @return string
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getTable()
    {
        if ( !isset($this->table)) {
            throw new DevelopperException('table is not set yet');
        }
        return $this->table;
    }

    /**
     * Set the table size and updates the total size
     *
     * @param int $size The size
     *
     * @return TableInterface
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function setSize($size)
    {
        $this->size = (int) $size;
        $this->total_size = (isset($this->index_size))? $this->size + $this->index_size : $this->size;
        return $this;
    }

    /**
     * Get the table size
     *
     * @return int
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getSize()
    {
        if ( !isset($this->size)) {
            throw new DevelopperException('size is not set yet');
        }
        return $this->size;
    }

    /**
     * Set the table index size and updates the total size
     *
     * @param int $size The size
     *
     * @return TableInterface
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function setIndexSize($size)
    {
        $this->index_size = (int) $size;
        $this->total_size = (isset($this->size))? $this->size + $this->index_size : $this->index_size;
        return $this;
    }

    /**
     * Get the table size
     *
     * @return int
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getIndexSize()
    {
        if ( !isset($this->index_size)) {
            throw new DevelopperException('index_size is not set yet');
        }
        return $this->index_size;
    }

    /**
     * Get the table+index size
     *
     * @return int
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getTotalSize()
    {
        if ( !isset($this->total_size)) {
            throw new DevelopperException('total_size is not set yet');
        }
        return $this->total_size;
    }

    /**
     * Set the number of rows for this table
     *
     * @param int $rows The number of rows
     *
     * @return TableInterface
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function setRows($rows)
    {
        $this->rows = (int) $rows;
        return $this;
    }

    /**
     * Get the number of rows for this table
     *
     * @return int
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getRows()
    {
        if ( !isset($this->rows)) {
            throw new DevelopperException('rows is not set yet');
        }
        return $this->rows;
    }

    /**
     * Set the user (human) group for this table
     *
     * @param int $group The group
     *
     * @return TableInterface
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function setUserGroup($group)
    {
        $this->user_group = $group;
        return $this;
    }

    /**
     * Get the user (human) group for this table
     *
     * @return string or NULL
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getUserGroup()
    {
        return $this->user_group;
    }

    /**
     * Set the is_database flag for this table.
     * TRUE if this table is in fact not a "table" but
     * instead an aggregate of tables.
     *
     * @param boolean $bool The flag value
     *
     * @return TableInterface
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function flagIsDatabase($bool)
    {
        $this->is_database = (bool) $bool;
        return $this;
    }

    /**
     * Get the is_database flag,
     * TRUE if this table is in fact not a "table" but
     * instead an aggregate of tables.
     *
     * @return boolean
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getIsDatabase()
    {
        if (!isset($this->is_database)) {
             $this->is_database = 0;
        }
        return ($this->is_database)? 1 : 0;
    }

    /**
     * Add this number of rows for this table
     *
     * @param int $rows The number of rows to add
     *
     * @return TableInterface
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function addRows($rows)
    {
        if (!isset($this->rows)) {
            $this->rows = 0;
        }
        $this->rows += (int) $rows;
        return $this;
    }

    /**
     * Add this size to the table size
     *
     * @param int $size The size
     *
     * @return TableInterface
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function addSize($size)
    {
        if (!isset($this->size)) {
            $this->size = 0;
        }
        $this->size += (int) $size;
        $this->total_size = (isset($this->index_size))? $this->index_size + $this->size : $this->size;
        return $this;
    }

    /**
     * Add this size to the table Index size
     *
     * @param int $size The size
     *
     * @return TableInterface
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function addIndexSize($size)
    {
        if (!isset($this->index_size)) {
            $this->index_size = 0;
        }
        $this->index_size += (int) $size;
        $this->total_size = (isset($this->size))? $this->index_size + $this->size : $this->index_size;
        return $this;
    }

    /**
     * Initialize the TableInterface object whith an array of data extracted
     * from the Analyzer extractTables method.
     * Note: This method is not always called by the Factory.
     *
     * @param array $data record extracted from the Analyzer extractTables
     *
     * @return TableInterface
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function init($data)
    {
        if (!is_array($data)
          || !array_key_exists('data_length', $data)) {
            throw new DbAnalyzerException('data_length key is not present in the given data entry');
        }
        if (!array_key_exists('index_length', $data)) {
            throw new DbAnalyzerException('index_length key is not present in the given data entry');
        }
        if (!array_key_exists('table_rows', $data)) {
            throw new DbAnalyzerException('table_rows key is not present in the given data entry');
        }
        if (!array_key_exists('table_name', $data)) {
            throw new DbAnalyzerException('table_name key is not present in the given data entry');
        }
        $this->setSize($data['data_length'])
            ->setIndexSize($data['index_length'])
            ->setRows($data['table_rows'])
            ->setTable($data['table_name']);
        return $this;
    }

    /**
     * Save (upsert) the table record in prod_db_stats table.
     * This function is also responsible for feeding the
     * object with the database id (setId())
     *
     * @return TableInterface
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function save()
    {
        $this->logger->log('Saving record for table ' .  $this->getTable(),NULL, WATCHDOG_DEBUG);

        // Upsert the record
        try {
            db_merge('prod_db_stats')
                -> key( array(
                    'pdb_identifier' => $this->getDbIdentifier(),
                    'pdb_db_name' => $this->getDbName(),
                    'pdb_table' => substr($this->getTable(),0,255),
                    'pdb_is_database' => $this->getIsDatabase()
                ) )
                -> fields( array(
                    'pdb_size' => $this->getSize() * 100,
                    'pdb_idx_size' => $this->getIndexSize() * 100,
                    'pdb_full_size' => $this->getTotalSize() * 100,
                    'pdb_nb_rows' => $this->getRows() * 100,
                    'pdb_timestamp' => $this->getTimestamp(),
                    // TODO: getter/setter when this will go to ui
                    'pdb_enable' => 1,
                ) )
                ->execute();
        } catch (Exception $e) {
            throw new DbAnalyzerException(__METHOD__ . ": Unable to save the table record. " . $e->getMessage());
        }

        // get the record id
        try {
            $result = db_select('prod_db_stats', 's')
                ->fields('s',array('pdb_id'))
                ->condition('pdb_identifier',$this->getDbIdentifier())
                ->condition('pdb_db_name',$this->getDbName())
                ->condition('pdb_table',substr($this->getTable(),0,255))
                ->condition('pdb_is_database',$this->getIsDatabase())
                ->execute();
            foreach ($result as $res) {
                $id = $res->pdb_id;
            }

            $this->setId($id);
            return $this->getId();

        } catch (Exception $e) {
            throw new DbAnalyzerException(__METHOD__ . ": Unable to reload the table record. " . $e->getMessage());
        }

    }

    /**
     * Set the table internal id (from prod_db_stats)
     *
     * @param int $id The id
     *
     * @return TableInterface
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get the table internal id
     *
     * @return int
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getId()
    {
        if ( !isset($this->id)) {
            throw new DevelopperException('id is not set yet');
        }
        return $this->id;
    }

    /**
     * Set the record timestamp
     *
     * @param int $timestamp The UNIX timestamp
     *
     * @return TableInterface
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = (int) $timestamp;
        return $this;
    }

    /**
     * Get the record timestamp
     *
     * @return int an UNIX timestamp
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getTimestamp()
    {
        if ( !isset($this->timestamp)) {
            $this->timestamp = REQUEST_TIME;
        }
        return $this->timestamp;
    }

    /**
     * Get the list of stats provided by this provider
     *
     * @return array List of StatInterface, the array key
     *               is the stat_col
     */
    public function getStatsList()
    {
        if (!isset($this->id)) {
            throw new DevelopperException('Cannot create stats elements before having a real record id, maybe save this object before?');
        }

        $stat_size = new Stat(
            $this->id,
            'size',
            $this->getSize(),
            $this->getTimestamp()
        );

        $stat_index_size = new Stat(
            $this->id,
            'idx_size',
            $this->getIndexSize(),
            $this->getTimestamp()
        );

        $stat_total_size = new Stat(
            $this->id,
            'full_size',
            $this->getTotalSize(),
            $this->getTimestamp()
        );
        $stat_rows = new Stat(
            $this->id,
            'nb_rows',
            $this->getRows(),
            $this->getTimestamp()
        );

        return array(
            'size'      => $stat_size,
            'idx_size'  => $stat_index_size,
            'full_size' => $stat_total_size,
            'nb_rows'   => $stat_rows,
        );
    }

    /**
     * Get the stats provider id
     *
     * @return int identifier of the stat provider, usually the first part of the Stat id
     */
    public function getStatsProviderId()
    {
        if (!isset($this->id)) {
            throw new DevelopperException('Cannot extract stat provider Id, maybe save this object before?');
        }
        return $this->id;
    }

    /**
     * Get the default RRD settings ('interval','points_per_graph','points_per_aggregate')
     * for this provider.
     *
     * @param string $stat_col Stat column identifier in this provider.
     *
     * @return array keyed by setting name
     */
    public function getDefaultRrdSettings($stat_col)
    {
        return self::getDefaultRrdSettingsDb();
    }

    public static function getDefaultRrdSettingsDb()
    {
        $defaults =& drupal_static(__METHOD__);

        if (!isset($defaults)) {
            // get generic defaults
            $defaults = array(
                    'interval' => (int) variable_get(
                        'prod_default_rrd_interval',
                        300
                    ),
                    'points_per_graph' => (int) variable_get(
                        'prod_default_rrd_points_per_graph',
                        300
                    ),
                    'points_per_aggregate' => (int) variable_get(
                        'prod_default_rrd_points_per_aggregate',
                        5
                    ),
            );
            // check for db overrides
            $defaults['interval'] = (int) variable_get(
                  'prod_default_rrd_interval_db',
                   $defaults['interval']
            );
            $defaults['points_per_graph'] = (int) variable_get(
                  'prod_default_rrd_points_per_graph_db',
                   $defaults['points_per_graph']
            );
            $defaults['points_per_aggregate'] = (int) variable_get(
                  'prod_default_rrd_points_per_aggregate_db',
                   $defaults['points_per_aggregate']
            );
        }
        return $defaults;
    }

}
