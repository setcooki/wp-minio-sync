<?php

namespace Setcooki\Wp\Minio\Sync;

/**
 * Class Sync
 * @package Setcooki\Wp\Minio\Sync
 */
class Sync extends Base
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
     * @param $file
     * @param bool $update
     * @return bool
     * @throws \Exception
     */
    public function execute($file, $update = false)
    {
        if(!(bool)$update && $this->getAttachment($file))
        {
            return true;
        }
        $webhook = new Webhook($this->options);
        $webhook->key($file);
        return $webhook->put();
    }
}