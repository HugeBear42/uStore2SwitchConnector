<?php
/*
	ApplicationException.php © 2021 frank@xmpie.com 
	Simple Exception class
	
	v1.00 of 2021-11-22	Genesis

*/
namespace App\utils;

class ApplicationException extends \Exception
{
	public function errorMessage()
	{
		$errorMsg = 'Error on line '.$this->getLine().' in '.$this->getFile().': '.$this->getMessage();
		return $errorMsg;
	}
}

?>