<?php
/*
Plugin Name: Minio Webhook (Media Cloud Extension)
Plugin URI: https://github.com/setcooki/minio-webhook
Description: A Wordpress (ilab media tools) extension to synchronise cloud based Wordpress instance media via Minio webhooks
Author: Frank Mueller <set@cooki.me>
Author URI: https://github.com/setcooki/
Issues: https://github.com/setcooki/minio-webhook/issues
Text Domain: minio-webhook
Version: 
*/
if(!defined('MINIO_WEBHOOK_DOMAIN'))
{
    define('MINIO_WEBHOOK_DOMAIN', 'minio-webhook');
}
define('MINIO_WEBHOOK_DIR', dirname(__FILE__));
define('MINIO_WEBHOOK_NAME', basename(__FILE__, '.php'));
define('MINIO_WEBHOOK_FILE', __FILE__);
define('MINIO_WEBHOOK_URL', plugin_dir_url(MINIO_WEBHOOK_FILE));

if(!function_exists('minio_webhook'))
{
    function minio_webhook()
    {
        try
        {
            require dirname(__FILE__) . '/lib/vendor/autoload.php';

            $plugin = new \Setcooki\Minio\Webhook\Plugin();
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
minio_webhook();
