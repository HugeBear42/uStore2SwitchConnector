<?php


namespace App\controllers;
use PDO;
use App\utils\WS_XMP_ActualDelivery;
use App\utils\ApplicationException;
use App\utils\Database;
use App\utils\Logger;

class OrderWrapper
{
	const NEW = 'new';
	const PROCESSING = 'processing';
	const ERROR = 'error';
	const DELIVERING = 'delivering';
	const DELIVERED = 'delivered';
	const RETRY = 'retry';
	const STATUS_ARRAY = [ self::NEW, self::PROCESSING, self::ERROR, self::DELIVERING, self::DELIVERED, self::RETRY ];
	
	public function __construct( private array $array, private string $tmpDir)
	{}
	
	public function getOrderId() : int
	{
		return array_key_exists('@OrderId', $this->array['Order']) ? $this->array['Order']['@OrderId'] : $this->array['Order']['OrderId'];	// check if the OrderId tag is preceded by a '@'
	}
	public function getOrderProductIdsAsString() : string
	{
		$str="";
		foreach($this->array['Order']['OrderProducts']['OrderProduct'] as $orderProduct)
		{
			if(!empty($str))
			{	$str.=',';	}
			$str.=array_key_exists('@id', $orderProduct) ? $orderProduct['@id'] : $orderProduct['id'];
		}
		return $str;
	}
	private function getFilePath(string $extension)
	{	return $this->tmpDir.$this->getOrderId().$extension;	}
	public function getJSONPath()
	{	return $this->getFilePath('.json');	}
	public function getXMLPath()
	{	return $this->getFilePath('.xml');	}
	
	public function writeToDB(Database $db) : void
	{
		$query="INSERT INTO Orders (OrderId, OrderProductIds, CreationDateTime, ModificationDateTime, Status, TrackingId, Message, JSONFilePath, RetryCount) VALUES(:OrderId, :OrderProductIds, ".($db->getType()=='mysql' ? 'UTC_TIMESTAMP(), UTC_TIMESTAMP()' : 'GETUTCDATE(), GETUTCDATE()').", 'new', '', '',:path, 0)";
		$orderId=$this->getOrderId();
		$orderProducts=$this->getOrderProductIdsAsString();
		$params=[ 'OrderId'=>$orderId, 'OrderProductIds'=>$orderProducts, 'path'=>$this->getJSONPath() ];
		$result=$db->query($query, $params);
		Logger::info("Database: Created Order {$orderId}, products are {$orderProducts}, status is 'new'");
	}
	
	public static function updateStatus(Database $db, array $configArray, int $orderId, string $status, string $message='', string $trackingId='') : int
	{
		$statusCode=0;
		if( strtolower($status)===self::DELIVERING )
		{	$statusCode=self::processDelivering($db, $configArray, $orderId, $trackingId);	}
		$message=($statusCode!=-1) ? $message : $message.", uStore status might not have been updated, please check the logs!";
		$query="UPDATE Orders SET Status=:Status, Message=:Message".($trackingId==="" ? "" : ", TrackingId=:TrackingId").", ModificationDateTime=".($db->getType()=='mysql' ? 'UTC_TIMESTAMP()' : 'GETUTCDATE()')." WHERE OrderId=:OrderId";
		$params=['Status'=>$status, 'OrderId'=>$orderId, 'Message'=>$message];
		if($trackingId!="")
		{	$params['TrackingId']=$trackingId;	}
		$result=$db->query($query, $params);
		Logger::info("Database: Order {$orderId}, status updated to '{$status}', message is '{$message}', trackingId is '{$trackingId}'");
		return $statusCode;
	}

	public static function processDelivering(Database $db, array $configArray, int $orderId, string $trackingId) :int
	{
		$actualDeliveryId = -1;
		$array=self::getOrderDetails($db, $orderId);
		if( !empty($array) )
		{
			$orderProductArray=explode(',',$array[0]['OrderProductIds']);
			if( !empty($orderProductArray) )
			{
				$deliveryWS=new WS_XMP_ActualDelivery($configArray['uStoreAPI']);
				try
				{
					Logger::info("BEFORE SOAP");
					$actualDeliveryId=$deliveryWS->createDeliveryByOrderProducts($orderProductArray, date('Y-m-d\TH:i:s'), $trackingId );
					Logger::info("AFTER SOAP");
					Logger::fine("Actual Delivery created, Id: {$actualDeliveryId}");
				}
				catch(\Exception $ex)
				{
					Logger::error("Failed to process delivery for order {$orderId}, exception was: ".print_r($ex->getMessage(), true));
				}
			}
			else
			{	Logger::error("Order {$orderId} doesn't contain any order products ?!");	}
		}
		else
		{	Logger::error("Order {$orderId} not found in the database!");	}
		return $actualDeliveryId;
	}

