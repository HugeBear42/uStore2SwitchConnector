<?php
/**
	A utility class that generates a delivery using the uStore SOAP API. All methods can throw an exception !
	2021-11-12	Genesis!
	2024-11-07	Refactored class
	
**/
namespace App\utils;

class WS_XMP_ActualDelivery
{
	private object $deliveryWS;
	
	function __construct(private array $cfgArray)
	{
		$url=$cfgArray['baseURL'].str_ends_with($cfgArray['baseURL'], '/') ? '' : '/'; 
		$deliveryWS = new SoapClient($url.'uStoreWSAPI/ActualDeliveryWS.asmx?WSDL', array('trace'   => true, 'exceptions'=> true);
	}

	private function createParamsStub() : object
	{
		$params=new \stdClass;	
		$params->username = $this->cfgArray['apiUser'];		// uStore API user name 
		$params->password = $this->cfgArray['apiPass'];		// password
		return $params;
	}
	public function createDeliveryByOrderProducts(array $orderProductIdsArray, string $deliveryDateTime, string $trackingNumber='', float $deliveryPrice=0.0, int $deliveryServiceID) : int
	{
		$params=$this->createParamsStub();
		$params->orderProductIds = $orderProductIdsArray;
		$params->deliveryDatetime = $deliveryDateTime;
		$params->trackingNumber =$trackingNumber;
		$params->deliveryPrice =$deliveryPrice;
		$params->deliveryServiceID=$deliveryServiceID;
		return  $this->deliveryWS->CreateDeliveryByOrderProducts($params)->CreateDeliveryByOrderProductsResult;
	}

	public function manualDeliveryArrived(int $deliveryId) : void
	{
		$params=$this->createParamsStub();
		$params->deliveryId = $deliveryId;
		$this->deliveryWS->ManualDeliveryArrived($params);
	}
	
	public function getActualDelivery(int $actualDeliveryId) : object
	{
		$params=$this->createParamsStub();
		$params->actualDeliveryId = $actualDeliveryId;
		return $this->deliveryWS->GetActualDelivery($params);
	}
	
}

