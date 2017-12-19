<?php

namespace Drupal\Prod\Stats\Drupal\Mysql;

use Drupal\Prod\Stats\StatsProviderInterface;
use Drupal\Prod\ProdObserverInterface;
use Drupal\Prod\Stats\TaskInterface;
use Drupal\Prod\Stats\Drupal\UserInterface;
use Drupal\Prod\Stats\Drupal\AbstractUser;

/**
 */
class User extends AbstractUser implements UserInterface,TaskInterface, StatsProviderInterface, ProdObserverInterface
{
    /**
     *
     * @var \Drupal\Prod\Stats\Drupal\Mysql\User object (for Singleton)
     */
    protected static $instance;

    // Task informations
    // the module, here
    protected $task_module='Drupal\\Prod\\Stats\\Drupal\\Mysql\\User';
    // running task collector function
    protected $task_name='collect';

    /**
     * Singleton implementation
     *
     * @return \Drupal\Prod\Stats\User
     */
    public static function getInstance()
    {

        if (!isset(self::$instance)) {

            self::$instance = new User();
        }

        return self::$instance;
    }


    public function query_daily_connected_users(){
        $result = db_query("
            select count(*) as counter
            from {users}
            where status=1
            and (access > UNIX_TIMESTAMP(CURRENT_DATE()) )
        ");

        return $result;
    }

    public function query_monthly_connected_users(){
        $result = db_query("
            select count(*) as counter
            from {users}
            where status=1
            and (access > UNIX_TIMESTAMP(
                  CONCAT(
                      DATE_ADD(
                            LAST_DAY(
                               DATE_SUB( CURRENT_DATE(), INTERVAL 31 DAY )
                             )
                            , INTERVAL 1 DAY
                      ),
                      ' 00:00:00'
                  )
                ));
        ");

        return $result;
    }

}