<?php

namespace Drupal\Prod\Stats\Drupal;

use Drupal\Prod\Stats\StatsProviderInterface;
use Drupal\Prod\ProdObserverInterface;
use Drupal\Prod\ProdObservable;
use Drupal\Prod\ProdObject;
use Drupal\Prod\Monitoring\Cacti;
use Drupal\Prod\Stats\TaskInterface;
use Drupal\Prod\Stats\Drupal\DrupalTask;
use Drupal\Prod\Stats\Queue;
use Drupal\Prod\Stats\Stat;
use Drupal\Prod\Error\StatTaskException;
use Drupal\Prod\Error\DevelopperException;

/**
 */
class User extends DrupalTask implements TaskInterface, StatsProviderInterface, ProdObserverInterface
{

    /**
     *
     * @var \Drupal\Prod\Stats\Drupal\User object (for Singleton)
     */
    protected static $instance;
    
    // Stat provider id. This comes from Task
    protected $id;
    
    // Task informations
    // the module, here
    protected $task_module='Drupal\\Prod\\Stats\\Drupal\\User';
    // running task collector function
    protected $task_name='collect';

    protected $total_users;
    protected $total_users_time = 0;
    protected $enabled_users;
    protected $enabled_users_time = 0;
    protected $active_users;
    protected $active_users_time = 0;
    protected $recent_connected;
    protected $recent_connected_time = 0;
    protected $day_connected;
    protected $day_connected_time = 0;
    protected $month_connected;
    protected $month_connected_time = 0;
    
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
    
    public function __construct()
    {
        return $this->initHelpers();
    }
    
    /**
     * Ensure all Helpers (log) are loaded into this object
     *
     */
    public function initHelpers()
    {
        
        parent::initHelpers();

        $this->logger->log(__METHOD__, NULL, WATCHDOG_DEBUG);
        
        // register in the Queue of StatsProviders
        $prodQueue = Queue::getInstance();
        $prodQueue->attach($this);
        
        // register our callback for Cacti Output
        $cactiObservable = Cacti::getInstance();
        $cactiObservable->attach($this);
        
        return $this;
    }
    
