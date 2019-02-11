<?php

ini_set( 'error_log', dirname(__FILE__) . '/../../debug.log');
$data = file_get_contents("php://input");

try
{
    if(strtolower($_SERVER['REQUEST_METHOD']) !== 'post')
    {
        throw new \Exception('Post method must be post');
    }
    if(!isset($_REQUEST['token']) || (isset($_REQUEST['token']) && empty($_REQUEST['token'])))
    {
        throw new \Exception('Empty or non-existing token');
    }
    if((string)$_REQUEST['token'] !== (string)get_option('minio-webhook-token'))
    {
        throw new \Exception('Token miss match');
    }
    if(!empty($data))
    {
        throw new \Exception(__('Empty post data', 'minio-webhook'));
    }
    if(($data = json_decode($data)) !== null && json_last_error() === JSON_ERROR_NONE)
    {
        (new \Setcooki\Minio\Webhook\Webhook())->execute($data);
    }else{
        throw new \Exception(sprintf('Json decode error: %s', json_last_error_msg()));
    }
}
catch(\Exception $e)
{
    echo $e->getMessage();
    error_log($e->getMessage());
}