<?php

namespace Drupal\Prod\Stats\Drupal;

use Drupal\Prod\Stats\StatsProviderInterface;
use Drupal\Prod\ProdObserverInterface;
use Drupal\Prod\ProdObservable;
use Drupal\Prod\ProdObject;
use Drupal\Prod\Monitoring\Cacti;
use Drupal\Prod\Stats\TaskInterface;
use Drupal\Prod\Stats\Task;
use Drupal\Prod\Stats\Queue;
use Drupal\Prod\Stats\Rrd\Manager;
use Drupal\Prod\Error\StatTaskException;
use Drupal\Prod\Error\DevelopperException;

/**
 */
class DrupalTask extends Task
{

    /**
     *
     * @var \Drupal\Prod\Stats\Drupal\DrupalTask object (for Singleton)
     */
    protected static $instance;

    // Stat provider id. This comes from Task
    protected $id;

    // Task informations
    // the module, here
    protected $task_module='Drupal\\Prod\\Stats\\Drupal\\DrupalTask';
    // running task collector function
    protected $task_name='collect';

    /**
     * Singleton implementation
     *
     * @return \Drupal\Prod\Stats\Drupal\DrupalTask
     */
    public static function getInstance()
    {

        if (!isset(self::$instance)) {

            self::$instance = new DrupalTask();
        }

        return self::$instance;
    }

    public function __construct()
    {
        return $this->initHelpers();
    }

    /**
     * Receive and handle a signal from an Observable object that we registered.
     *
     * @param ProdObservable $sender Sender of the signal (Obervable)
     *
     * @param int $signal one of the signal defined as consts in ProdObservable
     *
     * @param string $info optional information associated with the signal
     */
    public function signal(ProdObservable $sender, $signal, $info=null)
    {
        switch ($signal) {
            case ProdObservable::SIGNAL_MONITOR_SUMMARY:
              $this->_buildMonitorSummaryLines($sender);
              break;
            case ProdObservable::SIGNAL_STAT_TASK_INFO:
              $this->_registerTasksInQueue($sender);
              break;
        }

    }

    protected function _registerTasksInQueue(Queue $queue)
    {
        // We are just one task, so use simple add-me-to-the-queue mode
        // This will ensure we'll get a call for first and next scheduling
        // and also this will CREATE our Stat provider ID! which is the id
        // in the db queue.
        $this->flagEnabled(TRUE);
        $this->flagInternal(TRUE);
        $queue->queueTask($this);
    }

    protected function _buildMonitorSummaryLines(Cacti $sender)
    {

        $this->logger->log(__METHOD__, NULL, WATCHDOG_DEBUG);

        if ($this->_loadId()) {

            $stats = $this->getStatsList();

            foreach($stats as $key => $stat) {

                // That's a final end user input, apply formatter
                $line = $key . '=' . floor($stat->getValue()/1000);
                $sender->AddOutputLine($line);

            }

        } else {

            $this->logger->log($this->task_module . "; no metrics available yet", NULL, WATCHDOG_DEBUG);

        }
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



    public function manageRRD()
    {

        // RRD storage, now that all tables are at least recorded once
        if (variable_get('prod_stats_rrd_enabled', FALSE)) {

            // WARN: ManageRotations contains transaction management
            $rrd_manager = new Manager();
            $rrd_manager
              ->loadMultipleProviders($this->getId(), array($this) )
              ->manageRotations();

        }

    }
}
