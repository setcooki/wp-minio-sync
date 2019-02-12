<?php

namespace Setcooki\Minio\Webhook;

/**
 * Class Plugin
 * @package Setcooki\Minio\Webhook
 */
class Plugin
{
    /**
     * @var null
     */
    public static $options = [];


    /**
     * Plugin constructor.
     * @param null $options
     * @throws \Exception
     */
    public function __construct($options = null)
    {
        if(is_array($options))
        {
           static::$options = array_merge(static::$options, $options);
        }
        $this->setup();
    }


    /**
     * @throws \Exception
     */
    protected function setup()
    {
    }


    /**
     * @throws \Exception
     */
    public function init()
    {
        if(!get_option('minio-webhook-token'))
        {
            add_option('minio-webhook-token', bin2hex(random_bytes(16)));
        }
        add_action('plugin_action_links_' . plugin_basename(MINIO_SYNC_FILE), function($links)
        {
            $url = sprintf('%swebhook.php?token=%s', MINIO_SYNC_URL, get_option('minio-webhook-token'));
            $links = array_merge($links, ['<span style="color:black">Token:</span> <a href="'.$url.'" target="_blank">'.get_option('minio-webhook-token').'</a>']);
            return $links;
        });
    }


    /**
     * @throws \Exception
     */
    public function activate()
    {
    }


    /**
     *
     */
    public function deactivate()
    {
    }


    /**
     *
     */
    public static function uninstall()
    {
        delete_option('minio-webhook-token');
    }
}