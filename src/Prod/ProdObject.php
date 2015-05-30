<?php

namespace Drupal\Prod;

use Drupal\Prod\ProdObject;
use Drupal\Prod\Log\LogFactory;

/**
 * Basic Prod Object
 *
 */
class ProdObject
{

    /**
     * Log helper
     */
    protected $log;

    /**
     * Ensure all Helpers (log) are loaded into this object
     *
     */
    public function initHelpers()
    {
        $this->log = LogFactory::get();

        return $this;
    }
}
