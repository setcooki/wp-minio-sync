<?php

namespace Setcooki\Wp\Minio\Sync;

/**
 * Class Webhook
 * @package Setcooki\Minio\Webhook
 */
class Webhook
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
        if(!defined('DIRECTORY_SEPARATOR'))
        {
            define('DIRECTORY_SEPARATOR', ((isset($_ENV['OS']) && strpos('win', $_ENV['OS']) !== false) ? '\\' : '/'));
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
                        return true;
                    }
                    $args =
                    [
                        'guid'              => $guid,
                        'post_mime_type'    => $this->getMimeType(),
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
        return preg_replace("=^(\/?{$this->bucket}\/?)=", '', $key);
    }


    /**
     * @param $file
     * @return bool
     */
    protected function unlink($file)
    {
        if(is_file($file))
        {
            return unlink($file);
        }
        return true;
    }


    /**
     * @param $attachment_id
     * @param $key
     * @param $file
     * @return mixed
     */
    protected function generateAttachmentData($attachment_id, $key, $file)
    {
        $key            = ltrim($key, ' /');
        $endpoint       = get_option('ilab-media-s3-endpoint');
        $bucket         = get_option('ilab-media-s3-bucket');
        $privacy        = get_option('ilab-media-s3-privacy');
        $provider       = get_option('ilab-media-storage-provider');
        $cache_control  = get_option('ilab-media-s3-cache-control');
        $expires        = get_option('ilab-media-s3-expires');
        $url            = sprintf('%s/%s/%s', rtrim($endpoint, ' /'), trim($bucket, ' /'), ltrim($key, ' /'));
        $type           = mime_content_type($file);

        $data = wp_generate_attachment_metadata($attachment_id, $file);

        if(empty($endpoint) || empty($bucket) || empty($provider))
        {
            return $data;
        }
        if(isset($data['s3']))
        {
            return $data;
        }

        $params = [];
        $options = [];
        if(!empty($cache_control))
        {
            $params['CacheControl'] = $cache_control;
        }
        if(!empty($expires))
        {
            $params['Expires'] = $expires;
        }
        if(!empty($params))
        {
            $options['params'] = $params;
        }

        $data['s3'] =
        [
            'url' => $url,
            'bucket' => $bucket,
            'privacy' => $privacy,
            'key' => $key,
            'provider' =>  $provider,
            'options' => $options
        ];
        $filetype = wp_check_filetype($file);
        if(!empty($filetype) && isset($filetype['type']))
        {
            $data['s3']['mime-type'] = $type = $filetype['type'];
        }
        if($this->isImage($file))
        {
            if(isset($data['sizes']))
            {
                foreach($data['sizes'] as $size => &$sizes)
                {
                    if(!is_array($sizes)) continue;
                    if(!isset($sizes['s3']))
                    {
                        $_key = preg_replace_callback('=(\.(jp(e)?g|gif|png)$)=i', function($m) use ($sizes)
                        {
                            return sprintf('-%dx%d%s', (int)$sizes['width'], (int)$sizes['height'], $m[0]);
                        }, $key);
                        $_url = sprintf('%s/%s/%s', rtrim($endpoint, ' /'), trim($bucket, ' /'), ltrim($_key, ' /'));
                        $sizes['s3'] = [
                            'url' => $_url,
                            'bucket' => $bucket,
                            'privacy' => $privacy,
                            'key' => $_key,
                            'provider' =>  $provider,
                            'options' => $options,
                            'mime-type' => $sizes['mime-type']
                        ];
                    }
                }
            }
        }else{
            if(!isset($data['file']) || (isset($data['file']) && empty($data['file'])))
            {
                $data['file'] = $file;
            }
            if(!isset($data['url']) || (isset($data['url']) && empty($data['url'])))
            {
                $data['url'] = $url;
            }
            if(!isset($data['type']) || (isset($data['type']) && empty($data['type'])))
            {
                $data['type'] = $type;
            }
        }
        return $data;
    }


    /**
     * @param $args
     * @param $file
     * @return int|\WP_Error
     */
    protected function saveAttachment($args, $file)
    {
        return wp_insert_attachment($args, $file);
    }


    /**
     * @param $file
     * @return array
     */
    protected function getAttachmentMeta($file)
    {
        $info = pathinfo($file);
        $name = wp_basename($info['basename'], "." . $info['extension']);
        $title = sanitize_text_field($name);
        $type = mime_content_type($file);
        $content = '';
       	$excerpt = '';

       	// from wordpress wp_handle_media()
        if ( preg_match( '#^audio#', $type ) ) {
       		$meta = wp_read_audio_metadata( $file );

       		if ( ! empty( $meta['title'] ) ) {
       			$title = $meta['title'];
       		}

       		if ( ! empty( $title ) ) {

       			if ( ! empty( $meta['album'] ) && ! empty( $meta['artist'] ) ) {
       				/* translators: 1: audio track title, 2: album title, 3: artist name */
       				$content .= sprintf( __( '"%1$s" from %2$s by %3$s.' ), $title, $meta['album'], $meta['artist'] );
       			} elseif ( ! empty( $meta['album'] ) ) {
       				/* translators: 1: audio track title, 2: album title */
       				$content .= sprintf( __( '"%1$s" from %2$s.' ), $title, $meta['album'] );
       			} elseif ( ! empty( $meta['artist'] ) ) {
       				/* translators: 1: audio track title, 2: artist name */
       				$content .= sprintf( __( '"%1$s" by %2$s.' ), $title, $meta['artist'] );
       			} else {
       				/* translators: 1: audio track title */
       				$content .= sprintf( __( '"%s".' ), $title );
       			}

       		} elseif ( ! empty( $meta['album'] ) ) {

       			if ( ! empty( $meta['artist'] ) ) {
       				/* translators: 1: audio album title, 2: artist name */
       				$content .= sprintf( __( '%1$s by %2$s.' ), $meta['album'], $meta['artist'] );
       			} else {
       				$content .= $meta['album'] . '.';
       			}

       		} elseif ( ! empty( $meta['artist'] ) ) {

       			$content .= $meta['artist'] . '.';

       		}

       		if ( ! empty( $meta['year'] ) ) {
       			/* translators: Audio file track information. 1: Year of audio track release */
       			$content .= ' ' . sprintf( __( 'Released: %d.' ), $meta['year'] );
       		}

       		if ( ! empty( $meta['track_number'] ) ) {
       			$track_number = explode( '/', $meta['track_number'] );
       			if ( isset( $track_number[1] ) ) {
       				/* translators: Audio file track information. 1: Audio track number, 2: Total audio tracks */
       				$content .= ' ' . sprintf( __( 'Track %1$s of %2$s.' ), number_format_i18n( $track_number[0] ), number_format_i18n( $track_number[1] ) );
       			} else {
       				/* translators: Audio file track information. 1: Audio track number */
       				$content .= ' ' . sprintf( __( 'Track %1$s.' ), number_format_i18n( $track_number[0] ) );
       			}
       		}

       		if ( ! empty( $meta['genre'] ) ) {
       			/* translators: Audio file genre information. 1: Audio genre name */
       			$content .= ' ' . sprintf( __( 'Genre: %s.' ), $meta['genre'] );
       		}

       	// Use image exif/iptc data for title and caption defaults if possible.
       	} elseif ( 0 === strpos( $type, 'image/' ) && $image_meta = @wp_read_image_metadata( $file ) ) {
       		if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) ) {
       			$title = $image_meta['title'];
       		}

       		if ( trim( $image_meta['caption'] ) ) {
       			$excerpt = $image_meta['caption'];
       		}
       	}

        return
        [
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt
        ];
    }


    /**
     * @param $file
     * @return bool
     */
    protected function isImage($file)
    {
        return (bool)preg_match('=\.(jp(e)?g|gif|png)$=i', $file);
    }


    /**
     * @param $file
     * @return bool
     */
    protected function isOriginalImage($file)
    {
        if(($size = getimagesize($file)) !== false)
        {
            foreach($this->getImageSizes() as $key => $sizes)
            {
                if(($dim = image_resize_dimensions((int)$size[0], (int)$size[1], (int)$sizes['width'], (int)$sizes['height'], (bool)$sizes['crop'])) !== false)
                {
                    if(is_array($dim) && isset($dim[4]) && isset($dim[5]) && (bool)preg_match("=\-({$dim[4]})x({$dim[5]})\.(jp(e)?g|gif|png)$=i", $file))
                    {
                        return false;
                    }
                }
            }
        }
        return true;
    }


    /**
     * @return array
     */
    protected function getImageSizes()
    {
        global $_wp_additional_image_sizes;

       	$sizes = [];

       	foreach(get_intermediate_image_sizes() as $s)
       	{
       		if(in_array($s, array('thumbnail', 'medium', 'medium_large', 'large')))
       		{
                $sizes[$s] =
                [
                    'width'  => get_option("{$s}_size_w"),
                    'height' => get_option("{$s}_size_h"),
                    'crop'   => (bool) get_option("{$s}_crop")
                ];
       		}else if(isset($_wp_additional_image_sizes[$s]) && !empty($_wp_additional_image_sizes[$s])){
       			$sizes[$s] =
                [
                    'width'  => $_wp_additional_image_sizes[$s]['width'],
                    'height' => $_wp_additional_image_sizes[$s]['height'],
                    'crop'   => (bool)$_wp_additional_image_sizes[$s]['crop']
                ];
       		}
       	}

       	return $sizes;
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
