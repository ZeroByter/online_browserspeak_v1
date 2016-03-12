<?
	function get_default_channel(){
		global $channels_array;
		foreach($channels_array as $i=>$channel){
			if($channel["default"]){
				return $i;
			}
		}
	}
?>