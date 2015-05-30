<?php

namespace Drupal\Prod\Log;

/**
 * Log interface
 */
interface LogInterface
{
    /**
     * Log a message,
     * but output it only if prod_log_level is greater than provided level.
     *
     * @param string $message The message
     *
     * @param array $variables variables to replace in message
     *
     * @param string $level The message level, we use the WATCHDOG_* severity constants
     *                      WATCHDOG_EMERGENCY
     *                      WATCHDOG_ALERT
     *                      WATCHDOG_CRITICAL
     *                      WATCHDOG_ERROR
     *                      WATCHDOG_WARNING
     *                      WATCHDOG_NOTICE
     *                      WATCHDOG_INFO
     *                      WATCHDOG_DEBUG
     *
     * @return TableInterface
     */
    public function log($message, $args=NULL, $level);

}
