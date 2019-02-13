<?php

namespace Setcooki\Wp\Minio\Sync;

/**
 * Class Webhook
 * @package Setcooki\Minio\Webhook
 */
class Webhook
{
    protected $options = [];

    /**
     * @var array|mixed|object|null
     */
    protected $data = null;

    /**
     * @var null
     */
    protected $item = null;

    /**
     * @var null
     */
    public $bucket = null;

    /**
     * @var null
     */
    public $type = null;

    /**
     * @var null
     */
    public $key = null;


    /**
     * Webhook constructor.
     * @param null $options
     * @throws \Exception
     */
    public function __construct($options = null)
    {
        if($options !== null)
        {
            $this->options = array_merge((array)$options, (array)$options);
        }

        if(!get_option('ilab-media-s3-bucket'))
        {
            throw new \Exception(__('Minio bucket option \'ilab-media-s3-bucket\' not found (assuming storage has not been set up yet)', MINIO_SYNC_DOMAIN));
        }else{
            $this->bucket = get_option('ilab-media-s3-bucket');
        }
        Minio::instance();
    }


    /**
     * @param $data
     * @throws \Exception
     */
    protected function validate($data)
    {
        if(!isset($data->EventName) || (isset($data->EventName) && empty($data->EventName)))
        {
            throw new \Exception(__('Webhook payload is missing \'EventName\' key', MINIO_SYNC_DOMAIN));
        }
        if(!isset($data->Key) || (isset($data->Key) && empty($data->Key)))
        {
            throw new \Exception(__('Webhook payload is missing \'Key\' key', MINIO_SYNC_DOMAIN));
        }
        if(!isset($data->Records) || (isset($data->Records) && empty($data->Records)))
        {
            throw new \Exception(__('Webhook payload is missing \'Records\' key', MINIO_SYNC_DOMAIN));
        }
        if(!is_array($data->Records) || (is_array($data->Records) && !array_key_exists(0, $data->Records)))
        {
            throw new \Exception(__('Webhook payload has no \'Records\' items', MINIO_SYNC_DOMAIN));
        }
        if(empty($data->Records[0]) || (!empty($data->Records[0]) && !is_object($data->Records[0])))
        {
            throw new \Exception(__('Webhook payload has no valid \'Records\' items', MINIO_SYNC_DOMAIN));
        }
        if(preg_match('=\:(put|delete)$=i', $data->EventName, $m))
        {
            $this->data     = $data;
            $this->item     = ($data->Records[0]);
            $this->key      = trim($data->Key);
            $this->type     = strtolower(trim($m[1]));
        }
    }


    /**
     * @param null $data
     * @return array|mixed|object|null
     */
    public function data($data = null)
    {
        if($data !== null)
        {
            $this->data = $data;
        }
        return $this->data;
    }


    /**
     * @param null $item
     * @return null
     */
    public function item($item = null)
    {
        if($item !== null)
        {
            $this->item = $item;
        }
        return $this->item;
    }


    /**
     * @param $data
     * @return bool
     * @throws \Exception
     */
    public function execute($data)
    {
        $this->validate($data);
        if(method_exists($this, $this->type))
        {
            if($this->bailIp())
            {
                throw new \Exception(_('Remote IP is not allowed to use webhook', MINIO_SYNC_DOMAIN));
            }else{
                return $this->{$this->type}($data);
            }
        }else{
             throw new \Exception(sprintf(__('Webhook type: %s is not implemented', MINIO_SYNC_DOMAIN), $this->type));
        }
    }


    /**
     * @return bool
     */
    protected function delete()
    {
        $key    = $this->pathFromKey($this->key);
        $udir   = wp_upload_dir();
        $guid   = $udir['baseurl'] . $key;
        if(($id = $this->getAttachment($guid)) !== false)
        {
            //TODO: when there is no file in /uploads wp_delete_attachment() throws a bunch or warnings
            if(($post = @wp_delete_attachment($id, true)) instanceof \Wp_Post)
            {
                return true;
            }else{
                return false;
            }
        }
        return true;
    }


