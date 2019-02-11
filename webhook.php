<?php

ini_set( 'error_log', dirname(__FILE__) . '/../../debug.log');
$data = file_get_contents("php://input");

try
{
    if(strtolower($_SERVER['REQUEST_METHOD']) !== 'post')
    {
        throw new \Exception(__('Post method must be post', 'minio-webhook'));
    }
    if(!isset($_REQUEST['token']) || (isset($_REQUEST['token']) && empty($_REQUEST['token'])))
    {
        throw new \Exception(__('Empty or non-existing token', 'minio-webhook'));
    }
    if((string)$_REQUEST['token'] !== (string)get_option('minio-webhook-token'))
    {
        throw new \Exception(__('Token miss match', 'minio-webhook'));
    }
    if(!empty($data))
    {
        throw new \Exception(__('Empty post data', 'minio-webhook'));
    }
    if(($data = json_decode($data)) !== null && json_last_error() === JSON_ERROR_NONE)
    {
        (new \Setcooki\Minio\Webhook\Webhook())->execute($data);
    }else{
        throw new \Exception(sprintf(__('Json decode error: %s', 'minio-webhook'), json_last_error_msg()));
    }
}
catch(\Exception $e)
{
    error_log($e->getMessage());
}