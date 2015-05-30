<?php

namespace Drupal\Prod\Log;

use Drupal\Prod\Error\ProdException;

/**
 * Log Factory
 */
class LogFactory
{

   /**
    * Internal storage of Singletons
    * (one per type)
    */
   private static $instances = array();

    /**
     * Factory getter.
     *
     * Return the Log Singleton
     * Detect the right logger based on execution env
     *
     * @return LogInterface
     */
    public static function get()
    {
        // Detect mode
        $driver = 'watchdog';
        if (function_exists('drush_main')) {
            $driver = 'drush';
        }

        $driver = ucFirst($driver);
        
        if (!array_key_exists($driver, self::$instances)) {

            $className = 'Drupal\\Prod\\Log\\' . $driver . 'Log';
            $logger = new $className($identifier, $db_name);
            if ( !($logger instanceof LogInterface)) {
                throw new ProdException($className . ' is not a LogInterface object');
            }
            self::$instances[$driver] = $logger;

        }
        return self::$instances[$driver];
    }

}
