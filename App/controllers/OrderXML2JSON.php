<?php
/*
	OrderXML2JSON.php © 2024 frank@xmpie.com 
	an object used to parse OrderXML files	
	
	v1.00 of 2024-11-20	Genesis

*/

namespace App\controllers;
use App\utils\ApplicationException;
use App\utils\Logger;
//require_once(__DIR__."/../common/ApplicationException.php");

class OrderXML2JSON
{
	function __construct()
	{}
	
	// Following function found here: // https://outlandish.com/blog/tutorial/xml-to-json/
	// It is more powerful than simply using the json_encode() as it will convert all attributes.
	// Empty tags are returned as empty arrays, this should be further filtered & replaced by empty strings
	public function  xmlToArray($xml, $options = array()) : array
	{
		$defaults = array(
			'namespaceSeparator' => ':',//you may want this to be something other than a colon
			'attributePrefix' => '@',   //to distinguish between attributes and nodes with the same name
			'alwaysArray' => array(),   //array of xml tag names which should always become arrays
			'autoArray' => true,        //only create arrays for tags which appear more than once
			'textContent' => '$',       //key used for the text content of elements
			'autoText' => true,         //skip textContent key if node has no attributes or child nodes
			'keySearch' => false,       //optional search and replace on tag and attribute names
			'keyReplace' => false       //replace values for above search values (as passed to str_replace())
		);
		$options = array_merge($defaults, $options);
		$namespaces = $xml->getDocNamespaces();
		$namespaces[''] = null; //add base (empty) namespace
 
	//get attributes from all namespaces
		$attributesArray = array();
		foreach ($namespaces as $prefix => $namespace) {
			foreach ($xml->attributes($namespace) as $attributeName => $attribute) {
			//replace characters in attribute name
				if ($options['keySearch'])
					$attributeName = str_replace($options['keySearch'], $options['keyReplace'], $attributeName);
				$attributeKey = $options['attributePrefix'] . ($prefix ? $prefix . $options['namespaceSeparator'] : '') . $attributeName;
				$attributesArray[$attributeKey] = (string)$attribute;
			}
		}
 
    //get child nodes from all namespaces
		$tagsArray = array();
		foreach ($namespaces as $prefix => $namespace) {
			foreach ($xml->children($namespace) as $childXml) {
			//recurse into child nodes
				$childArray = $this->xmlToArray($childXml, $options);
				//  list($childTagName, $childProperties) = each($childArray);	// Deprecated in PHP7, removed from PHP8
				$key=array_key_first($childArray);	// Updated 2024-11-20
				$childTagName = $key;				// Updated 2024-11-20
				$childProperties=$childArray[$key];	// Updated 2024-11-20
 
			//replace characters in tag name
				if ($options['keySearch'])
					$childTagName = str_replace($options['keySearch'], $options['keyReplace'], $childTagName);
			//add namespace prefix, if any
				if ($prefix)
					$childTagName = $prefix . $options['namespaceSeparator'] . $childTagName;
				
				if (!isset($tagsArray[$childTagName])) {
			//only entry with this key
			//test if tags of this type should always be arrays, no matter the element count
					$tagsArray[$childTagName] =
					in_array($childTagName, $options['alwaysArray']) || !$options['autoArray'] ? array($childProperties) : $childProperties;
				}
				elseif (is_array($tagsArray[$childTagName]) && array_keys($tagsArray[$childTagName])=== range(0, count($tagsArray[$childTagName]) - 1)) {
				//key already exists and is integer indexed array
					$tagsArray[$childTagName][] = $childProperties;
				}
				else {
				//key exists so convert to integer indexed array with previous value in position 0
					$tagsArray[$childTagName] = array($tagsArray[$childTagName], $childProperties);
				}
			}
		}
		
	//get text content of node
		$textContentArray = array();
		$plainText = trim((string)$xml);
		if ($plainText !== '') $textContentArray[$options['textContent']] = $plainText;
		
	//stick it all together
		$propertiesArray = !$options['autoText'] || $attributesArray || $tagsArray || ($plainText === '') ? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;
		
	//return node as array
		return array( $xml->getName() => $propertiesArray );
	}
	
	public function filterArray(array $array) : array	// Convert any empty arrays to empty strings, build the downloadURLs and add them to the orderproducts
	{
		foreach($array as $key=>&$value)	// pass the value by reference so that it can be updated !
		{
			if( is_array($value) )
			{
				if( empty($value) )
				{	$value="";
				//$array[$key]='xxx';
			//	error_log("found empty value for key {$key} in array ".print_r($array, true));
				}
				else
				{	$value=$this->filterArray($value);}
			}
		}
		
		return $array;
	}
	
	function convert(object $xml, array $configArray = [], bool $returnAsArray=false) : string|array
	{
		//$jsonPayload="";
		libxml_use_internal_errors(true);	// Throw exception in case of parsing errors.
	//	$xml=simplexml_load_file($xmlFile);
		if($xml)
		{
		// We first parse the main order structure
			if( !(property_exists($xml, 'Order') && ($xml->Order[0] instanceof \SimpleXMLElement) ) )	// Make sure this is an order XML !
			{	throw new ApplicationException("Invalid XML file, expected root tag to be OrderXml!");	}
			$defaults=['attributePrefix'=>'', 'textContent'=>'value'];
			$array=$this->xmlToArray($xml, $defaults );
			$array=$this->filterArray($array);
		// Remove the encapsulating 'OrderXML' array
			if( array_key_exists('OrderXml', $array) && is_array($array['OrderXml']) && sizeof($array['OrderXml'])===1 && array_key_exists('Order', $array['OrderXml']) )
			{	$array=$array['OrderXml']; }
			
			
		//	error_log(print_r($array, true));
			$domain="http://localhost/";
			$storeExternalId=$array['Order']['Store'][$defaults['attributePrefix'].'externalId'];
			if(array_key_exists($storeExternalId, $configArray['domains']))
			{	$domain=$configArray['domains'][$storeExternalId];	}
			
			Logger::fine("StoreExternalId: {$storeExternalId}, domain: {$domain}");
			if(array_key_exists('id', $array['Order']['OrderProducts']['OrderProduct']))	// It only a single order product in the order, nest it in an array for same presentation or order with multiple products !
			{
				Logger::fine("Converting OrderProduct from object to array (only 1 OrderProduct in the order)");
				$tmpArray=$array['Order']['OrderProducts']['OrderProduct'];
				unset($array['Order']['OrderProducts']['OrderProduct']);
				$array['Order']['OrderProducts']['OrderProduct']=[];
				$array['Order']['OrderProducts']['OrderProduct'][0]=$tmpArray;
			}
			foreach($array['Order']['OrderProducts']['OrderProduct'] as &$orderProduct)
			{
				//error_log("ORDER PRODUCT: ".print_r($orderProduct, true));
				$downloadURL="{$domain}uStore/Controls/SDK/OrderOutputProxy.ashx?token=".$orderProduct[$defaults['attributePrefix'].'OutputToken'];
				$orderProduct['downloadURL']=$downloadURL;
				Logger::fine("Added download URL: {$downloadURL}");
			}
			return $returnAsArray ? $array : json_encode($array);
		//	$jsonPayload=json_encode($array);
		//	error_log($jsonPayload);
		//	exit;
		}
		else
		{	throw new ApplicationException("Invalid XML file, failed to convert {$xmlFile} to XML!");	}
		//return $jsonPayload;	
	}
	

}
?>