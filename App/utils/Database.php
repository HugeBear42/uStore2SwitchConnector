<?php
namespace App\utils;
use PDO;
use ApplicationException;

class Database
{
    public $connection;
    public $statement;
	private array $optionsArray = [];
	private ?string $type=null;
	
	
    public function __construct(array $config)
    {
		$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];	// Throw exception on error, return assoc arrays!
		$dsn='';
		if($config['type']==='sqlsrv')
		{
			$dsn=$config['type'].":Server={$config['connection']['host']}".(array_key_exists("port",$config['connection']) ? ",{$config['connection']['port']}" : '').";Database={$config['connection']['dbname']}";
			if( defined('PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE'))
			{	$this->optionsArray=[PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL, PDO::SQLSRV_ATTR_CURSOR_SCROLL_TYPE => PDO::SQLSRV_CURSOR_BUFFERED];	}
		}
		else if($config['type']==='mysql')
		{	$dsn=$config['type'].':'.http_build_query($config['connection'], '', ';');	}
		else
		{	throw new ApplicationException("Unsupported database type {$config['type']}");	}
	
        $this->connection = new PDO($dsn, $config['user'], $config['pass'], $options);
		$this->type=$config['type'];
    }

    public function query(string $query, array $params=[]) : Database
    {
        $this->statement=$this->connection->prepare($query, $this->optionsArray);
        $this->statement->execute($params);
        return $this;
    }
	
	public function getType() : string
	{	return $this->type;	}
	public function lastInsertId() : int
	{	return $this->connection->lastInsertId();	}
	
	public function __toString() : string
	{
		return print_r($this->connection, true).", ".print_r($this->statement, true);
	}
	
    public function find()
    {
        return $this->statement->fetch();
    }

    public function findOrFail()
    {
        $result=$this->find();
        if( !$result)
        {   exit(-1);  }
        return $result;
    }

    public function get()
    {
        return $this->statement->fetchAll();
    }

}