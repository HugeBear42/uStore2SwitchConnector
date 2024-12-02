<?php
/*
	retry2Switch.php © 2024 frank@xmpie.com 
	A script that will try to upload all orders in 'retry' status to Switch	
	
	v1.00 of 2024-11-30	Genesis

*/

namespace App\controllers;


use App\utils\Database;
use App\utils\Logger;
use App\controllers\OrderWrapper;

// Setup a simple PHP autoloader
spl_autoload_register(function ($class) {$path=__DIR__.'/../../'.str_replace('\\', '/', $class).'.php'; require $path;});

$configArray=require __DIR__.'/../config/appConfig.php';
$db=new Database($configArray['db']);

$candidates=OrderWrapper::getOrderDetailsByStatus($db, OrderWrapper::RETRY, 10);
if( !empty($candidates))
{	Logger::info("------------------------------ Processing retry orders, ".sizeof($candidates)." orders found! ------------------------------");	}
foreach($candidates as $line)
{
	$payload=file_get_contents($line['JSONFilePath']);
	Logger::info("PAYLOAD: ".$payload);
	$success=OrderWrapper::sendJSONToSwitch($db, $configArray['switch'], $line['OrderId'], $payload, $line['RetryCount']);
}
exit;
