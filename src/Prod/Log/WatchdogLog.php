<?php

namespace Drupal\Prod\Log;

/**
 * WatchdogLog Log logger
 */
class WatchdogLog implements LogInterface
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
     *                      WATCHDOG_EMERGENCY 0
     *                      WATCHDOG_ALERT     1
     *                      WATCHDOG_CRITICAL  2
     *                      WATCHDOG_ERROR     3
     *                      WATCHDOG_WARNING   4
     *                      WATCHDOG_NOTICE    5
     *                      WATCHDOG_INFO      6
     *                      WATCHDOG_DEBUG     7
     *
     * @return TableInterface
     */
    public function log($message, $args=NULL, $level)
    {
        if (variable_get('prod_log_level',WATCHDOG_NOTICE) >= $level) {
          watchdog('Prod',$message, $args,$level);
        }
    }

}
