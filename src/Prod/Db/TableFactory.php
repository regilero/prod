<?php

namespace Drupal\Prod\Db;

use Drupal\Prod\Error\DbAnalyzerException;

/**
 * Drupal\Prod\Db\TableFactory class
 */
class TableFactory
{

    /**
     *
     * @param string $driver database driver
     *
     * @param string $identifier Drupal's internal identifier for the database
     *
     * @param string $db_name The database name
     *
     * @param array $data Optionnal array of data, if present an init() call
     *                     is made with this data.
     *
     * @return TableInterface object
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public static function get($driver, $identifier, $db_name, $data) {
        
        try {

            $driver = ucFirst($driver);
            $className = 'Drupal\\Prod\\Db\\' . $driver . '\\Table';
            $table = new $className($identifier, $db_name);

            if ( !($table instanceof TableInterface)) {
                throw new DbAnalyzerException($className . ' is not an TableInterface object');
            }

            // If we have an array, it should contain a record extracted from the Analyzer->extractTables()
            // The driver specific Table should undertand that content.
            if (!empty($data)) {
                $table->init($data);
            }

            return $table;

        } catch (Exception $e) {
            throw new DbAnalyzerException(__METHOD__ . ': Failed to load TableInterface :'.$e->getMessage());
        }

    }

}
