<?php
/*
Plugin Name: WP Minio Sync (Media Cloud Extension)
Plugin URI: https://github.com/setcooki/wp-minio-sync
Description: A Wordpress (ilab media tools) extension to synchronise cloud based Wordpress instance media via Minio webhooks
Author: Frank Mueller <set@cooki.me>
Author URI: https://github.com/setcooki/
Issues: https://github.com/setcooki/wp-minio-sync/issues
Text Domain: wp-minio-sync
Version: 0.1.0
*/
if(!defined('MINIO_SYNC_DOMAIN'))
{
    define('MINIO_SYNC_DOMAIN', 'wp-minio-sync');
}
define('MINIO_SYNC_DIR', dirname(__FILE__));
define('MINIO_SYNC_NAME', basename(__FILE__, '.php'));
define('MINIO_SYNC_FILE', __FILE__);
define('MINIO_SYNC_URL', plugin_dir_url(MINIO_SYNC_FILE));

if(!function_exists('minio_sync'))
{
    function minio_sync()
    {
        try
        {
            require dirname(__FILE__) . '/lib/vendor/autoload.php';
            $plugin = new \Setcooki\Wp\Minio\Sync\Plugin();
            register_activation_hook(__FILE__, array($plugin, 'activate'));
            register_deactivation_hook(__FILE__, array($plugin, 'deactivate'));
            register_uninstall_hook(__FILE__, array(get_class($plugin), 'uninstall'));
            add_action('init', function() use ($plugin)
            {
                $plugin->init();
            });
        }
        catch(Exception $e)
        {
            @file_put_contents(ABSPATH . 'wp-content/logs/error.log', $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}
minio_sync();
