<?php

namespace Drupal\Prod;

use Drupal\Prod\ProdObject;
use Drupal\Prod\Config\Config;

/**
 * Prod Object having some tweakable configurations
 *
 */
class ProdConfigurable extends ProdObject
{

    /**
     * Conf token, VERY important, defined a UNIQUE token for you configuration
     */
    protected $conf_token = NULL;

    /**
     * Ensure all Helpers (log) are loaded into this object
     *
     */
    public function initHelpers()
    {
        parent::initHelpers();
        $this->config = Config::getInstance($this->conf_token);

        return $this;
    }
}
