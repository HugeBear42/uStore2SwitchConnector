<?php
/**
	A utility class that generates a delivery
	2021-11-12	Genesis!
	2024-11-07	Refactored class
	
**/
namespace App\utils;

class WS_XMP_ActualDelivery
{
	function __construct(private array $cfgArray)
	{}

	public function createDeliveryByOrderProducts(array $orderProductIdsArray, $deliveryDateTime, $trackingNumber="")//, $deliveryPrice=0, $deliveryServiceID)	// throws Exception!
	{
		//try
		//{
		$delivery = new \SoapClient($this->cfgArray['deliveryWSDL']);
		
		$params=new \stdClass;	
		$params->username = $this->cfgArray['apiUser'];	// uStore API user name 
		$params->password = $this->cfgArray['apiPass'];	// password
		$params->orderProductIds = $orderProductIdsArray;	// 
		$params->deliveryDatetime = $deliveryDateTime;		//
		$params->trackingNumber =$trackingNumber;			//
		//$params->deliveryPrice =$deliveryPrice;				//
		//$params->deliveryServiceID=$deliveryServiceID;		//

		return  $delivery->CreateDeliveryByOrderProducts($params)->CreateDeliveryByOrderProductsResult;

		//}
		//catch(Exception $e)
		//{
		//	Logger::error("createDeliveryByOrderProducts() threw an exception, orderProductIds: ".print_r($orderProductIdsArray, true).", deliveryDateTime: $deliveryDateTime, trackingNumber: $trackingNumber, exception: ". $e->getMessage());
		//	return false;
		//}
	}
/**
	public function ManualDeliveryArrived($deliveryId)
	{
		//try
		//{
			$delivery = new SoapClient($this->cfg->getActualDeliveryWSDL());
		
			$params=$this->cfg->getParamsStubObj();		// obj with userId & password filled-in
			$params->deliveryId = $deliveryId;			// 

			$result= $delivery->ManualDeliveryArrived($params);
			//echo print_r($result, true);
			return true;
		//}
		//catch(Exception $e)
		//{
		//	Logger::error("ManualDeliveryArrived() threw an exception, deliveryId: $deliveryId, exception: ". $e->getMessage());
		//	return false;
		//}
	}
**/

}
?>
