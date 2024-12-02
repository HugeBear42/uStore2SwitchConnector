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
		'pass' => 'ebwkTmpqQnYx38I'
	],
	'switch'=>
	[
		"dataTransfer"=>"pushToSwitch",
		"_switchURL" => 'http://35.195.165.100:51088/scripting/XMPie',
		"switchURL2" => 'http://35.195.165.100:51088/scripting/uStore',
		"switchURL" => 'https://manchesterY.xmpie.net/switch/test/testJobOk.php',
		"defaultDomain" => "https://marketingx.xmpie.net/",
		"domains" => 
		[
			"marketingx@xmpie.com" => "https://marketingx.xmpie.net/",
			"Webhooks" => "https://manchester.xmpie.net/"
		],
		"tmpDir" =>"C:/websites/switch/Data/tmp/",
		"sampleFile" =>"C:/websites/switch/Data/samples/43235.xml",
		"uStoreAPI" =>
		[
			"deliveryWSDL" => "https://marketingx.xmpie.net/uStoreWSAPI/ActualDeliveryWS.asmx?WSDL",
			"apiUser" => "api@ustore.xmpie.net",
			"apiPass"=>"!Ap1user@"
		]
	]
];