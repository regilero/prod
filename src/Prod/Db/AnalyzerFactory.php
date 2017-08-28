<?php

namespace Drupal\Prod\Db;

use Drupal\Prod\Error\DbAnalyzerException;

/**
 * Drupal\Prod\Db\AnalyzerFactory class
 */
class AnalyzerFactory
{

    /**
     *
     * @param array $db_arr Drupal database definition array
     *
     * @param string $identifier Drupal's internal identifier for this database
     *
     * @return AnalyzerInterface object
     *
     * @throws Drupal\Prod\Error\DbAnalyzerException
     */
    public static function get($db_arr, $identifier) {

        try {

            if ( !(is_array($db_arr))
              || !(array_key_exists('default', $db_arr)) ) {
                throw new DbAnalyzerException("No 'default' key in the database definition array");
            }
            if ( !(is_array($db_arr['default']))
              || !(array_key_exists('driver', $db_arr['default'])) ) {
                throw new DbAnalyzerException("No 'driver' key in the default database definition array");
            }

            $driver = ucFirst($db_arr['default']['driver']);
            $className = 'Drupal\\Prod\\Db\\' . $driver . '\\Analyzer';
            $analyzer = new $className();

            if ( !($analyzer instanceof AnalyzerInterface)) {
                throw new DbAnalyzerException($className . ' is not an AnalyzerInterface object');
            }

            $analyzer->init($db_arr['default'],$identifier);
            return $analyzer;

        } catch (Exception $e) {
            throw new DbAnalyzerException(__METHOD__ . ': Failed to load AnalyzerInterface :'.$e->getMessage());
        }

    }

}
