<?php
	include("server_functions.php");
	include("channel_functions.php");
	include("user_functions.php");
	
	$host = "127.0.0.1";
	$port = "8080";
	$start_time = time();
	echo "started on $host:$port at $start_time\n";
	$null = NULL;
	
	$admin_pass = "_-zeroo"; //TO-DO: Better fucking security than this because this will go on GitHub and anyone could see this shit

	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

	socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

	socket_bind($socket, 0, $port);

	socket_listen($socket);
	
	$client_uid = 0;
	$clients = array($socket);
	$clients_info = array();
	$channels_array = [];
	$channels_array_i = 0;
	$channels_array[] = [
		"id" => $channels_array_i,
		"name" => "* Default *",
		"default" => true,
		"subscribe_admin_only" => false,
		"enter_admin_only" => false,
		"is_secure" => true,
	];
	$channels_array_i++;
	$channels_array[] = [
		"id" => $channels_array_i,
		"name" => "Lobby #1",
		"default" => false,
		"subscribe_admin_only" => false,
		"enter_admin_only" => false,
		"is_secure" => true,
	];
	$channels_array_i++;
	$channels_array[] = [
		"id" => $channels_array_i,
		"name" => "Lobby #2",
		"default" => false,
		"subscribe_admin_only" => false,
		"enter_admin_only" => false,
		"is_secure" => true,
	];
	$channels_array_i++;
	$channels_array[] = [
		"id" => $channels_array_i,
		"name" => "Lobby #3",
		"default" => false,
		"subscribe_admin_only" => false,
		"enter_admin_only" => false,
		"is_secure" => true,
	];
	$channels_array_i++;
	$channels_array[] = [
		"id" => $channels_array_i,
		"name" => "Random shit channel (NOT XSS SECURE)",
		"default" => false,
		"subscribe_admin_only" => false,
		"enter_admin_only" => false,
		"is_secure" => false,
	];
	$channels_array_i++;
	$channels_array[] = [
		"id" => $channels_array_i,
		"name" => "Owner/Admin channel",
		"default" => false,
		"subscribe_admin_only" => true,
		"enter_admin_only" => true,
		"is_secure" => true,
	];
	$channels_array_i++;
	$channels_array[] = [
		"id" => $channels_array_i,
		"name" => "* Testing channel *",
		"default" => false,
		"subscribe_admin_only" => true,
		"enter_admin_only" => true,
		"is_secure" => true,
	];

	while(true) {
		$changed = $clients;
		socket_select($changed, $null, $null, 0, 1);
		
		if(in_array($socket, $changed)){
			$socket_new = socket_accept($socket);
			$clients[bin2hex(openssl_random_pseudo_bytes(12))] = $socket_new;
			
			$client_uid = $client_uid + 1;
			$clients_info[get_socket_sessionid($socket_new)] = [
				"socket" => $socket_new,
				"uid" => $client_uid,
				"channel" => get_default_channel(),
				"username" => "No name #$client_uid",
				"is_admin" => false,
			];
			$connected_channel = $channels_array[$clients_info[get_socket_sessionid($socket_new)]["channel"]]["name"];
			
			$header = socket_read($socket_new, 1024);
			perform_handshaking($header, $socket_new, $host, $port);
			
			socket_getpeername($socket_new, $ip);
			//TO-DO: Make it so this message sends both uid of client and the channel that he connected to
			$response = mask(json_encode(array("type"=>"user_connected", 
				"id"=>$clients_info[get_socket_sessionid($socket_new)]["uid"], 
				"username"=>$clients_info[get_socket_sessionid($socket_new)]["username"],
				"channel_id"=>$clients_info[get_socket_sessionid($socket_new)]["channel"],
				"channel_name"=>$connected_channel,
				)));
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
				if($ws_type == "admin_enter"){
					$username = $clients_info[get_socket_sessionid($changed_socket)]["username"];
					if($ws_msg->password == $admin_pass){
						$is_admin = $clients_info[get_socket_sessionid($changed_socket)]["is_admin"];
						$clients_info[get_socket_sessionid($changed_socket)]["is_admin"] = !$is_admin;
						if($is_admin){
							echo "($ip) $username requested admin access and was granted (disable)\n";
							$response_text = mask(json_encode(array("type"=>$ws_type, "valid"=>true, "enabled"=>false)));
							send_message_private($changed_socket, $response_text);
							$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username went off from admin mode!")));
							send_message($response_text);
						}else{
							echo "($ip) $username requested admin access and was granted (enable)\n";
							$response_text = mask(json_encode(array("type"=>$ws_type, "valid"=>true, "enabled"=>true)));
							send_message_private($changed_socket, $response_text);
							$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"$username went into admin mode!")));
							send_message($response_text);
						}
					}else{
						echo "($ip) $username requested admin access and was denied\n";
						$response_text = mask(json_encode(array("type"=>$ws_type, "valid"=>false, "enabled"=>false)));
						send_message_private($changed_socket, $response_text);
					}
				}
				if($ws_type == "user_message"){
					$user_channel = get_sessionid_user_info(get_socket_sessionid($changed_socket))["channel"];
					$channel_props = $channels_array[$user_channel];
					$chat_message = $ws_msg->message;
					
					if($channel_props["is_secure"]){
						$chat_message = str_replace("<", "&lt;", $chat_message);
						$chat_message = str_replace(">", "&gt;", $chat_message);
					}
					
					$response_text = mask(json_encode(array("type"=>$ws_type, "username"=>$ws_msg->username, "message"=>$chat_message)));
					send_message_to_channel($user_channel, $response_text);
					echo "($ip) $ws_msg->username: sent a message '" . $ws_msg->message . "' to channel '{$channels_array[$user_channel]["name"]}'\n";
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
						echo "'{$target_info["username"]}' was kicked from the server by $username!\n";
						
						socket_shutdown($target_info["socket"], 2);
						socket_close($target_info["socket"]);
						unset($clients_info[get_socket_sessionid($target_info["socket"])]);
						
						$found_socket = array_search($target_info["socket"], $clients);
						unset($clients[$found_socket]);
					}else{
						$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Invalid permissions for this command!")));
						send_message_private($changed_socket, $response_text);
					}
				}
				if($ws_type == "bring_user"){
					$username = get_sessionid_user_info(get_socket_sessionid($changed_socket))["username"];
					$is_admin = get_sessionid_user_info(get_socket_sessionid($changed_socket))["is_admin"];
					
					if($is_admin){
						//TO-DO: Bring user to channel function
					}else{
						$response_text = mask(json_encode(array("type"=>"error_message", "message"=>"Invalid permissions for this command!")));
						send_message_private($changed_socket, $response_text);
					}
				}
				if($ws_type == "user_change_name"){
					$uid = $clients_info[get_socket_sessionid($changed_socket)]["uid"];
					$old_username = $clients_info[get_socket_sessionid($changed_socket)]["username"];
					if($old_username == ""){
						echo "($ip) set username to '$ws_msg->name'\n";
					}else{
						echo "($ip) $old_username: switched username to '$ws_msg->name'\n";
						$response_text = mask(json_encode(array("type"=>"system_message", "message"=>"'$old_username' switched username to '$ws_msg->name'")));
						send_message($response_text);
					}
					$clients_info[get_socket_sessionid($changed_socket)]["username"] = $ws_msg->name;
					
					$response_text = mask(json_encode(array("type"=>$ws_type, "old_name"=>$old_username, "new_name"=>$ws_msg->name, "id"=>$uid)));
					send_message($response_text);
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
							$clients_send_list[] = [
								"username" => $info["username"],
								"id" => $info["uid"],
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
					$response_text = mask(json_encode(array("type"=>$ws_type, "channels"=>$channels_array, "active_channel"=>$channel)));
					send_message_private($changed_socket, $response_text);
					echo "($ip) requested to get a list of channels\n";
				}
				if($ws_type == "get_users_in_channels"){
					$clients_send_list = [];
					
					foreach($clients_info as $sessiondid=>$info){
						if(can_user_subscribe_channel($clients_info[get_socket_sessionid($changed_socket)], $channels_array[$info["channel"]])){
							$clients_send_list[] = [
								"username" => $info["username"],
								"channel" => $info["channel"],
								"id" => $info["uid"],
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
				
				$response_text = mask(json_encode(array("type"=>"user_disconnected", "id"=>$clients_info[get_socket_sessionid($changed_socket)]["uid"])));
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
?>