<?php
/*
	sendOrderToSwitch.php © 2024 frank@xmpie.com 
	A utility script that parses the order XML request body, converts to JSON, write to DB, then forwards to a switch workflow	
	
	v1.00 of 2024-11-05	Genesis
	
*/

use App\utils\Database;
use App\utils\Logger;
use App\controllers\OrderXML2JSON;
use App\controllers\OrderWrapper;

// Setup a simple PHP autoloader
spl_autoload_register(function ($class) {$path=__DIR__.'/../'.str_replace('\\', '/', $class).'.php'; require $path;});

$configArray=require __DIR__.'/../App/config/appConfig.php';
$db=new Database($configArray['db']);
$debug=$configArray['debug'];	// If true, script can be run from the command-line & will retrieve XML file from the tmp folder
Logger::setDebug($debug);

Logger::info("------------------------------ Send order info to Switch ------------------------------");

$tmpDir=$configArray['switch']['tmpDir'].date("Y-m").'/';	// Add yyyy-mm timestamp to the archive for easier file deletion
if( !is_dir($tmpDir))
{	mkdir($tmpDir, 0777, true);	}

$tmpFile=$tmpDir.uniqid().".xml";
$xmlContents=file_get_contents($configArray['switch']['sampleFile']);	// Use the debug file as a default value

if(array_key_exists('SERVER_PROTOCOL', $_SERVER))	// This is an online request & not CLI
{
	$xmlContents=file_get_contents("php://input");
	if( stripos($_SERVER["CONTENT_TYPE"], "utf-8") >0 && stripos($xmlContents, '<?xml version="1.0" encoding="utf-16"?>')==0 )	// payload encoded as utf-8 but file will be parsed as utf-16, conversion needed!
	{
		Logger::info("Reencoding XML payload from UTF-8 to UTF-16!");
		$xmlContents = iconv("UTF-8", "UTF-16", $xmlContents );
	}
}

$success=file_put_contents($tmpFile, $xmlContents);
if($success)
{	Logger::info("New order payload received, temporarily stored to {$tmpFile} ({$success} bytes)");	}
else
{
	Logger::error("Failed to save file {$tmpFile}!");
	http_response_code(500);
	exit;
}

// Now we generate the JSON file from the OrderXML file
try
{
	$xmldata = simplexml_load_file($tmpFile);
	$converter=new OrderXML2JSON();
	$arrayData=$converter->convert($xmldata, $configArray['switch'], true);	// Return an array so we can extract the data to presist in the DB!

	$wrapper=new OrderWrapper($arrayData, $tmpDir);
	if($debug)	// Allows multiple submissions of the same file for testing, otherwise an exception is thrown as the orderId is the primary key!
	{	OrderWrapper::DeleteOrder($db, $wrapper->getOrderId());	}
	$wrapper->writeToDB($db);
	$payload=json_encode($arrayData);
	file_put_contents( $wrapper->getJSONPath(), $payload);	// Write the JSON file to the archive
	Logger::info("Saved file {$wrapper->getJSONPath()}");
	if($debug)
	{
		rename($tmpFile, $wrapper->getXMLPath());			// Write the XML file to the archive
		Logger::info("Saved file {$wrapper->getXMLPath()}");
	}
	else
	{
		unlink($tmpFile);
		Logger::info("Deleted temporary file {$tmpFile}");
	}	
	
	if($configArray['switch']['dataTransfer']!='pushToSwitch')	// Switch will pull the files from the DB, all done for us
	{
		Logger::info("------------------------------ Order {$wrapper->getOrderId()} placed in the Switch download queue ------------------------------");
		exit;
	}
	OrderWrapper::sendJSONToSwitch($db, $configArray['switch'], $wrapper->getOrderId(), $payload, 0);

}
catch(Exception $ex)
{
	$msg="Failed to process OrderXML file $tmpFile, error was: ".$ex;
	Logger::error($msg);
	http_response_code(500);
	exit;
}
exit;
