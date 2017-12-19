<?php

namespace Drupal\Prod\Config;

use Drupal\Prod\Error\ProdException;

/**
 * Config Main object
 */
class Config
{
    /**
     * Internal storage of full config
     */
    private static $full_config = NULL;

    private static $dirty = False;

    private $section = NULL;
    private $config_token = '';
    /**
     * Factory getter.
     *
     * Return the Config Singleton
     * Detect the right config section based on given token
     *
     * @return ConfigInterface
     */
    public static function getInstance($config_token)
    {

        if (is_null($config_token)) {
            throw new ProdException('NULL config token, you have defined an object'
                . 'derived from ProdConfigurable without defining the config token');
        }
        if (is_null(self::$full_config)) {
            self::$full_config = variable_get('prod_config_array',array());
        }

        $instance = new Config($config_token);

        return $instance;
    }

    public static function register_config_alteration_saved() {
        if (self::$dirty) {
            variable_set('prod_config_array', self::$full_config);
            var_dump('variable_set done');
            var_dump(variable_get('prod_config_array'));
            self::$dirty = False;
        }
    }

    public function __construct($config_token) {

        if (is_null(self::$full_config)) {
            self::$full_config = variable_get('prod_config_array',array());
        }

        $this->config_token = $config_token;

        if (array_key_exists($config_token, self::$full_config)) {
            $this->section = self::$full_config[$config_token];
        } else {
            $this->section = array();
        }
    }

    public function get($name, $default=NULL) {
        $value = $default;
        if (array_key_exists($name, $this->section)) {
            $value = $this->section[$name];
        }
        return $value;
    }

    public function set($name, $value) {
        $this->section[$name] = $value;
        self::$full_config[$this->config_token][$name] = $value;
        if (! self::$dirty) {
            drupal_register_shutdown_function('Drupal\\Prod\\Config\\Config::register_config_alteration_saved');
            self::$dirty = True;
        }
    }
}
