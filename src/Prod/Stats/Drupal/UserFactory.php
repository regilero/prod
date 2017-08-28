<?php

namespace Drupal\Prod\Stats\Drupal;

use Drupal\Prod\Error\DbUserTaskException;
use Drupal\Prod\Stats\Drupal\UserInterface;

/**
 * Drupal\Prod\Stats\Drupal\UserFactory class
 */
class UserFactory
{

    /**
     *
     * @param array $db_arr Drupal database definition array
     *
     * @param string $identifier Drupal's internal identifier for this database
     *
     * @return UserInterface object
     *
     * @throws Drupal\Prod\Error\DbUserTaskException
     */
    public static function get($db_arr, $identifier) {

        try {

            if ( !(is_array($db_arr))
              || !(array_key_exists('default', $db_arr)) ) {
                throw new DbUserTaskException("No 'default' key in the database definition array");
            }
            if ( !(is_array($db_arr['default']))
              || !(array_key_exists('driver', $db_arr['default'])) ) {
                throw new DbUserTaskException("No 'driver' key in the default database definition array");
            }

            $driver = ucFirst($db_arr['default']['driver']);
            $className = 'Drupal\\Prod\\Stats\\Drupal\\' . $driver . '\\User';
            $user = $className::getInstance();

            if ( !($user instanceof UserInterface)) {
                throw new DbUserTaskException($className . ' is not an UserInterface object');
            }

            return $user;

        } catch (Exception $e) {
            throw new DbUserTaskException(__METHOD__ . ': Failed to load UserInterface :'.$e->getMessage());
        }

    }

}
