<?php

namespace Setcooki\Minio\Webhook;

/**
 * Class Minio
 * @package Setcooki\Minio\Webhook
 */
class Minio
{
    /**
     * @var null
     */
    protected static $instance = null;

    /**
     * @var null
     */
    protected $minio = null;

    /**
     * @var null
     */
    public $options = [];


    /**
     * Minio constructor.
     * @param null $options
     * @throws \Exception
     */
    protected function __construct($options = null)
    {
        $this->options = (array)$options;
        $this->init();
    }


    /**
     * @param null $options
     * @return null
     * @throws \Exception
     */
    public static function instance($options = null)
    {
        if(static::$instance === null)
        {
            static::$instance = new static($options);
        }
        return static::$instance;
    }


    /**
     * @return bool
     */
    public static function isInstalled()
    {
        return (class_exists('\ILAB\MediaCloud\Cloud\Storage\Driver\S3\MinioStorage'));
    }


    /**
     *
     */
    protected function init()
    {
        if(!static::isInstalled())
        {
            throw new \Exception(sprintf('Storage class: %s not found', '\ILAB\MediaCloud\Cloud\Storage\Driver\S3\MinioStorage'));
        }
        $this->minio = new \ILAB\MediaCloud\Cloud\Storage\Driver\S3\MinioStorage();
    }


    /**
     * @param $path
     * @return mixed
     */
    public function get($path)
    {
        return $this->minio->presignedUrl($path);
    }


    /**
     * @param $path
     * @return bool
     */
    public function has($path)
    {
        return (bool)$this->minio->exists($path);
    }
}