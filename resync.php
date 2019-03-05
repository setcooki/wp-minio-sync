#!/usr/bin/php

<?php

if(strtolower(php_sapi_name()) !== 'cli')
{
    die("script can only run in cli mode");
}
if(isset($_SERVER['PWD']) && stripos(basename($_SERVER['PWD']), 'plugins') === false){
    define('ABSPATH', preg_replace('=wp-content.*=i', '', $_SERVER['PWD']));
}else if(stripos(basename(dirname(__DIR__)), 'plugins') === false){
    define('ABSPATH', preg_replace('=wp-content.*=i', '', $_SERVER['SCRIPT_FILENAME']));
}else{
    define('ABSPATH', dirname(__FILE__) . '/../../../../');
}

if(!defined('WPINC'))
{
    define('WPINC', 'wp-includes');
}
if(!defined('DIRECTORY_SEPARATOR'))
{
    define('DIRECTORY_SEPARATOR', ((isset($_ENV['OS']) && strpos('win', $_ENV['OS']) !== false) ? '\\' : '/'));
}

define('WP_USE_THEMES', false);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SHORTINIT', false);

error_reporting(E_ALL);
ini_set( 'error_log', ABSPATH . 'wp-content' . DIRECTORY_SEPARATOR . 'debug.log');

require_once ABSPATH . 'wp-load.php';

$args = [];
if($argv)
{
    foreach((array)$argv as $arg)
    {
        $arg = trim($arg);
        if(substr($arg, 0, 2) === '--')
        {
            if(stripos($arg, '=') !== false){
                $arg = explode('=', $arg);
                $args[substr($arg[0], 2)] = $arg[1];
            }else{
                $args[substr($arg, 2)] = null;
            }
        }
    }
}

try
{
    (new \Setcooki\Wp\Minio\Sync\Resync($args))->execute();
}
catch(\Exception $e)
{
    echo sprintf('%s, %d', $e->getMessage(), $e->getCode()) . PHP_EOL;
}