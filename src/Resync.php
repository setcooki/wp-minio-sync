<?php

namespace Setcooki\Wp\Minio\Sync;

/**
 * Class Resync
 * @package Setcooki\Wp\Minio\Sync
 */
class Resync extends Base
{
    /**
     * @var array
     */
    protected $options = [];


    /**
     * Sync constructor.
     * @param null $options
     * @throws \Exception
     */
    public function __construct($options = null)
    {
        if($options !== null)
        {
            $this->options = array_merge((array)$options, (array)$options);
        }
        Minio::instance();
    }


    /**
     * @throws \Exception
     */
    public function execute()
    {
        $webhook = new Webhook();
        $minio = Minio::instance();
        $args =
        [
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_status' => ['publish', 'inherit']
        ];
        $args = apply_filters('wp_minio_resync_args', $args);
        foreach(get_posts($args) as $post)
        {
            $s3 = false;
            $key = null;
            $delete = false;
            $guid = $post->guid;
            $meta = get_post_meta($post->ID, '_wp_attachment_metadata', true);
            if(!empty($meta))
            {
                if(is_string($meta))
                {
                    $meta = unserialize($meta);
                }
                if(is_array($meta) && array_key_exists('s3', $meta) && array_key_exists('key', $meta['s3']) && !empty($meta['s3']['key']))
                {
                    $s3 = true;
                    $guid = $meta['s3']['key'];
                    if($minio->has($guid))
                    {
                        $key = $guid;
                    }else{
                        $delete = true;
                    }
                }
            }
            if(!$s3)
            {
                $meta = get_post_meta($post->ID, 'ilab_s3_info', true);
                if(!empty($meta))
                {
                    if(is_string($meta))
                    {
                        $meta = unserialize($meta);
                    }
                    if(is_array($meta) && array_key_exists('s3', $meta) && array_key_exists('key', $meta['s3']) && !empty($meta['s3']['key']))
                    {
                        $s3 = true;
                        $guid = $meta['s3']['key'];
                        if($minio->has($guid))
                        {
                            $key = $guid;
                        }else{
                            $delete = true;
                        }
                    }
                }
            }
            $delete = apply_filters('wp_minio_resync_delete', $delete, $post, $minio);
            if($delete)
            {
                if(array_key_exists('simulate', $this->options))
                {
                    echo sprintf('Deleting attachment with ID: %d (search key: %s) since not found in minio media library', $post->ID, $guid) . PHP_EOL;
                }else{
                    $data = wp_delete_attachment($post->ID, true);
                    if($data === false || $data === null)
                    {
                        echo sprintf('Unable to delete attachment with ID: %d since wp_delete_attachment() returned false', $post->ID) . PHP_EOL;
                    }else{
                        echo sprintf('Deleted attachment with ID: %d since not found in minio media library', $post->ID) . PHP_EOL;
                    }
                }
            }else{
                if(!$s3)
                {
                    if(empty($key))
                    {
                        $key = get_post_meta($post->ID, '_wp_attached_file', true);
                        if(empty($key))
                        {
                            $key = $post->guid;
                        }
                        if(preg_match('=((?:[0-9]{4}\/[0-9]{2}\/)?(?:[a-z0-9\-\_\.]{1,})(\.[a-z0-9]{2,4})?)$=i', $key, $m))
                        {
                            $key = trim($m[1]);
                        }
                    }
                    $key = apply_filters('wp_minio_resync_key', $key, $post, $minio);
                    if($minio->has($key))
                    {
                        if(array_key_exists('simulate', $this->options))
                        {
                            echo sprintf('Found orphaned attachment: ID: %d (search key: %s) with missing s3 meta data that can be matched', $post->ID, $guid) . PHP_EOL;
                        }else{
                            try
                            {
                                $webhook->key($key);
                                $webhook->put();
                                echo sprintf('Auto corrected orphaned attachment: ID: %d (search key: %s) since missing s3 meta data', $post->ID, $guid) . PHP_EOL;
                            }
                            catch(\Exception $e)
                            {
                                echo sprintf('Unable to auto correct orphaned attachment: ID: %d (search key: %s) due to: %s', $post->ID, $guid, $e->getMessage()) . PHP_EOL;
                            }
                        }
                    }else{
                        if(array_key_exists('simulate', $this->options))
                        {
                            echo sprintf('Found orphaned attachment: ID: %d (search key: %s) with missing s3 meta data that can not be matched since key: %s not found in s3 media library', $post->ID, $guid, $key) . PHP_EOL;
                        }else{
                            wp_delete_attachment($post->ID, true);
                        }
                    }
                }
            }
        }
    }
}
