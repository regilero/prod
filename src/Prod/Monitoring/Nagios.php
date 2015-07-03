<?php

namespace Drupal\Prod\Monitoring;

use Drupal\Prod\Error\MonitoringException;
use Drupal\Prod\ProdObject;
use Drupal\Prod\Error\Drupal\Prod\Error;

/**
 * 
 */
class Nagios extends ProdObject
{

    public function check(&$output, &$perf) {
        
        $status = PROD_MONITORING_SUCCESS;
        $messages = array();
        $output = '';
        $perf = '';
        
        $checkers = module_implements('prod_nagios_check');
        
        foreach($checkers as $i => $module) {
            
            $result = module_invoke($module, 'prod_nagios_check');
            
            // Check output format
            if (!is_array($result) || !(array_key_exists('status', $result)) ) {
                throw new MonitoringException($module . ' has invalid output format');
            }
            // Collect messages
            if (array_key_exists('message', $result)) {
                $messages[] = $result['message'];
            }
            // Collect perfdata
            // TODO (via arrays?)
            
            switch ($result['status']) {
                case PROD_MONITORING_CRITICAL:
                    $status = $result['status'];
                    // stop processing modules and exit right now
                    break 2;
                case PROD_MONITORING_WARNING:
                    if ( (PROD_MONITORING_SUCCESS === $status)
                       ||(PROD_MONITORING_PENDING === $status) 
                       ||(PROD_MONITORING_UNKNOWN === $status) ) {
                        $status = $result['status']; 
                    }
                    break;
                case PROD_MONITORING_UNKNOWN:
                    if ( (PROD_MONITORING_SUCCESS === $status)
                       ||(PROD_MONITORING_PENDING === $status) ) {
                        $status = $result['status']; 
                    }
                    break;
                case PROD_MONITORING_PENDING:
                    if ( (PROD_MONITORING_SUCCESS === $status) ) {
                        $status = $result['status']; 
                    }
                    break;
            }

        }
        
        $output = implode('; ', $messages);
        
        return $status;
    }
}
