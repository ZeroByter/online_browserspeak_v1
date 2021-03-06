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
	$read_limit = 2048;

	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

	socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

	socket_bind($socket, 0, $port);

	socket_listen($socket);
	
	$client_uid = 0;
	$clients = array($socket);
	$clients_info = array();
	$channels_array = [];
	
	function redo_channels_array(){
		global $channels_array;
		$channels_array = [];
		foreach(get_all_channels_by_order() as $key=>$value){
			if(isset($value->id)){
				$channels_array[$value->id] = [
					"id" => $value->id,
					"name" => $value->name,
					"listorder" => $value->listorder,
					"default" => $value->is_default,
					"subscribe_admin_only" => $value->subscribe_admin_only,
					"enter_admin_only" => $value->enter_admin_only,
					"requires_password" => $value->requires_password,
					"password" => $value->password,
				];
			}
		}
	}
	redo_channels_array();
	
	while(true) {
		$changed = $clients;
		socket_select($changed, $null, $null, 0, 1);
		
		if(in_array($socket, $changed)){
			$socket_new = socket_accept($socket);
			$header = socket_read($socket_new, $read_limit);
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
			while(socket_recv($changed_socket, $buf, $read_limit, 0) >= 1)
			{
				socket_getpeername($changed_socket, $ip);
				$received_text = unmask($buf);
				$ws_msg = json_decode($received_text);
				@$ws_type = $ws_msg->type;
				$debug = false;
				
				if($debug && isset($ws_type)){
					echo "message received: $ws_type\n";
				}
				
				if($ws_type == "test"){
					var_dump($channels_array);
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
				
				if($ws_type == "user_message"){
					$user_channel = get_sessionid_user_info(get_socket_sessionid($changed_socket))["channel"];
					$channel_props = $channels_array[$user_channel];
					$chat_message = $ws_msg->message;
					$username = get_sessionid_user_info(get_socket_sessionid($changed_socket))["username"];
					
					$chat_message = str_replace("<", "&lt;", $chat_message);
					$chat_message = str_replace(">", "&gt;", $chat_message);
					
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
				
				if($ws_type == "kick_user_server"){
					$username = get_sessionid_user_info(get_socket_sessionid($changed_socket))["username"];
					$is_admin = get_sessionid_user_info(get_socket_sessionid($changed_socket))["is_admin"];
					
					if($is_admin){
						$target_info = get_user_by_name($ws_msg->target_name);
						$response_text = mask(json_encode(array("type"=>"kick_message", "message"=>"Kicked from the server by $username.")));
						send_message_private($target_info["socket"], $response_text);
						
						$response_text = mask(json_encode(array("type"=>"remove_user", "id"=>$target_info["uid"])));
						send_message($response_text);
						
						echo "{$target_info["username"]} was kicked from the server by $username!\n";
						
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
				
				if($ws_type == "kick_user_channel"){
					$username = get_sessionid_user_info(get_socket_sessionid($changed_socket))["username"];
					$is_admin = get_sessionid_user_info(get_socket_sessionid($changed_socket))["is_admin"];
					
					if($is_admin){
						$target_info = get_user_by_name($ws_msg->target_name);
						$target_channel = $channels_array[$target_info["channel"]];
						$response_text = mask(json_encode(array("type"=>"kick_message", "message"=>"Kicked from the channel by $username.")));
						send_message_private($target_info["socket"], $response_text);
						echo "{$target_info["username"]} was kicked from the channel '" . get_channel_name($target_info["channel"]) . "' by $username!\n";
						if($target_channel["subscribe_admin_only"]){
							if(!$target_info["is_admin"]){
								$response_text = mask(json_encode(array("type"=>"clear_channel_users", "channel"=>$target_channel["id"])));
								send_message_private($target_info["socket"], $response_text);
							}
						}
						
						$clients_info[get_socket_sessionid($target_info["socket"])]["channel"] = get_default_channel();
						
						$clients_send_list = [];
						foreach($clients_info as $sessiondid=>$info){
							if($info["channel"] == get_default_channel()){
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
						
						$response_text = mask(json_encode(array("type"=>"remove_user", "id"=>$target_info["uid"])));
						send_message($response_text);
						$response_text = mask(json_encode(array("type"=>"client_active_channel", "channel"=>get_default_channel())));
						send_message_private($target_info["socket"], $response_text);
						$response_text = mask(json_encode(array("type"=>"user_change_channel", "users"=>$clients_send_list, "channel"=>get_default_channel())));
						send_message_can_subscribe($target_info["socket"], $channels_array[get_default_channel()], $response_text);
					}else{
						$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Invalid permissions for this command!")));
						send_message_private($target_info["socket"], $response_text);
					}
				}
				
				if($ws_type == "kick_all_from_channel"){
					$username = get_sessionid_user_info(get_socket_sessionid($changed_socket))["username"];
					$is_admin = get_sessionid_user_info(get_socket_sessionid($changed_socket))["is_admin"];
					
					if($is_admin){
						$target_channel = $ws_msg->channel;
						$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username kicked everyone from the channel '" . get_channel_name($target_channel) . "'")));
						send_message($response_text);
						
						echo "$username kicked everyone out of channel '" . get_channel_name($target_channel) . "'\n";
						
						foreach($clients_info as $sessiondid=>$info){
							if($info["channel"] == $target_channel){
								$clients_info[get_socket_sessionid($info["socket"])]["channel"] = get_default_channel();
								$response_text = mask(json_encode(array("type"=>"remove_user", "id"=>$info["uid"])));
								send_message($response_text);
								$response_text = mask(json_encode(array("type"=>"client_active_channel", "channel"=>get_default_channel())));
								send_message_private($info["socket"], $response_text);
								$response_text = mask(json_encode(array("type"=>"user_change_channel", "users"=>$clients_send_list, "channel"=>get_default_channel())));
								send_message_can_subscribe($info["socket"], $channels_array[get_default_channel()], $response_text);
							};
						}
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
				
				if($ws_type == "change_channel_name"){
					$user_info = $clients_info[get_socket_sessionid($changed_socket)];
					$username = $user_info["username"];
					$id = $ws_msg->id;
					$new_name = $ws_msg->name;
					$is_admin = $clients_info[get_socket_sessionid($changed_socket)]["is_admin"];
					if($is_admin){
						$target_channel = $channels_array[$id];
						$old_name = $channels_array[$id]["name"];
						$channels_array[$id]["name"] = $new_name;
						store_channel_name($id, $new_name);
						redo_channels_array();
						
						$response_text = mask(json_encode(array("type"=>$ws_type, "id"=>$id, "name"=>$new_name)));
						send_message($response_text);
						$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username changed channel's '$old_name' name to '$new_name'")));
						send_message($response_text);
						echo "$username changed channel's '$old_name' name to '$new_name'\n";
					}else{
						$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Invalid permissions for this command!")));
						send_message_private($changed_socket, $response_text);
					}
				}
				
				if($ws_type == "add_channel_after"){
					$user_info = $clients_info[get_socket_sessionid($changed_socket)];
					$is_admin = $user_info["is_admin"];
					$id = $ws_msg->id;
					$target_channel = $channels_array[$id];
					$name = $ws_msg->name;
					if($is_admin){
						$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"{$user_info["username"]} added channel '$name'")));
						send_message($response_text);
						echo "{$user_info["username"]} added channel '$name'\n";
						
						store_push_channels_down($target_channel["listorder"]);
						$insert_id = store_channel($name, $target_channel["listorder"]+1, false, false, false, true);
						
						$response_text = mask(json_encode(array("type"=>"add_channel_after", 
							"after_order"=>$target_channel["listorder"],
							"name"=>$name,
							"listorder"=>$target_channel["listorder"]+1,
							"id"=>$insert_id,
						)));
						send_message($response_text);
						
						redo_channels_array();
					}else{
						$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Invalid permissions for this command!")));
						send_message_private($changed_socket, $response_text);
					}
				}
				
				if($ws_type == "delete_channel"){
					$user_info = $clients_info[get_socket_sessionid($changed_socket)];
					$is_admin = $user_info["is_admin"];
					$id = $ws_msg->id;
					$target_channel = $channels_array[$id];
					if($is_admin){
						$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"{$user_info["username"]} deleted channel '{$target_channel["name"]}'")));
						send_message($response_text);
						echo "{$user_info["username"]} deleted channel '{$target_channel["name"]}'\n";
						
						$response_text = mask(json_encode(array("type"=>"remove_channel", "id"=>$id)));
						send_message($response_text);
						
						store_delete_channel($id);
						
						redo_channels_array();
					}else{
						$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Invalid permissions for this command!")));
						send_message_private($changed_socket, $response_text);
					}
				}
				
				if($ws_type == "make_channel_default"){
					$user_info = $clients_info[get_socket_sessionid($changed_socket)];
					$username = $user_info["username"];
					$is_admin = $user_info["is_admin"];
					$id = $ws_msg->id;
					$target_channel = $channels_array[$id];
					if($is_admin){
						$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username made '{$target_channel["name"]}' the default channel")));
						send_message($response_text);
						echo "$username made '{$target_channel["name"]}' the default channel\n";
						
						$response_text = mask(json_encode(array("type"=>$ws_type, "id"=>$id)));
						send_message($response_text);
						
						store_channel_make_default($id);
						
						redo_channels_array();
					}else{
						$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Invalid permissions for this command!")));
						send_message_private($changed_socket, $response_text);
					}
				}
				
				if($ws_type == "change_channel_password"){
					$user_info = $clients_info[get_socket_sessionid($changed_socket)];
					$username = $user_info["username"];
					$is_admin = $user_info["is_admin"];
					$id = $ws_msg->id;
					$target_channel = $channels_array[$id];
					$curr_password = $target_channel["password"];
					$new_password = $ws_msg->password;
					if($is_admin){
						$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username changed '{$target_channel["name"]}'s password to '****'")));
						send_message_but_local($changed_socket, $response_text);
						$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username changed '{$target_channel["name"]}'s password to '$new_password'")));
						send_message_private($changed_socket, $response_text);
						$response_text = mask(json_encode(array("type"=>$ws_type, "id"=>$id)));
						send_message($response_text);
						
						echo "$username changed '{$target_channel["name"]}'s password to '$new_password'\n";
						
						store_channel_change_password($id, $new_password);
						
						redo_channels_array();
					}else{
						$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Invalid permissions for this command!")));
						send_message_private($changed_socket, $response_text);
					}
				}
				
				if($ws_type == "remove_channel_password"){
					$user_info = $clients_info[get_socket_sessionid($changed_socket)];
					$username = $user_info["username"];
					$is_admin = $user_info["is_admin"];
					$id = $ws_msg->id;
					$target_channel = $channels_array[$id];
					if($is_admin){
						if($target_channel["requires_password"]){
							$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username removed '{$target_channel["name"]}'s password")));
							send_message($response_text);
							$response_text = mask(json_encode(array("type"=>$ws_type, "id"=>$id)));
							send_message($response_text);
							
							echo "$username removed '{$target_channel["name"]}'s password\n";
							
							store_channel_remove_password($id);
							
							redo_channels_array();
						}else{
							$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Channel already has no password!")));
							send_message_private($changed_socket, $response_text);
						}
					}else{
						$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Invalid permissions for this command!")));
						send_message_private($changed_socket, $response_text);
					}
				}
				
				if($ws_type == "toggle_admin_enter_only"){
					$user_info = $clients_info[get_socket_sessionid($changed_socket)];
					$username = $user_info["username"];
					$is_admin = $user_info["is_admin"];
					$id = $ws_msg->id;
					$target_channel = $channels_array[$id];
					if($is_admin){
						if($target_channel["enter_admin_only"]){
							$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username turned off entry for admins only in channel '{$target_channel["name"]}'")));
							send_message($response_text);
							echo "$username turned off entry for admins only in channel '{$target_channel["name"]}'\n";
						}else{
							$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username turned on entry for admins only in channel '{$target_channel["name"]}'")));
							send_message($response_text);
							echo "$username turned on entry for admins only in channel '{$target_channel["name"]}'\n";
						}
						
						$response_text = mask(json_encode(array("type"=>$ws_type, "id"=>$id, "state"=>!$target_channel["enter_admin_only"])));
						send_message($response_text);
						
						store_channel_toggle_admin_enter_only($id);
						
						redo_channels_array();
					}else{
						$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Invalid permissions for this command!")));
						send_message_private($changed_socket, $response_text);
					}
				}
				
				if($ws_type == "toggle_subscribe_enter_only"){
					$user_info = $clients_info[get_socket_sessionid($changed_socket)];
					$username = $user_info["username"];
					$is_admin = $user_info["is_admin"];
					$id = $ws_msg->id;
					$target_channel = $channels_array[$id];
					if($is_admin){
						if($target_channel["subscribe_admin_only"]){
							$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username turned off subscription for admins only in channel '{$target_channel["name"]}'")));
							send_message($response_text);
							echo "$username turned off subscription for admins only in channel '{$target_channel["name"]}'\n";
						}else{
							$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username turned on subscription for admins only in channel '{$target_channel["name"]}'")));
							send_message($response_text);
							echo "$username turned on subscription for admins only in channel '{$target_channel["name"]}'\n";
						}
						
						$response_text = mask(json_encode(array("type"=>$ws_type, "id"=>$id, "state"=>!$target_channel["subscribe_admin_only"])));
						send_message($response_text);
						
						store_channel_toggle_admin_subscribe_only($id);
						redo_channels_array();
						$target_channel = $channels_array[$id];
						
						$clients_send_list = [];
						foreach($clients_info as $sessiondid=>$info){
							if(!can_user_subscribe_channel($info, $target_channel)){
								$response_text = mask(json_encode(array("type"=>"clear_channel_users", "channel"=>$id)));
								send_message_private($info["socket"], $response_text);
							}else{
								$username = $info["username"];
								if($info["is_admin"]){
									$username = "<b>" . $info["username"] . " [Admin]</b>";
								}
								if($info["channel"] == $id){
									$clients_send_list[] = [
										"username" => $username,
										"id" => $info["uid"],
										"is_admin" => $info["is_admin"],
									];
								}
								$response_text = mask(json_encode(array("type"=>"get_users_in_channel", "users"=>$clients_send_list, "channel"=>$id)));
								send_message_private($info["socket"], $response_text);
							};
						}
					}else{
						$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Invalid permissions for this command!")));
						send_message_private($changed_socket, $response_text);
					}
				}
				
				if($ws_type == "user_change_name"){
					$identity = $clients_info[get_socket_sessionid($changed_socket)]["identity"];
					$uid = $clients_info[get_socket_sessionid($changed_socket)]["uid"];
					$old_username_fltr = $clients_info[get_socket_sessionid($changed_socket)]["username"];
					$old_username = decode_string($clients_info[get_socket_sessionid($changed_socket)]["username"]);
					$new_username = encode_string($ws_msg->name);
					$is_admin = $clients_info[get_socket_sessionid($changed_socket)]["is_admin"];
					
					if(strlen($new_username) < 48){ //Is the username more than 48 charachters?
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
					}else{
						$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Username is more than 48 charachters! (" . strlen($new_username) . ")")));
						send_message_private($changed_socket, $response_text);
					}
				}
				
				if($ws_type == "user_change_channel"){
					$user_info = $clients_info[get_socket_sessionid($changed_socket)];
					$uid = $user_info["uid"];
					$username = $user_info["username"];
					$old_channel = $user_info["channel"];
					$is_admin = $user_info["is_admin"];
					$target_channel = $channels_array[$ws_msg->channel];
					$channel_name = $target_channel["name"];
					$password = $ws_msg->password;
					$can_join = true;
					
					if($target_channel["requires_password"]){
						if($password == $target_channel["password"]){
							$can_join = true;
						}else{
							if($is_admin){
								if($password == "skip"){
									$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"Used skip password permission to skip channel password verificiation")));
									send_message_private($changed_socket, $response_text);
									$can_join = true;
								}else{
									$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Incorrect password to join channel ($password). As admin you can enter in 'skip' in the password field to skip password verification")));
									send_message_private($changed_socket, $response_text);
									echo "$username tried to join password-protected channel '$channel_name' and failed ($password)\n";
									$can_join = false;
								}
							}else{
								$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Incorrect password to join channel ($password)")));
								send_message_private($changed_socket, $response_text);
								echo "$username tried to join password-protected channel '$channel_name' and failed ($password)\n";
								$can_join = false;
							}
						}
					}else{
						$can_join = true;
					}
					
					if($can_join){
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
				}
				
				if($ws_type == "get_channels"){
					$send_channel_array = [];
					
					foreach($channels_array as $id=>$info){
						$send_channel_array[$id] = [
							"id"=>$info["id"],
							"name"=>$info["name"],
							"listorder"=>$info["listorder"],
							"default"=>$info["default"],
							"subscribe_admin_only"=>$info["subscribe_admin_only"],
							"enter_admin_only"=>$info["enter_admin_only"],
							"requires_password"=>$info["requires_password"],
						];
					}
					
					$channel = $clients_info[get_socket_sessionid($changed_socket)]["channel"];
					$response_text = mask(json_encode(array("type"=>$ws_type, "channels"=>$send_channel_array, "active_channel"=>$channel)));
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
			
			$buf = @socket_read($changed_socket, $read_limit, PHP_NORMAL_READ);
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
