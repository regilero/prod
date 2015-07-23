#!/usr/bin/env php
<?php
/**
 * Bootstrapper timer metric extractor
  *
 * Check for your PHP interpreter - on Windows you'll probably have to
 * replace line 1 with
 *   #!c:/program files/php/php.exe
 *
 */
$script = basename(array_shift($_SERVER['argv']));

function prod_boostraper_error_handler($errno, $errstr, $errfile, $errline)
{
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }
    $_GLOBALS['errors'][] = "catched PHP error: [$errno] $errstr";
    
    /* Don't execute PHP internal error handler */
    return true;
}
$old_error_handler = set_error_handler("prod_boostraper_error_handler");

function prod_boostraper_shutdown() {
    /*
    if (array_key_exists('errors', $_GLOBALS)) {
      var_dump($_GLOBALS['errors']);
    }
    */
    exit();
}
register_shutdown_function('prod_boostraper_shutdown');

// disallow execution from a web server, only cli mode allowed
if (php_sapi_name() !== "cli") {
    header("HTTP/1.0 404 Not Found");
    exit;
}

// define default settings (as in scripts/drupal.sh)
$cmd = 'index.php';
$_SERVER['HTTP_HOST']       = 'default';
$_SERVER['PHP_SELF']        = '/index.php';
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['SERVER_SOFTWARE'] = NULL;
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['QUERY_STRING']    = '';
$_SERVER['PHP_SELF']        = $_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_USER_AGENT'] = 'console';

$time = array(
  'conf' => 0,
  'page_cache' => 0,
  'page_cache_abs' => 0,
  'db' => 0,
  'db_abs' => 0,
  'query' => 0,
  'query_abs' => 0,
  'variables' => 0,
  'variables_abs' => 0,
  'session' => 0,
  'session_abs' => 0,
  'page_header' => 0,
  'page_header_abs' => 0,
  'language' => 0,
  'language_abs' => 0,
  'full' => 0,
  'full_abs' => 0,
);

try{

    // Drupal bootstrap.
    
    // Warning : php > 5.3 for realpath
    $script_path = realpath(realpath($_SERVER['SCRIPT_FILENAME']));
    // if we are in /path/to/www/sites/something/modules/prod
    // this will give us "/path/to/www/sites/something"
    $prefix_path = substr( $script_path, 0, 
             strpos(realpath($_SERVER['SCRIPT_FILENAME']), '/modules/prod/bootstrapper.php'));
    // Now we'll try to remove the 'sites/something' part...
    $parts =  explode("/",$prefix_path);
    $nb_parts = count($parts);
    if (0 === $nb_parts) {
      define('DRUPAL_ROOT', $prefix_path);
    } else {
      if ( 'sites' === $parts[$nb_parts -2]) {
          array_pop($parts);
          array_pop($parts);
          define('DRUPAL_ROOT', implode('/', $parts));
      } else {
          define('DRUPAL_ROOT', getcwd());
      }
    }
    
    // Change the directory to the Drupal root.
    chdir(DRUPAL_ROOT);
    
    // First time, STARTING
    $t0 = microtime(TRUE);
    
    require_once './includes/bootstrap.inc';
    drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);
    
    // TIME 1, DRUPAL_BOOTSTRAP_CONFIGURATION
    $t1 = microtime(TRUE);
    $time['conf'] = (int) (round( $t1 - $t0, 4 ) * 1000);

    // re-enforce our error handler
    $old_error_handler = set_error_handler("prod_boostraper_error_handler");
    
    drupal_bootstrap(DRUPAL_BOOTSTRAP_PAGE_CACHE);
    
    // TIME 2, DRUPAL_BOOTSTRAP_PAGE_CACHE
    $t2 = microtime(TRUE);
    $time['page_cache_abs'] = (int) (round( $t2 - $t0, 4 ) * 1000);
    $time['page_cache'] = $time['page_cache_abs']- $time['conf'];
    
    drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);
    
    // TIME 3, DRUPAL_BOOTSTRAP_DATABASE
    $t3 = microtime(TRUE);
    $time['db_abs'] = (int) (round( $t3 - $t0 ,4 ) * 1000);
    $time['db'] = $time['db_abs']- $time['page_cache_abs'];
    
    // make a real simple db query, this will really open the db connection
    $result = db_query('SELECT * FROM {users} WHERE uid = 1');
    foreach($result as $account) {
        if (!$account->uid == 1) {
            $_GLOBALS['errors'][] = 'Master database not responding.';
        }
    }
    // TIME 4, DB_CONN is opened
    $t4 = microtime(TRUE);
    $time['query_abs'] = (int) (round( $t4 - $t0, 4 ) * 1000);
    $time['query'] = $time['query_abs']- $time['db_abs'];
    
    drupal_bootstrap(DRUPAL_BOOTSTRAP_VARIABLES);
    
    // TIME 5, DRUPAL_BOOTSTRAP_VARIABLES
    $t5 = microtime(TRUE);
    $time['variables_abs'] = (int) (round( $t5 - $t0, 4 ) * 1000);
    $time['variables'] = $time['variables_abs']- $time['query_abs'];
    
    drupal_bootstrap(DRUPAL_BOOTSTRAP_SESSION);
    
    // TIME 6, DRUPAL_BOOTSTRAP_SESSION
    $t6 = microtime(TRUE);
    $time['session_abs'] = (int) (round( $t6 - $t0, 4 ) * 1000);
    $time['session'] = $time['session_abs']- $time['variables_abs'];
    
    drupal_bootstrap(DRUPAL_BOOTSTRAP_PAGE_HEADER);
    
    // TIME 7, DRUPAL_BOOTSTRAP_PAGE_HEADER
    $t7 = microtime(TRUE);
    $time['page_header_abs'] = (int) (round( $t7 - $t0, 4 ) * 1000);
    $time['page_header'] = $time['page_header_abs']- $time['session_abs'];
    
    drupal_bootstrap(DRUPAL_BOOTSTRAP_LANGUAGE);

    // TIME 8, DRUPAL_BOOTSTRAP_LANGUAGE
    $t8 = microtime(TRUE);
    $time['language_abs'] = (int) (round( $t8 - $t0, 4 ) * 1000);
    $time['language'] = $time['language_abs']- $time['page_header_abs'];
    
    drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
    
    // TIME 9, DRUPAL_BOOTSTRAP_FULL
    $t9 = microtime(TRUE);
    $time['full_abs'] = (int) (round( $t9 - $t0, 4 ) * 1000);
    $time['full'] = $time['full_abs']- $time['language_abs'];
    
} catch (Exception $e) {
    // Do nothing, we do not want to die for a litle error, do we?
    $_GLOBALS['errors'][] = "catched PHP error: " . $e->getMessage();
} 

// Show Bootstrapper results
echo "bootstrapper success run\r\n";
foreach ($time as $key => $value) {
    echo 'bootstrap_' . $key . '=' . $value . "\r\n";
}

// Exit immediately, note the shutdown function registered at the top of the file.
exit();