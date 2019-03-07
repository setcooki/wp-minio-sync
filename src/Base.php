<?php

namespace Setcooki\Wp\Minio\Sync;

/**
 * Class Base
 * @package Setcooki\Wp\Minio\Sync
 */
class Base
{
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
        if(preg_match('=((?:[0-9]{4}\/[0-9]{2}\/)?(?:[a-z0-9\-\_\.]{1,})(\.[a-z0-9]{2,4})?)$=i', $guid, $m))
        {
            $guid = trim($m[1]);
        }
        $results = $wpdb->get_row(sprintf("SELECT * FROM `{$wpdb->prefix}posts` WHERE `post_type` = 'attachment' AND `guid` LIKE '%%%s'", $guid));
        if(!empty($results))
        {
            return (int)$results->ID;
        }else{
            $results = $wpdb->get_row(sprintf("SELECT * FROM `{$wpdb->prefix}postmeta` WHERE `meta_key` = '_wp_attached_file' AND `meta_value` LIKE '%%%s'", $guid));
            if(!empty($results))
            {
                return (int)$results->post_id;
            }
        }
        return false;
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
                $data['file'] = $key;
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