<?
	function get_valid_username($username){
		global $clients_info;
		foreach($clients_info as $i=>$client){
			if($client["username"] == $username){
				$matches = array();
				if(preg_match('#(\d+)$#', $username, $matches)){
					var_dump($matches[1]);
				}
			}
		}
		return true;
	}
	
	function get_all_users_in_channel($send_channel, $msg){
		$users = [];
		global $clients_info;
		foreach($clients_info as $sessionid=>$info){
			if($info["channel"] == $send_channel){
				$users[$sessionid] = $info;
			}
		}
		return $users;
	}
	
	//TO-DO: Be able to find a user by a small section of name entered
	function get_user_by_name($name){
		global $clients_info;
		return "";
	}
?>
