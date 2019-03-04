<?php

if(!defined('ABSPATH'))
{
    if(isset($_SERVER['DOCUMENT_ROOT']) && !empty($_SERVER['DOCUMENT_ROOT']))
    {
        define('ABSPATH', rtrim($_SERVER['DOCUMENT_ROOT'], ' /\\') . DIRECTORY_SEPARATOR);
    }else if(isset($_SERVER['SCRIPT_FILENAME']) && !empty($_SERVER['SCRIPT_FILENAME'])){
        define('ABSPATH', preg_replace('=wp-content.*=i', '', dirname($_SERVER['SCRIPT_FILENAME'])));
    }else{
        define('ABSPATH', dirname(__FILE__) . '/../../../');
    }
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

try
{
    $debug = (isset($_REQUEST['debug']) && (bool)$_REQUEST['debug']) ? true : false;
    $update = (isset($_REQUEST['update']) && (bool)$_REQUEST['update']) ? true : false;
    if(strtolower($_SERVER['REQUEST_METHOD']) !== 'get')
    {
        throw new \Exception('Request method not allowed');
    }
    if(!isset($_REQUEST['file']) || (isset($_REQUEST['file']) && empty($_REQUEST['file'])))
    {
        throw new \Exception('Empty or non-existing file value');
    }
    if(!isset($_REQUEST['token']) || (isset($_REQUEST['token']) && empty($_REQUEST['token'])))
    {
        throw new \Exception('Empty or non-existing token');
    }
    if((string)$_REQUEST['token'] !== (string)get_option('minio-webhook-token'))
    {
        throw new \Exception('Token miss match');
    }
    echo (int)(new \Setcooki\Wp\Minio\Sync\Sync(['debug' => $debug]))->execute($_REQUEST['file'], $update);
}
catch(\Exception $e)
{
    if($debug)
    {
        die($e->getMessage());
    }else{
        echo 0;
    }
    error_log($e->getMessage());
}