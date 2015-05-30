<?php

namespace Drupal\Prod\Db;

use Drupal\Prod\Error\DbAnalyzerException;

/**
 * DB Analyzer
 */
interface AnalyzerInterface
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
    public function init($db_arr, $identifier);

    /**
     * Return an iterable collection of TableInterface objects
     *
     * @param int $limit: max number of records to manage
     *
     * @return array|Traversable List of tables
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function extractTables($limit);

    /**
     * Add or update a db record containing summ of tables data.
     *
     * @return TableInterface object (the created or updated record)
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function ManageDbRecord();

    /**
     * Try to guess some good human readable groups for all contained tables.
     *
     * @return AnalyzerInterface
     * 
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public function setDefaultUserGroup();

}
