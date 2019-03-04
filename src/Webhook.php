<?php

namespace Setcooki\Wp\Minio\Sync;

/**
 * Class Webhook
 * @package Setcooki\Minio\Webhook
 */
class Webhook extends Base
{
    /**
     * @var array
     */
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
     * @param null $key
     * @return |null
     */
    public function key($key = null)
    {
        if($key !== null)
        {
            $this->key = $key;
        }
        return $this->key;
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
    public function delete()
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
     * @return bool
     */
    public function put()
    {
        $key = $this->pathFromKey($this->key);
        if(Minio::instance()->has($key))
        {
            $udir   = wp_upload_dir();
            $guid   = rtrim($udir['baseurl'], '\\/') . DIRECTORY_SEPARATOR . ltrim($key, '\\/');
            $file   = basename($key);
            $name   = pathinfo($file, PATHINFO_FILENAME);
            $link   = Minio::instance()->get($key);
            $tmp    = rtrim($udir['basedir'], '/\\') . DIRECTORY_SEPARATOR . ltrim($key, '/\\');
            $path   = dirname($tmp);

            if(!is_dir($path))
            {
                mkdir($path, 0755, true);
            }
            if(file_put_contents($tmp, fopen($link, 'r')))
            {
                try
                {
                    $meta = $this->getAttachmentMeta($tmp);
                    //if we process an image and the image is generated thumbnail identified by the file name ending we bail
                    if($this->isImage($tmp) && !$this->isOriginalImage($tmp))
                    {
                        $this->unlink($tmp);
                        return true;
                    }
                    $args =
                    [
                        'guid'              => $guid,
                        'post_mime_type'    => $this->getMimeType($tmp),
                        'post_title'        => $meta['title'],
                        'post_content'      => $meta['content'],
                        'post_status'       => 'inherit',
                        'post_type'         => 'attachment',
                        'post_excerpt'      => $meta['excerpt']
                    ];
                    if(($id = $this->getAttachment($guid)) !== false)
                    {
                        $args['ID'] = $id;
                    }
                    $attach_id = $this->saveAttachment($args, $key);
                    if(!($attach_id instanceof \WP_Error))
                    {
                        $data = $this->generateAttachmentData($attach_id, $key, $tmp);
                        update_post_meta($attach_id, '_wp_attachment_metadata', $data);
                        $this->unlink($tmp);
                        return true;
                    }else{
                        throw new \Exception(sprintf(__('Unable to insert attachment: %s', MINIO_SYNC_DOMAIN), $attach_id->get_error_message()));
                    }
                }
                catch(\Exception $e)
                {
                    $this->unlink($tmp);
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
     * @param null $key
     * @return bool|string
     */
    protected function getMimeType($key = null)
    {
        if($key === null)
        {
            return mime_content_type($key);
        }
        $item = $this->item();
        if(!empty($item) && isset($item->s3) && isset($item->s3->object) && isset($item->s3->object->contentType))
        {
            return $item->s3->object->contentType;
        }else if(!empty($this->key)){
            return mime_content_type($this->key);
        }else{
            return false;
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
        return preg_replace("=^(\/?{$this->bucket}\/?)=", '', $key);
    }
}
