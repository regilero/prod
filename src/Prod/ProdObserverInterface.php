<?php

namespace Drupal\Prod;

use Drupal\Prod\ProdObject;
use Drupal\Prod\ProdObservable;

/**
 * Observer Object Interface
 *
 */
interface ProdObserverInterface
{

    /**
     * Receive and handle a signal from an Observable object that we registered.
     * 
     * @param ProdObservable $sender Sender of the signal (Obervable)
     * 
     * @param int $signal one of the signal defined as consts in ProdObservable
     * 
     * @param string $info optional information associated with the signal
     */
    public function signal(ProdObservable $sender, $signal, $infp=null);
    
}
