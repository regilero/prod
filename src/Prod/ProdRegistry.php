<?php

namespace Drupal\Prod;

use Drupal\Prod\ProdObject;

/**
 * Prod registry class, contains some initialization routines
 *
 */
class ProdRegistry extends ProdObject
{
    protected static $instance;
    
    public static function initInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new ProdRegistry();
        }
        return self::$instance;
    }
    
    public function __construct()
    {
        return $this->initHelpers();
    }
    
    /**
     * Ensure all Helpers (log) are loaded into this object, and initialize
     * all observers of this module
     *
     */
    public function initHelpers()
    {
        parent::initHelpers();
        
        $this->logger->log('Init Known internal Observers', NULL, WATCHDOG_DEBUG);
        
        // Ensure our known internal Task Collectors are registered
        $user = \Drupal\Prod\Stats\Drupal\User::getInstance();
        $node = \Drupal\Prod\Stats\Drupal\Nodes::getInstance();
        $db = \Drupal\Prod\Db\TaskHandler::getInstance();
        
        return $this;
    }
}