    /**
     * @throws \Exception
     */
    protected function put()
    {
        $key = $this->pathFromKey($this->key);
        if(Minio::instance()->has($key))
        {
            $udir   = wp_upload_dir();
            $guid   = $udir['baseurl'] . $key;
            $file   = basename($key);
            $name   = pathinfo($file, PATHINFO_FILENAME);
            $link   = Minio::instance()->get($key);
            $tmp    = $udir['basedir'] . $key;
            if(file_put_contents($tmp, fopen($link, 'r')))
            {
                try
                {
                    $meta = wp_read_image_metadata($tmp);
                    if($meta && is_array($meta))
                    {
                        $excerpt = (array_key_exists('caption', $meta)) ? $meta['caption'] : '';
                        $args =
                        [
                            'guid'              => $guid,
                            'post_mime_type'    => $this->getMimeType(),
                            'post_title'        => sanitize_file_name($name),
                            'post_content'      => '',
                            'post_status'       => 'inherit',
                            'post_type'         => 'attachment',
                            'post_excerpt'      => $excerpt
                        ];
                        if(($id = $this->getAttachment($guid)) !== false)
                        {
                            $args['ID'] = $id;
                        }
                        $attach_id = wp_insert_attachment($args, $key);
                        if(!($attach_id instanceof \WP_Error))
                        {
                            $attach_data = wp_generate_attachment_metadata($attach_id, $tmp);
                            wp_update_attachment_metadata($attach_id, $attach_data);
                            if(is_file($tmp)){ unlink($tmp); }
                            return true;
                        }else{
                            throw new \Exception(sprintf(__('Unable to insert attachment: %s', MINIO_SYNC_DOMAIN), $attach_id->get_error_message()));
                        }
                    }else{
                        throw new \Exception(sprintf(__('Unable to read image meta data', MINIO_SYNC_DOMAIN)));
                    }
                }
                catch(\Exception $e)
                {
                    if(is_file($tmp)){ unlink($tmp); }
                    throw $e;
                }
            }else{
                throw new \Exception(sprintf(__('Unable to create temporary upload file: %s', MINIO_SYNC_DOMAIN), $tmp));
            }
        }else{
            throw new \Exception(sprintf(__('Key: %s not found in storage', MINIO_SYNC_DOMAIN), $key));
        }
    }


    /**
     * @param $guid
     * @return bool
     */
    protected function getAttachment($guid)
    {
        global $wpdb;

        if(preg_match('=^http(s)\:\/\/=i', $guid))
        {
            $guid = parse_url($guid, PHP_URL_PATH);
        }else{
            $guid = trim($guid);
        }
        $results = $wpdb->get_row(sprintf("SELECT * FROM `{$wpdb->prefix}posts` WHERE `post_type` = 'attachment' AND `guid` LIKE '%%%s'", $guid));
        if(!empty($results))
        {
            return $results->ID;
        }
        return false;
    }



    /**
     * @return string
     */
    protected function getMimeType()
    {
        $item = $this->item();
        if(isset($item->s3) && isset($item->s3->object) && isset($item->s3->object->contentType))
        {
            return $item->s3->object->contentType;
        }else{
            return mime_content_type($this->key);
        }
    }


    /**
     * @return bool
     */
    protected function bailIp()
    {
        $item = $this->item();
        if
        (
            isset($item->requestParameters)
            &&
            !empty($item->requestParameters)
            &&
            is_object($item->requestParameters)
            &&
            isset($item->requestParameters->sourceIPAddress)
            &&
            !empty($item->requestParameters->sourceIPAddress)
        ){
            return ($item->requestParameters->sourceIPAddress == static::getServerIp()) ? true : false;
        }else{
            return false;
        }
    }


    /**
     * @param $key
     * @return string|string[]|null
     */
    protected function pathFromKey($key)
    {
        return preg_replace("=^{$this->bucket}=", '', $key);
    }


    /**
     * @return string|null
     */
    public static function getServerIp()
    {
        if(strtolower(php_sapi_name()) !== 'cli')
        {
            $ip = $_SERVER['SERVER_ADDR'];
            if(!empty($ip))
            {
                return $ip;
            }
            if(!empty($HTTP_SERVER_VARS) && !empty($HTTP_SERVER_VARS['SERVER_ADDR']))
            {
                return $HTTP_SERVER_VARS['SERVER_ADDR'];
            }
            $ip = gethostbyname($_SERVER['SERVER_NAME']);
            if((bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)
            {
                return $ip;
            }
            $ip = $_SERVER['LOCAL_ADDR'];
            if(!empty($ip))
            {
                return $ip;
            }
        }
        return null;
    }


    /**
     * get the client ip from request. returns null if not possible
     *
     * @return mixed|null
     */
    public static function getClientIp()
    {
        if(strtolower(php_sapi_name()) !== 'cli')
        {
            if(isset($_SERVER['HTTP_CLIENT_IP']) && strcasecmp($_SERVER['HTTP_CLIENT_IP'], "unknown"))
            {
               return $_SERVER['HTTP_CLIENT_IP'];
            }
            if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'], "unknown"))
            {
               return $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
            if(!empty($_SERVER['REMOTE_ADDR']) && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown"))
            {
               return $_SERVER['REMOTE_ADDR'];
            }
        }
        return null;
    }
}