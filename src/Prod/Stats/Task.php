<?php

namespace Drupal\Prod\Stats;

use Drupal\Prod\ProdConfigurable;
use Drupal\Prod\Stats\TaskInterface;

/**
 * Statistic Collector's Task
 */
class Task extends ProdConfigurable implements TaskInterface
{

    protected $id;
    protected $task_module;
    protected $task_name;
    protected $is_internal;
    protected $is_enable = TRUE;
    protected $timestamp;

    /**
     *
     * @var \Drupal\Prod\Stats\Task object (for Singleton)
     */
    protected static $instance;

    /**
     * Singleton implementation
     *
     * @return \Drupal\Prod\Stats\StatInterface
     */
    public static function getInstance()
    {

        if (!isset(self::$instance)) {

            self::$instance = new Task();
        }

        return self::$instance;
    }

    /**
     * Constructor
     * @return \Drupal\Prod\Stats\TaskInterface
     */
    public function __construct()
    {
        $this->initHelpers();
        $this->flagEnabled($this->config->get('enabled'));
        return $this;
    }

    /**
     * Get the Stat Task Unique Id
     *
     * @return int the stat task id
     *
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     *
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get the stat run scheduling timestamp
     *
     * @return int the UNIX timestamp
     *
     */
    public function getScheduling()
    {
        return $this->timestamp;
    }

    /**
     *
     * @param int $timestamp
     * @return \Drupal\Prod\Stats\TaskInterface
     */
    public function setScheduling($timestamp)
    {
        $this->timestamp = (int) $timestamp;
        return $this;
    }

    /**
     * Setter for enabled boolean
     *
     * @param boolean $bool
     * @return \Drupal\Prod\Stats\TaskInterface
     */
    public function flagEnabled($bool)
    {
        $this->is_enable = (int) $bool;
        return $this;
    }

    /**
     * Setter for is_internal boolean. This should be true only for objects
     * Using the PordObserver and TaskInterface patterns, non Internal objects
     * are instead using Drupal hooks to get called.
     *
     * @param boolean $bool
     * @return \Drupal\Prod\Stats\TaskInterface
     */
    public function flagInternal($bool)
    {
        $this->is_internal = (int) $bool;
        return $this;
    }

    /**
     *
     * @return boolean
     */
    public function isEnabled()
    {
        return (bool) $this->is_enable;
    }

    /**
     *
     * @return boolean
     */
    public function isInternal()
    {
        return (bool) $this->is_internal;
    }

    /**
     *
     * @return string
     */
    public function getTaskModule()
    {
        return $this->task_module;
    }

    /**
     *
     * @return string
     */
    public function getTaskName()
    {
        return $this->task_name;
    }
    public function setTaskModule($name)
    {
        $this->task_module = $name;
        return $this;
    }

    /**
     *
     * @param string $name
     * @return \Drupal\Prod\Stats\TaskInterface
     */
    public function setTaskName($name)
    {
        $this->task_name = $name;
        return $this;
    }

    /**
     * Is this record a new record -- no id yet -- ?
     * @return boolean
     */
    public function isNew() {
        return (is_null($this->id));
    }


    /**
     * Internally set the next scheduling time
     */
    public function scheduleNextRun()
    {
        if (is_null($this->timestamp)) {

            // new record, schedule right now
            $this->setScheduling(REQUEST_TIME);

        } else {

            $new = REQUEST_TIME + (int) variable_get('prod_default_rrd_interval', 300);

            $this->logger->log("Stat :module :method, rescheduling at :timestamp.", array(
                    ':module' => $this->task_module,
                    ':method' => $this->task_name,
                    ':timestamp' => $new
            ), WATCHDOG_DEBUG);

            $this->setScheduling( $new );

        }
    }

    /**
     * Run the task, this is our main goal in fact!
     */
    public function run()
    {
        if ($this->is_enable) {

            if ($this->is_internal) {

                $this->logger->log("Internal call on Stat :method.", array(
                        ':method' => $this->task_name
                ), WATCHDOG_DEBUG);

                call_user_func(array($this,$this->task_name));

            }
            else {

                // D7 hook system
                module_invoke(
                    $this->task_module,
                    'prod_stat_task_collect',
                    $this->task_name
                );

            }
        } else {
            $this->logger->log("Stat :module :: :name is disabled.", array(
                    ':module' => $task->getTaskModule(),
                    ':name' => $task->getTaskName()
            ), WATCHDOG_DEBUG);
        }

    }

    /**
     * Feed the internal id from the Queue table, if we can.
     * That is only of this task as run already.
     */
    protected function _loadId()
    {
        if (isset($this->id)) {
            return TRUE;
        }

        $query = db_select('prod_stats_task_queue', 'q');
        $query->fields('q', array(
                'ptq_stat_tid',
          ))
          ->condition('ptq_module', $this->getTaskModule())
          ->condition('ptq_name', $this->getTaskName());
        $results = $query->execute();

        foreach( $results as $result) {
            $this->id = $result->ptq_stat_tid;
        }

        return (!empty($this->id));
    }


    /**
     * return the list of StatsProviderInterface objects managed
     */
    /*public function getStatsProviders() {
        return array($this);
    }
*/
    public function getAdminForm() {
        $form = array();
        $form['enabled'] = array(
            '#type' => 'checkbox',
            '#title' => t('Enable'),
            '#default_value' => $this->config->get('enabled', TRUE),
            '#description' => t('Collector Enabled'),
        );
        return $form;
    }

    public function ValidateAdminForm($form, &$form_state, $section) {
    }

    public function SubmitAdminForm($form, &$form_state, $section) {
        $enabled = $form_state['values'][$section . '_enabled'];
        $this->flagEnabled($enabled);
        $this->config->set('enabled', $enabled);
    }
}
