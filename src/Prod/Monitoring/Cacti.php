<?php

namespace Drupal\Prod\Monitoring;

use Drupal\Prod\Error\MonitoringException;
use Drupal\Prod\ProdObservable;
use Drupal\Prod\ProdObserver;

/**
 * 
 */
class Cacti extends ProdObservable
{
    protected $_lines = array();
    
    protected static $instance;
    
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new Cacti();
        }
        return self::$instance;
    }

    public function render() {
        
        $reporters = module_implements('prod_monitor_summary');
        
        $lines = array();
        
        // D7 hooks
        foreach($reporters as $i => $module) {
            
            $new_lines = module_invoke($module, 'prod_monitor_summary');
            
            if (!is_array($new_lines)) {
                throw new MonitoringException($module . ' has invalid output format');
            }
            
            $lines = array_merge($lines, new_lines);
        }

        // Listeners should call AddOutputLine() on ourself
        $this->notify(ProdObservable::SIGNAL_MONITOR_SUMMARY);
        
        $lines = array_merge($lines, $this->_lines);
        
        foreach ($lines as $line) {
            drush_print($line, 0, NULL, TRUE);
        }
    }
    
    public function AddOutputLine($line)
    {

        $this->logger->log("Receiving line :".$line, NULL, WATCHDOG_DEBUG);
        
        $this->_lines[] = $line;
        return $this;
    }
}
