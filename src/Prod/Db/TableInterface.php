<?php

namespace Drupal\Prod\Db;

use Drupal\Prod\Error\DbAnalyzerException;

/**
 * DB Analyzer Table
 */
interface TableInterface
{
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
    public function __construct($db_identifier, $db_name);

    public function setDbName($name);

    public function getDbName();

    public function setDbIdentifier($name);

    public function getDbIdentifier();

    /**
     * Set the table name 
     *
     * @param string $name The name
     *
     * @return TableInterface
     */
    public function setTable($name);

    /**
     * Get the table name
     *
     * @return string
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getTable();

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
    public function init($data);

    /**
     * Set the table size and updates the total size 
     *
     * @param int $size The size
     *
     * @return TableInterface
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function setSize($size);

    /**
     * Get the table size
     *
     * @return int
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getSize();
    
    /**
     * Set the table index size and updates the total size
     *
     * @param int $size The size
     *
     * @return TableInterface
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function setIndexSize($size);

    /**
     * Get the table size
     *
     * @return int
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getIndexSize();
    
    /**
     * Get the table+index size
     *
     * @return int
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getTotalSize();

    /**
     * Set the number of rows for this table
     *
     * @param int $rows The number of rows
     *
     * @return TableInterface
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function setRows($rows);

    /**
     * Get the number of rows for this table
     *
     * @return int
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getRows();
    
    /**
     * Set the user (human) group for this table
     *
     * @param int $group The group
     *
     * @return TableInterface
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function setUserGroup($group);

    /**
     * Get the user (human) group for this table
     *
     * @return string or NULL
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getUserGroup();

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
    public function flagIsDatabase($bool);

    /**
     * Get the is_database flag,
     * TRUE if this table is in fact not a "table" but 
     * instead an aggregate of tables.
     *
     * @return boolean
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getIsDatabase();

    /**
     * Add this number of rows for this table
     *
     * @param int $rows The number of rows to add
     *
     * @return TableInterface
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function addRows($rows);
    /**
     * Add this size to the table size
     *
     * @param int $size The size
     *
     * @return TableInterface
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function addSize($size);

    /**
     * Add this size to the table Index size
     *
     * @param int $size The size
     *
     * @return TableInterface
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function addIndexSize($size);
    
    /**
     * Save (upsert) the table record in prod_db_stats table.
     * This function is also responsible for feeding the
     * object with the database id (setId())
     *
     * @param int $size The size
     *
     * @return TableInterface
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function save();

    /**
     * Set the table internal id (from prod_db_stats)
     *
     * @param int $id The id
     *
     * @return TableInterface
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function setId($id);

    /**
     * Get the table internal id
     *
     * @return int
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getId();

    /**
     * Set the record timestamp 
     *
     * @param int $timestamp The UNIX timestamp
     *
     * @return TableInterface
     */
    public function setTimestamp($timestamp);

    /**
     * Get the record timestamp
     *
     * @return int an UNIX timestamp
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function getTimestamp();

}
