<?php

return
[
	'debug'=>true,
	'db'=>
	[
		'type'=> 'sqlsrv',
		'connection' =>
		[
			'host' => 'localhost',
			'port' => '1433',
			'dbname' => 'SwitchConnector',
			'charset' => 'utf8mb4'
		],
		'user' => 'SwitchUser',
		'pass' => '****'
	],
	'switch'=>
	[
		"dataTransfer"=>"pushToSwitch",
		"switchURL2" => 'http://switch_server:51088/scripting/uStore',
		
		"tmpDir" =>"C:/websites/switch/Data/tmp/",
		"sampleFile" =>"C:/websites/switch/Data/samples/43235.xml",
		"allowStatusFeedback" => true
	],
	'uStore' =>
	[
		"apiUser" => "api@ustore.xmpie.net",
		"apiPass"=>"*****",
		"baseURL" => "https://manchester.xmpie.net/",
	]
];