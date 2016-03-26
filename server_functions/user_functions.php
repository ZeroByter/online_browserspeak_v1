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
	
	function get_user_by_name($name){
		$name = strtolower($name);
		
		global $clients_info;
		foreach($clients_info as $sessionid=>$info){
			if(strpos(strtolower($info["username"]), $name) !== false){
				return $info;
			}
		}
		return false;
	}
	
	function get_user_by_id($id){
		global $clients_info;
		foreach($clients_info as $sessionid=>$info){
			if($info["uid"] == $id){
				return $info;
			}
		}
		return false;
	}
?>
