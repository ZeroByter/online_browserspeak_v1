<?
	$array = [
		0 => [
				"prop1" => 1,
				"prop2" => 45.44,
				"lols" => "coooock",
			],
	];
	
	$array["* default *"] = [
		"*test" => "lol",
	];
	
	//var_dump($array);
	echo $array[0]["prop1"];
?>