<?php
	include("/mysql/sql_support.php");
	include("/server_functions/server_functions.php");
	include("/server_functions/channel_functions.php");
	include("/server_functions/user_functions.php");
	include("/mysql/accounts.php");
	include("/mysql/channels.php");
	
	$host = "127.0.0.1";
	$port = "8080";
	$start_time = time();
	echo "started on $host:$port at $start_time\n";
	$null = NULL;
	
	$admin_pass = "_-zero"; //TO-DO: Better fucking security than this because this will go on GitHub and anyone could see this shit

	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

	socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

	socket_bind($socket, 0, $port);

	socket_listen($socket);
	
	$client_uid = 0;
	$clients = array($socket);
	$clients_info = array();
	$channels_array_i = -1;
	$channels_array = [];
	
	foreach(get_all_channels() as $key=>$value){
		if(isset($value->id)){
			$channels_array_i++;
			$channels_array[] = [
				"id" => $channels_array_i,
				"name" => $value->name,
				"default" => $value->is_default,
				"subscribe_admin_only" => $value->subscribe_admin_only,
				"enter_admin_only" => $value->enter_admin_only,
				"is_secure" => $value->is_secure,
			];
		}
	}

	while(true) {
		$changed = $clients;
		socket_select($changed, $null, $null, 0, 1);
		
		if(in_array($socket, $changed)){
			$socket_new = socket_accept($socket);
			$header = socket_read($socket_new, 1024);
			$user_identity = urldecode(explode("id:", strtok($header, "\r\n"))[1]);
			$username = urldecode(explode("username:", strtok($header, "\r\n"))[1]);
			if($username == ""){
				$username = get_identity($user_identity)->username;
			}
			if(urldecode(explode("username:", strtok($header, "\r\n"))[1]) != "" && urldecode(explode("username:", strtok($header, "\r\n"))[1]) != get_identity($user_identity)->username){
				store_identity_username($user_identity, urldecode(explode("username:", strtok($header, "\r\n"))[1]));
			}
			$is_admin = get_identity($user_identity)->is_admin;
			
			$clients[bin2hex(openssl_random_pseudo_bytes(12))] = $socket_new;
			
			$client_uid = $client_uid + 1;
			
			if($username == ""){
				$username = "Teamspeak User #$client_uid";
			}
			
			$clients_info[get_socket_sessionid($socket_new)] = [
				"socket" => $socket_new,
				"identity" => $user_identity,
				"uid" => $client_uid,
				"channel" => get_default_channel(),
				"username" => "$username",
				"is_admin" => $is_admin,
			];
			$connected_channel = $channels_array[$clients_info[get_socket_sessionid($socket_new)]["channel"]]["name"];
			
			if($username == "Teamspeak User #$client_uid"){
				store_identity_username($user_identity, $username);
			}
			if(!does_identity_exist($user_identity)){
				store_identity($clients_info[get_socket_sessionid($socket_new)]["username"], $clients_info[get_socket_sessionid($socket_new)]["identity"], $clients_info[get_socket_sessionid($socket_new)]["is_admin"]); //Store the profile in mysql
			}
			if($user_identity != ""){
				perform_handshaking($header, $socket_new, $host, $port);
			}
			
			socket_getpeername($socket_new, $ip);
			if($is_admin){
				$response = mask(json_encode(array("type"=>"user_connected", 
					"id"=>$clients_info[get_socket_sessionid($socket_new)]["uid"], 
					"username"=>"<b>" . $clients_info[get_socket_sessionid($socket_new)]["username"] . " [Admin]</b>",
					"channel_id"=>$clients_info[get_socket_sessionid($socket_new)]["channel"],
					"channel_name"=>$connected_channel,
					"is_admin"=>$clients_info[get_socket_sessionid($socket_new)]["is_admin"],
				)));
			}else{
				$response = mask(json_encode(array("type"=>"user_connected", 
					"id"=>$clients_info[get_socket_sessionid($socket_new)]["uid"], 
					"username"=>$clients_info[get_socket_sessionid($socket_new)]["username"],
					"channel_id"=>$clients_info[get_socket_sessionid($socket_new)]["channel"],
					"channel_name"=>$connected_channel,
					"is_admin"=>$clients_info[get_socket_sessionid($socket_new)]["is_admin"],
				)));

			}
			send_message($response); //Send to everyone a general message that someone connected
			
			$username = $clients_info[get_socket_sessionid($socket_new)]["username"];
			$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username connected to the server!")));
			send_message($response_text);
			
			echo "$ip connected to channel '$connected_channel'\n";
			
			$found_socket = array_search($socket, $changed);
			unset($changed[$found_socket]);
		}
		
		foreach($changed as $changed_socket){
			while(socket_recv($changed_socket, $buf, 1024, 0) >= 1)
			{
				socket_getpeername($changed_socket, $ip);
				$received_text = unmask($buf);
				$ws_msg = json_decode($received_text);
				@$ws_type = $ws_msg->type;
				
				if($ws_type == "test"){
					var_dump($clients_info);
				}
				
				if($ws_type == "user_disconnect"){
					echo "$ip disconnected\n";
					
					$response_text = mask(json_encode(array("type"=>"remove_user", "id"=>$clients_info[get_socket_sessionid($changed_socket)]["uid"])));
					send_message($response_text);
					
					$username = $clients_info[get_socket_sessionid($changed_socket)]["username"];
					$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username disconnected from the server!")));
					send_message($response_text);
					
					$client_info = $clients_info[get_socket_sessionid($changed_socket)];
					
					socket_shutdown($client_info["socket"], 2);
					socket_close($client_info["socket"]);
					$clients_info[get_socket_sessionid($changed_socket)] = null;
					unset($clients_info[get_socket_sessionid($changed_socket)]);
					
					$found_socket = array_search($changed_socket, $clients);
					unset($clients[$found_socket]);
					
				}
				
				/*if($ws_type == "admin_enter"){
					$username_fltr = decode_string($clients_info[get_socket_sessionid($changed_socket)]["username"]);
					$username = $clients_info[get_socket_sessionid($changed_socket)]["username"];
					$uid = $clients_info[get_socket_sessionid($changed_socket)]["uid"];
					$identity = $clients_info[get_socket_sessionid($changed_socket)]["identity"];
					if($ws_msg->password == $admin_pass){
						$is_admin = $clients_info[get_socket_sessionid($changed_socket)]["is_admin"];
						$clients_info[get_socket_sessionid($changed_socket)]["is_admin"] = !$is_admin;
						if($is_admin){
							echo "($ip) $username_fltr requested admin access and was granted (disable)\n";
							$response_text = mask(json_encode(array("type"=>$ws_type, "valid"=>true, "enabled"=>false)));
							send_message_private($changed_socket, $response_text);
							$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username went off from admin mode!")));
							send_message($response_text);
							$response_text = mask(json_encode(array("type"=>"admin_name_change", "uid"=>$uid, "enabled"=>false, "name"=>$username)));
							send_message($response_text);
							store_identity_is_admin($identity, false);
						}else{
							echo "($ip) $username_fltr requested admin access and was granted (enable)\n";
							$response_text = mask(json_encode(array("type"=>$ws_type, "valid"=>true, "enabled"=>true)));
							send_message_private($changed_socket, $response_text);
							$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username went into admin mode!")));
							send_message($response_text);
							$response_text = mask(json_encode(array("type"=>"admin_name_change", "uid"=>$uid, "enabled"=>true, "name"=>$username)));
							send_message($response_text);
							store_identity_is_admin($identity, true);
						}
					}else{
						echo "($ip) $username requested admin access and was denied (failed password was '$ws_msg->password')\n";
						$response_text = mask(json_encode(array("type"=>$ws_type, "valid"=>false, "enabled"=>false)));
						send_message_private($changed_socket, $response_text);
						store_identity_is_admin($identity, false);
					}
				}*/ //Disabled because why use passwords??
				
				if($ws_type == "user_message"){
					$user_channel = get_sessionid_user_info(get_socket_sessionid($changed_socket))["channel"];
					$channel_props = $channels_array[$user_channel];
					$chat_message = $ws_msg->message;
					$username = get_sessionid_user_info(get_socket_sessionid($changed_socket))["username"];
					
					if($channel_props["is_secure"]){
						$chat_message = str_replace("<", "&lt;", $chat_message);
						$chat_message = str_replace(">", "&gt;", $chat_message);
					}
					
					$response_text = mask(json_encode(array("type"=>$ws_type, "username"=>$username, "message"=>$chat_message)));
					send_message_to_channel($user_channel, $response_text);
					echo "($ip) $username: sent a message '" . $ws_msg->message . "' to channel '{$channels_array[$user_channel]["name"]}'\n";
				}
				
				if($ws_type == "global_message"){
					$username = get_sessionid_user_info(get_socket_sessionid($changed_socket))["username"];
					$is_admin = get_sessionid_user_info(get_socket_sessionid($changed_socket))["is_admin"];
					
					if($is_admin){
						$response_text = mask(json_encode(array("type"=>$ws_type, "username"=>$username, "message"=>$ws_msg->message)));
						send_message($response_text);
						echo "($ip) $ws_msg->username: sent a global message '" . $ws_msg->message . "' to channel '{$channels_array[$user_channel]["name"]}'\n";
					}else{
						$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Invalid permissions for this command!")));
						send_message_private($changed_socket, $response_text);
					}
				}
				
				if($ws_type == "kick_user"){
					$username = get_sessionid_user_info(get_socket_sessionid($changed_socket))["username"];
					$is_admin = get_sessionid_user_info(get_socket_sessionid($changed_socket))["is_admin"];
					
					if($is_admin){
						$target_info = get_user_by_name($ws_msg->target_name);
						$response_text = mask(json_encode(array("type"=>"kick_message", "message"=>"Kicked from the server.")));
						send_message_private($target_info["socket"], $response_text);
						
						$response_text = mask(json_encode(array("type"=>"remove_user", "id"=>$target_info["uid"])));
						send_message($response_text);
						
						echo "($ip) '{$target_info["username"]}' was kicked from the server by $username!\n";
						
						if(isset($target_info["socket"])){
							socket_shutdown($target_info["socket"], 2);
							socket_close($target_info["socket"]);
						}
						
						unset($clients_info[get_socket_sessionid($target_info["socket"])]);
						
						$found_socket = array_search($target_info["socket"], $clients);
						unset($clients[$found_socket]);
					}else{
						$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Invalid permissions for this command!")));
						send_message_private($target_info["socket"], $response_text);
					}
				}
				
				if($ws_type == "bring_user"){
					$username = get_sessionid_user_info(get_socket_sessionid($changed_socket))["username"];
					$is_admin = get_sessionid_user_info(get_socket_sessionid($changed_socket))["is_admin"];
					
					if($is_admin){
						$target_info = get_user_by_name($ws_msg->target_name);
						$caller_channel = get_sessionid_user_info(get_socket_sessionid($changed_socket))["channel"];
						$old_target_channel = $target_info["channel"];
						
						if($target_info["socket"] == $changed_socket){
							$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"You can not bring your self!")));
							send_message_private($changed_socket, $response_text);
							break;
						}
						
						$clients_info[get_socket_sessionid($target_info["socket"])]["channel"] = $caller_channel;
						
						$clients_send_list = [];
						
						foreach($clients_info as $sessiondid=>$info){
							if($info["channel"] == $caller_channel){
								$username = $info["username"];
								if($info["is_admin"]){
									$username = "<b>" . $info["username"] . " [Admin]</b>";
								}
								$clients_send_list[] = [
									"username" => $username,
									"id" => $info["uid"],
								];
							};
						}
						
						$response_text = mask(json_encode(array("type"=>"client_active_channel", "channel"=>$caller_channel)));
						send_message_private($target_info["socket"], $response_text);
						$response_text = mask(json_encode(array("type"=>"remove_user", "id"=>$target_info["uid"])));
						send_message($response_text);
						$response_text = mask(json_encode(array("type"=>"user_change_channel", "users"=>$clients_send_list, "channel"=>$caller_channel)));
						send_message_can_subscribe($target_info["socket"], $channels_array[$caller_channel], $response_text);
						
						if(!can_user_subscribe_channel($target_info["socket"], $channels_array[$old_target_channel])){
							$response_text = mask(json_encode(array("type"=>"clear_channel_users", "channel"=>$old_target_channel)));
							send_message_private($target_info["socket"], $response_text);
						}
					}else{
						$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Invalid permissions for this command!")));
						send_message_private($changed_socket, $response_text);
					}
				}
				
				if($ws_type == "pmsg_user"){
					$username = get_sessionid_user_info(get_socket_sessionid($changed_socket))["username"];
					
					if(is_numeric($ws_msg->target_name)){
						$target_info = get_user_by_id($ws_msg->target_name);
					}else{
						$target_info = get_user_by_name($ws_msg->target_name);
					}
					$target_username = $target_info["username"];
					$message = $ws_msg->message;
					
					$response_text = mask(json_encode(array("type"=>"private_message", "username"=>$username, "message"=>$message)));
					send_message_private($target_info["socket"], $response_text);
					$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"You to $target_username: $message")));
					send_message_private($changed_socket, $response_text);
				}
				
				if($ws_type == "get_own_info"){
					$client_info = $clients_info[get_socket_sessionid($changed_socket)];
					
					$clients_send_list = [
						"username" => $client_info["username"],
						"uid" => $client_info["uid"],
						"channel" => $client_info["channel"],
						"is_admin" => $client_info["is_admin"],
					];
					
					$response_text = mask(json_encode(array("type"=>$ws_type, "client_info"=>$clients_send_list)));
					send_message_private($changed_socket, $response_text);
				}
				
				if($ws_type == "user_change_name"){
					$identity = $clients_info[get_socket_sessionid($changed_socket)]["identity"];
					$uid = $clients_info[get_socket_sessionid($changed_socket)]["uid"];
					$old_username_fltr = $clients_info[get_socket_sessionid($changed_socket)]["username"];
					$old_username = decode_string($clients_info[get_socket_sessionid($changed_socket)]["username"]);
					$new_username = encode_string($ws_msg->name);
					$is_admin = $clients_info[get_socket_sessionid($changed_socket)]["is_admin"];
					if($old_username == ""){
						echo "($ip) set username to '$ws_msg->name'\n";
					}else{
						echo "($ip) $old_username: switched username to '$ws_msg->name'\n";
						$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"'$old_username_fltr' switched username to '$new_username'")));
						send_message($response_text);
					}
					$clients_info[get_socket_sessionid($changed_socket)]["username"] = $new_username;
					
					if($is_admin){
						$response_text = mask(json_encode(array("type"=>$ws_type, "old_name"=>$old_username, "new_name"=>"<b>$new_username [Admin]</b>", "id"=>$uid)));
					}else{
						$response_text = mask(json_encode(array("type"=>$ws_type, "old_name"=>$old_username, "new_name"=>$new_username, "id"=>$uid)));
					}
					send_message($response_text);
					store_identity_username($identity, $new_username);
				}
				
				if($ws_type == "user_change_channel"){
					$uid = $clients_info[get_socket_sessionid($changed_socket)]["uid"];
					$username = $clients_info[get_socket_sessionid($changed_socket)]["username"];
					$old_channel = $clients_info[get_socket_sessionid($changed_socket)]["channel"];
					$channel_name = $channels_array[$ws_msg->channel]["name"];
					
					if(can_user_join_channel($clients_info[get_socket_sessionid($changed_socket)], $channels_array[$ws_msg->channel])){
						$clients_info[get_socket_sessionid($changed_socket)]["channel"] = $ws_msg->channel;
						echo "($ip) $username: switched to channel '$channel_name'\n";
						$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username joined channel '$channel_name'")));
						send_message($response_text);
					}else{
						$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Invalid permissions to connect to channel '$channel_name'")));
						send_message_private($changed_socket, $response_text);
						echo "($ip) $username tried to switch to channel '$ws_msg->channel' and was denied\n";
					}
					
					$clients_send_list = [];
					
					foreach($clients_info as $sessiondid=>$info){
						if($info["channel"] == $ws_msg->channel){
							$username = $info["username"];
							if($info["is_admin"]){
								$username = "<b>" . $info["username"] . " [Admin]</b>";
							}
							$clients_send_list[] = [
								"username" => $username,
								"id" => $info["uid"],
								"is_admin" => $info["is_admin"],
							];
						};
					}
					
					if(can_user_join_channel($clients_info[get_socket_sessionid($changed_socket)], $channels_array[$ws_msg->channel])){
						$response_text = mask(json_encode(array("type"=>"client_active_channel", "channel"=>$ws_msg->channel)));
						send_message_private($changed_socket, $response_text);
						$response_text = mask(json_encode(array("type"=>"remove_user", "id"=>$uid)));
						send_message($response_text);
						$response_text = mask(json_encode(array("type"=>$ws_type, "users"=>$clients_send_list, "channel"=>$ws_msg->channel)));
						send_message_can_subscribe($clients_info[get_socket_sessionid($changed_socket)], $channels_array[$ws_msg->channel], $response_text);
						if(!can_user_subscribe_channel($clients_info[get_socket_sessionid($changed_socket)], $channels_array[$old_channel])){
							$response_text = mask(json_encode(array("type"=>"clear_channel_users", "channel"=>$old_channel)));
							send_message_private($changed_socket, $response_text);
						}
					}
				}
				
				if($ws_type == "get_channels"){
					$channel = $clients_info[get_socket_sessionid($changed_socket)]["channel"];
					$xss_protected = $channels_array[$channel]["is_secure"];
					$response_text = mask(json_encode(array("type"=>$ws_type, "channels"=>$channels_array, "active_channel"=>$channel)));
					send_message_private($changed_socket, $response_text);
					echo "($ip) requested to get a list of channels\n";
				}
				
				if($ws_type == "get_users_in_channels"){
					$clients_send_list = [];
					
					foreach($clients_info as $sessiondid=>$info){
						if(can_user_subscribe_channel($clients_info[get_socket_sessionid($changed_socket)], $channels_array[$info["channel"]])){
							$username = $info["username"];
							if($info["is_admin"]){
								$username = "<b>" . $info["username"] . " [Admin]</b>";
							}
							$clients_send_list[] = [
								"username" => $username,
								"channel" => $info["channel"],
								"id" => $info["uid"],
								"is_admin" => $info["is_admin"],
							];
						}
					};
					
					$response_text = mask(json_encode(array("type"=>$ws_type, "users"=>$clients_send_list)));
					send_message_private($changed_socket, $response_text);
					echo "($ip) requested to get a list of users in channels\n";
				}
				break 2;
			}
			
			$buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
			if($buf === false){
				socket_getpeername($changed_socket, $ip);
				
				$response_text = mask(json_encode(array("type"=>"remove_user", "id"=>$clients_info[get_socket_sessionid($changed_socket)]["uid"])));
				send_message($response_text);
				
				$username = $clients_info[get_socket_sessionid($changed_socket)]["username"];
				$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username disconnected from the server!")));
				send_message($response_text);
				
				unset($clients_info[get_socket_sessionid($changed_socket)]);
				
				$found_socket = array_search($changed_socket, $clients);
				unset($clients[$found_socket]);
				
				echo "$ip disconnected\n";
			}
		}
	}
	socket_close($socket);
	
	function encode_string($string){
		$string = str_replace("<", "&lt;", $string);
		$string = str_replace(">", "&gt;", $string);
		return $string;
	}
	
	function decode_string($string){
		$string = str_replace("&lt;", "<", $string);
		$string = str_replace("&gt;", ">", $string);
		return $string;
	}
?>