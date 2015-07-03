<?php

namespace Drupal\Prod;

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
    protected $logger;

    /**
     * Ensure all Helpers (log) are loaded into this object
     *
     */
    public function initHelpers()
    {
        $this->logger = LogFactory::get();

        return $this;
    }
}
