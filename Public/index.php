<?php
/*
	endpoint.php © 2024 frank@xmpie.com 
	The endpoint used by Enfocus Switch to update order status or poll for new orders
	An order polling request body is formatted as follows: { "action" : "polling",  "jobType" : "new", "limit" : 10 }
	An order status update request body is formatted as follows: { "action" : "statusUpdate", "orderId" : 43235,  "status" : "delivering", "message" : "xxx",  "trackingId" : "xxxYYYzzz"}
	Multiple order status updates can be submitted as an array
	
	v1.00 of 2024-11-27	Genesis
	
*/

use App\utils\Database;
use App\utils\Logger;
use App\controllers\OrderWrapper;
spl_autoload_register(function ($class) {$path=__DIR__.'/../'.str_replace('\\', '/', $class).'.php'; require $path;});	// Setup a simple PHP autoloader

function validatePollingRequest(object $obj) : bool
{	return property_exists( $obj, 'action') && $obj->action==='polling' && property_exists($obj, 'jobType') && in_array($obj->jobType, OrderWrapper::STATUS_ARRAY);	}
function validateStatusUpdateRequest(object $obj) : bool
{	return property_exists( $obj, 'action') && $obj->action==='statusUpdate' && property_exists( $obj, 'orderId') && property_exists($obj, 'status') && in_array($obj->status, OrderWrapper::STATUS_ARRAY);	}
function printErrorMessage(string $message) : void
{
	http_response_code(400);
	header("Content-Type: application/json ; charset=utf-8");
	Logger::error($message);
	echo json_encode(["status"=>"error" , "message"=>$message ]);
}
function sendPayload(string $payload) : void
{
	http_response_code(200);
	header("Content-Type: application/json ; charset=utf-8");
	echo $payload;
}

$configArray=require __DIR__.'/../App/config/appConfig.php';
$debug=$configArray['debug'];	// If true, script can be run from the command-line & will retrieve XML file from the tmp folder
Logger::setDebug($debug);

$str=file_get_contents("php://input");	// get the JSON contents
Logger::info("--------------------------- start Switch request ---------------------------");
Logger::fine("Received payload from Switch: ".(empty($str) ? "[empty payload]" : $str));
$resultArray=null;
if(strlen($str)>0)
{
	$resultArray=json_decode($str, false);	// Return as an object or an array or null!
}
if(is_object($resultArray) )	// json is a single object, encapsulate in an array for normalised processing
{	$resultArray=[$resultArray];	}
else if(!is_array($resultArray))
{
	printErrorMessage('Payload '.(empty($str) ? "[empty payload]" : $str).' could not be parsed!');
	exit;	
}

// We need to run some more error checking to make sure we either  have a single polling request or multiple statusUpdate requests but not both !
$count=count($resultArray);
$action='statusUpdate';
foreach($resultArray as $request)
{
	if(validatePollingRequest($request))
	{
		$action='polling';
		if($count>1)
		{
			printErrorMessage('An order polling request cannot be submitted within an array, the request will be ignored!');
			exit;
		}
	}
	else if( !validateStatusUpdateRequest($request))
	{
		printErrorMessage("Invalid JSON request found: ".json_encode($request).", the request will be ignored");
		exit;
	}
}

$db=new Database($configArray['db']);
$str='';
foreach($resultArray as $request)
{
	if($action==='polling')
	{
		$ordersArray=OrderWrapper::getOrderDetailsByStatus($db,$request->jobType, $request->count ?? 10);
		$count=count($ordersArray);
		Logger::info("Found {$count} order".($count!=1 ? "s" : "")." to upload to Switch!");
		$str='';
		foreach($ordersArray as $orderLine)
		{
			Logger::info("Appeding JSON file {$orderLine['JSONFilePath']} to request");
			if(strlen($str)>0)
			{	$str.=',';	}
			$str.=file_get_contents($orderLine['JSONFilePath']);
		}
		$str='['.$str.']';
		break;
	}
	else if($action==='statusUpdate')
	{
		$orderId=$request->orderId;
		$status=strtolower( $request->status );	// ignore upper / lowercase in status string.
		$trackingId=$request->trackingId ?? '';
		$message=$request->message ?? '';
		$deliveryId=OrderWrapper::updateStatus($db, $configArray['uStore'], $orderId, $status, $message, $trackingId);
		if(strlen($str)>0)
		{	$str.=',';	}
		$str.='{"orderId" : '.$orderId.' , "status" : "ok" , "message" : "Order status updated to '.$status.($deliveryId===-1 ? ", uStore status might not have been updated, please check the logs!" : "").'"}';
	}
}
if($action==='polling' || count($resultArray)>1)
{	$str='['.$str.']';	}
sendPayload($str);
Logger::info("--------------------------- end Switch {$action} request ---------------------------");
exit;

