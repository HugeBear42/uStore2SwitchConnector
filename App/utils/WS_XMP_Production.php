<?php
/**
	A utility class that loosely implements some of the SOAP API Production functionality. All methods can throw an exception !
	2024-11-12	Genesis!
	
**/
namespace App\utils;

class WS_XMP_Production
{
	private object $productionWS;
	
	function __construct(private array $cfgArray)
	{
		$url=$cfgArray['baseURL'].str_ends_with($cfgArray['baseURL'], '/') ? '' : '/'; 
		$productionWS = new SoapClient($url.'uStoreWSAPI/ProductionWS.asmx?WSDL', array('trace'   => true, 'exceptions'=> true);
	}

	private function createParamsStub() : object
	{
		$params=new \stdClass;	
		$params->username = $this->cfgArray['apiUser'];		// uStore API user name 
		$params->password = $this->cfgArray['apiPass'];		// password
		return $params;
	}
	public function sendToProductionOneCopy(int $orderProductId) : void
	{
		$params=$this->createParamsStub();
		$params->orderProductId = $orderProductId;
		$this->productionWS->SendToProductionOneCopy($params);
	}

}