	public static function getOrderDetails(Database $db, int $orderId) : array
	{
		$query="SELECT OrderId, OrderProductIds, CreationDateTime, ModificationDateTime, Status, TrackingId, Message, JSONFilePath FROM Orders WHERE OrderId=:OrderId";
		$params=['OrderId'=>$orderId];
		return $db->query($query, $params)->get();
	}
	
	public static function getOrderDetailsByStatus(Database $db, string $status, int $limit=10) : array
	{
		$limit = ($limit > 100 ) ? 100 : ($limit<1 ? 1 : $limit);
		$top=$db->getType()=='mysql' ? "" : "TOP {$limit}";
		$limit=$db->getType()=='mysql' ? "LIMIT {$limit}" : "";
		
		$query="SELECT {$top} OrderId, OrderProductIds, CreationDateTime, ModificationDateTime, Status, TrackingId, Message, JSONFilePath, RetryCount FROM Orders WHERE Status=:Status {$limit}";
		$params=['Status'=>$status];
		return $db->query($query, $params)->get();
	}

	public static function updateRetryCount(Database $db, int $orderId, int $retryCount)
	{
		$query="UPDATE Orders SET RetryCount=:RetryCount WHERE OrderId=:OrderId";
		$params=['RetryCount'=>$retryCount, 'OrderId'=>$orderId];
		$db->query($query, $params);
	}

	public static function DeleteOrder(Database $db, int $orderId) : void
	{
		$query="DELETE Orders WHERE OrderId=:OrderId";
		$params=['OrderId'=>$orderId];
		$result=$db->query($query, $params);
		Logger::info("Database: Order {$orderId} deleted!");
	}

	public static function sendJSONToSwitch(Database $db, array $configArray, int $orderId, string $payload, $retryCount=0): bool
	{
		$returnVal=false;
		$url = $configArray['switchURL'];
		$headerArray=["Content-Type: application/json; charset=utf-8"];
		$options = array('http' => array('method' => 'POST', 'ignore_errors' => true,  'header' => $headerArray, 'content'=>$payload));
		$context  = stream_context_create($options);
		$response = file_get_contents($url, false, $context);	// Send payload to Switch & check return value
		if($response===false)									// Connection could not be established or HTTP error header returned (4xx & 5xx)!
		{
			$errorsArray=error_get_last();
			Logger::error("Failed to send payload to Switch, error message was: {$errorsArray['message']}");	
			if(isset($http_response_header))	// we need to see if we got a response header (genuine error) or not (server not reachable), this will determine whether the job is set to error or retry
			{
				self::updateStatus($db, $configArray, $orderId, 'error', $errorsArray['message']);	// This is an error
				Logger::error("------------------------------ Order {$orderId} completed with errors ------------------------------");
			}
			else
			{
				self::updateStatus($db, $configArray, $orderId, 'retry', $errorsArray['message']);	// Connectivity or timeout error, mark order for retry
				Logger::warning("------------------------------ Order {$orderId} has not been sent to Switch ------------------------------");
			}
		}
		else
		{
			$responseObj=json_decode($response);
			if(is_object($responseObj) && property_exists($responseObj, 'status') )
			{
				self::updateStatus($db, $configArray, $orderId, strtolower($responseObj->status)==self::ERROR ? self::ERROR : self::PROCESSING, property_exists($responseObj, 'message') ? $responseObj->message : '');
				Logger::info("------------------------------ Order {$orderId} completed ".(strtolower($responseObj->status)==OrderWrapper::ERROR ? "with errors" : "successfully").(property_exists($responseObj, 'message') ? ' ,'.$responseObj->message : '')." ------------------------------");
				$returnVal==!(strtolower($responseObj->status)==self::ERROR);
			}
			else	// just received a non-json encoded response, consider it ok !
			{
				self::updateStatus($db, $configArray, $orderId, self::PROCESSING);
				Logger::info("------------------------------ Order {$orderId} successfully sent to Switch ------------------------------");
				$returnVal=true;
			}
		}
		if( !$returnVal)
		{
			self::updateRetryCount($db, $orderId, ++$retryCount);
		}
		return $returnVal;
	}
}
		