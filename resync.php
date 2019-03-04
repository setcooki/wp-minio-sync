#!/usr/bin/php

<?php

if(strtolower(php_sapi_name()) !== 'cli')
{
    die("script can only run in cli mode");
}
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

var_dump(ABSPATH);


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

foreach(get_posts(['post_type' => 'attachment']) as $post)
{
    print_r($post);
    die();
}