    /**
     * This is the function called when we need to recompute the stats
     */
    public function collect()
    {

        $this->logger->log(__METHOD__, NULL, WATCHDOG_DEBUG);

        $total = $active = $enabled = $five_min = $day = $month = 0;
        
        // count users by status
        $result = db_query("
            select status,count(*) as counter from {users} group by status
        ");
        foreach($result as $record) {
            
            if (0 == $record->status) {
                
              $total += $record->counter;
              
            } else {
                
              $enabled = $record->counter;
              $total += $enabled;
              
            }
        }
        $this->logger->log("User.total: " . $total, NULL, WATCHDOG_DEBUG);
        $this->logger->log("User.enabled: " . $enabled, NULL, WATCHDOG_DEBUG);
        $this->setTotalUsers($total * 1000);
        $this->setEnabledUSers($enabled * 1000);
        
        // count users having at least one connection
        $result = db_query("
            select count(*) as counter from {users} where status = 1 and access> 0
        ");
        foreach($result as $record) {
            $active =  $record->counter;
        }
        $this->logger->log("User.active: " . $active, NULL, WATCHDOG_DEBUG);
        $this->setActiveUsers($active * 1000);
        
        //connected with activity in the last 5 minutes
        $result = db_query("
            select count(*) as counter
            from {users}
            where status=1
            and ( access-(". REQUEST_TIME. "-300)) > 0
        ");
        foreach($result as $record) {
            $five_min = $record->counter;
        }
        $this->logger->log("User.conn.5_min: " . $five_min, NULL, WATCHDOG_DEBUG);
        $this->setRecentConnected($five_min * 1000);
        
        // connected today
        // TODO: mysql only?
        $result = db_query("
            select count(*) as counter
            from {users}
            where status=1
            and (access > UNIX_TIMESTAMP(CURRENT_DATE()) )
        ");
        foreach($result as $record) {
            $day = $record->counter;
        }
        $this->logger->log("User.conn.day: " . $day, NULL, WATCHDOG_DEBUG);
        $this->setDayConnected($day * 1000);
        
        // connected month
        // TODO: mysql only?
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
        foreach($result as $record) {
            $month = $record->counter;
        }
        $this->logger->log("User.conn.month: " . $month, NULL, WATCHDOG_DEBUG);
        $this->setMonthConnected($month * 1000);

        // Final save of all this data collected
        $this->save();

        $this->manageRRD();
    }
    


    protected function getTotalUsers()
    {
        if (!isset($this->total_users)) {
            throw new DevelopperException('Object is not loaded, cannot extract total_users stat');
        }
        
        return $this->total_users;
    }
    
    protected function setTotalUsers($nb)
    {
        $this->total_users = (int) $nb;
        return $this;
    }

    protected function getEnabledUSers()
    {
        if (!isset($this->enabled_users)) {
            throw new DevelopperException('Object is not loaded, cannot extract enabled_users stat');
        }
        
        return $this->enabled_users;
    }
    
    protected function setEnabledUSers($nb)
    {
        $this->enabled_users = (int) $nb;
        return $this;
        
    }
    
    protected function getActiveUsers()
    {
        if (!isset($this->active_users)) {
            throw new DevelopperException('Object is not loaded, cannot extract active_users stat');
        }
        
        return $this->active_users;
    }
    
    protected function setActiveUsers( $nb ) {

        $this->active_users = (int) $nb;
        return $this;
        
    }

    protected function getRecentConnected()
    {
        if (!isset($this->recent_connected)) {
            throw new DevelopperException('Object is not loaded, cannot extract online_users connected stat');
        }
    
        return $this->recent_connected;
    }
    
    protected function setRecentConnected( $nb ) {
    
        $this->recent_connected = (int) $nb;
        return $this;
    
    }

    protected function getMonthConnected()
    {
        if (!isset($this->month_connected)) {
            throw new DevelopperException('Object is not loaded, cannot extract month_active_users connected stat');
        }
    
        return $this->month_connected;
    }
    
    protected function setMonthConnected( $nb ) {
    
        $this->month_connected = (int) $nb;
        return $this;
    
    }
    
    protected function getDayConnected()
    {
        if (!isset($this->day_connected)) {
            throw new DevelopperException('Object is not loaded, cannot extract day_active_users connected stat');
        }
    
        return $this->day_connected;
    }
    
    protected function setDayConnected( $nb ) {
    
        $this->day_connected = (int) $nb;
        return $this;
    
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
     * Save (upsert) the stats records in prod_drupal_stats table.
     *
     * @return StatsProviderInterface
     *
     * @throws Drupal\Prod\Error\StatTaskException
     */
    public function save()
    {
        $this->logger->log('Saving StatProvider records for Users Stats ',NULL, WATCHDOG_DEBUG);
    
        // Upsert the record
        try {
            db_merge('prod_drupal_stats')
              -> key( array(
                  'ptq_stat_tid' => $this->getId(),
                  'pds_name' => 'user_total',
              ) )
              -> fields( array(
                  'pds_value' => $this->getTotalUsers(),
                  'pds_is_1024' => 0,
                  'pds_timestamp' => REQUEST_TIME,
                  'pds_enable' => 1,
              ) )
              ->execute();
            
            db_merge('prod_drupal_stats')
              -> key( array(
                  'ptq_stat_tid' => $this->getId(),
                  'pds_name' => 'user_enabled',
              ) )
              -> fields( array(
                  'pds_value' => $this->getEnabledUSers(),
                  'pds_is_1024' => 0,
                  'pds_timestamp' => REQUEST_TIME,
                  'pds_enable' => 1,
              ) )
              ->execute();
            
            db_merge('prod_drupal_stats')
              -> key( array(
                  'ptq_stat_tid' => $this->getId(),
                  'pds_name' => 'user_active',
              ) )
              -> fields( array(
                  'pds_value' => $this->getActiveUsers(),
                  'pds_is_1024' => 0,
                  'pds_timestamp' => REQUEST_TIME,
                  'pds_enable' => 1,
              ) )
              ->execute();

              db_merge('prod_drupal_stats')
                -> key( array(
                      'ptq_stat_tid' => $this->getId(),
                      'pds_name' => 'online_users',
                ) )
                -> fields( array(
                      'pds_value' => $this->getRecentConnected(),
                      'pds_is_1024' => 0,
                      'pds_timestamp' => REQUEST_TIME,
                      'pds_enable' => 1,
                ) )
                ->execute();

              db_merge('prod_drupal_stats')
                -> key( array(
                      'ptq_stat_tid' => $this->getId(),
                      'pds_name' => 'day_active_users',
                ) )
                -> fields( array(
                      'pds_value' => $this->getDayConnected(),
                      'pds_is_1024' => 0,
                      'pds_timestamp' => REQUEST_TIME,
                      'pds_enable' => 1,
                ) )
                ->execute();

              db_merge('prod_drupal_stats')
                -> key( array(
                      'ptq_stat_tid' => $this->getId(),
                      'pds_name' => 'month_active_users',
                ) )
                -> fields( array(
                      'pds_value' => $this->getMonthConnected(),
                      'pds_is_1024' => 0,
                      'pds_timestamp' => REQUEST_TIME,
                      'pds_enable' => 1,
                ) )
                ->execute();
                
        } catch (Exception $e) {
            throw new StatTaskException(__METHOD__ . ": Unable to save the Task Stat record. " . $e->getMessage());
        }
        
        return $this;
    
    }

    protected function _loadStats()
    {

        if (!isset($this->id)) {
            throw new DevelopperException('Cannot create/load stats elements before having a real Task id, maybe save this object before?');
        }
        
        // get the records
        try {
            $query = db_select('prod_drupal_stats', 's');
            $query->fields('s')
              ->condition('ptq_stat_tid',$this->getId());
            $result = $query->execute();
            
            foreach ($result as $res) {
                switch ($res->pds_name) {
                    case 'user_active':
                        $this->setActiveUsers((int) $res->pds_value);
                        $this->active_users_time = $res->pds_timestamp;
                        break;
                    case 'user_enabled':
                        $this->setEnabledUSers((int) $res->pds_value);
                        $this->enabled_users_time = $res->pds_timestamp;
                        break;
                    case 'user_total':
                        $this->setTotalUsers((int) $res->pds_value);
                        $this->total_users_time = $res->pds_timestamp;
                        break;
                    case 'online_users':
                        $this->setRecentConnected((int) $res->pds_value);
                        $this->recent_connected_time = $res->pds_timestamp;
                        break;
                    case 'day_active_users':
                        $this->setDayConnected((int) $res->pds_value);
                        $this->day_connected_time = $res->pds_timestamp;
                        break;
                    case 'month_active_users':
                        $this->setMonthConnected((int) $res->pds_value);
                        $this->month_connected_time = $res->pds_timestamp;
                        break;
                    default:
                        $this->logger->log(
                          'Unknown stat attached to User Stats collector: ' . $res->pds_name,
                          NULL,WATCHDOG_WARNING
                        );
                }
            }
        
        } catch (Exception $e) {
            throw new DbAnalyzerException(__METHOD__ . ": Unable to reload the user stats records. " . $e->getMessage());
        }
        
    }
    
    /**
     * Get the list of stats provided by this provider
     *
     * @return array List of StatsInterface, the array key
     *               is the stat_col.
     */
    public function getStatsList()
    {
        
        $this->_loadStats();
        
        $res = array();
        
        if ( 0 !== $this->total_users_time) {
            $res['user_total'] = $stat_user_total = new Stat(
              $this->getId(),
              'user_total',
              $this->getTotalUsers(),
              $this->total_users_time,
              'Total users'
            );
        }

        if ( 0 !== $this->total_users_time) {
            $res['user_enabled'] = new Stat(
              $this->getId(),
              'user_enabled',
              $this->getEnabledUSers(),
              $this->enabled_users_time,
              'Total enabled users'
            );
        }

        if ( 0 !== $this->total_users_time) {
            $res['user_active'] = new Stat(
              $this->getId(),
              'user_active',
              $this->getActiveUsers(),
              $this->active_users_time,
              'Total active users'
            );
        }

        if ( 0 !== $this->recent_connected_time ) {
            $res['online_users'] = new Stat(
                $this->getId(),
                'online_users',
                $this->getRecentConnected(),
                $this->recent_connected_time,
                'Recently connected users'
            );
        }
        
        if ( 0 !== $this->day_connected_time ) {
            $res['day_active_users'] = new Stat(
              $this->getId(),
              'day_active_users',
              $this->getDayConnected(),
              $this->day_connected_time,
              'Day connected users'
            );
        }
        
        if ( 0 !== $this->month_connected_time ) {
            $res['month_active_users'] = new Stat(
              $this->getId(),
              'month_active_users',
              $this->getMonthConnected(),
              $this->month_connected_time,
              'Month connected users'
            );
        }
    
        return $res;
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
        return self::getDefaultRrdSettingsUsers();
    }
    
    public static function getDefaultRrdSettingsUsers()
    {
        $defaults =& drupal_static(__METHOD__);
    
        if (!isset($defaults)) {
            // get generic defaults
            $defaults = array(
                'interval' => variable_get(
                'prod_default_rrd_interval',
                300
            ),
                'points_per_graph' => variable_get(
                'prod_default_rrd_points_per_graph',
                300
            ),
            'points_per_aggregate' => variable_get(
                'prod_default_rrd_points_per_aggregate',
                5
                ),
            );
            // check for users overrides
            $defaults['interval'] = variable_get(
                'prod_default_rrd_interval_users',
                 $defaults['interval']
            );
            $defaults['points_per_graph'] = variable_get(
                 'prod_default_rrd_points_per_graph_users',
                 $defaults['points_per_graph']
            );
            $defaults['points_per_aggregate'] = variable_get(
                   'prod_default_rrd_points_per_aggregate_users',
                   $defaults['points_per_aggregate']
            );
        }
        return $defaults;
    }
}
