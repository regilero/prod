<?php

namespace Drupal\Prod;

use Drupal\Prod\ProdObject;
use Drupal\Prod\ProdObserver;
use Drupal\Prod\Log\LogFactory;

/**
 * Observable Object
 *
 */
class ProdObservable extends ProdObject
{

    const SIGNAL_MONITOR_SUMMARY=1;
    const SIGNAL_STAT_TASK_INFO=2;
    
    /**
     * Log helper
     */
    protected $log;
    
    
    protected $observers = array();
    
    public function attach(ProdObserverInterface $observer) {
    
        $key = array_search($observer, $this->observers);
        
        if ( FALSE===$key ) {
            $this->observers[] = $observer;
        }
    }
    
    public function detach(ProdObserverInterface $observer) {
    
        $key = array_search($observer, $this->observers);
        
        if ( FALSE!==$key ) {
            unset($this->observers[$key]);
        }
    
    }
    
    /**
     * Send a signal to each attached observer
     * 
     * @param int $signal as defined in ProdObservable constants
     * @param string $info optionnal information associated with the signal
     */
    public function notify($signal, $info=null) {
    
        $this->logger->log(__METHOD__ 
                . ' sending signal ' 
                . $signal 
                . ' to ' 
                . count($this->observers)
                . ' observers.' , NULL, WATCHDOG_DEBUG);
        foreach ($this->observers as $observer) {
            $observer->signal($this, $signal, $info);
        }
    
    }

    public function __construct()
    {

        // load the helpers (like $this->logger)
        $this->initHelpers();

        return $this;

    }
}
