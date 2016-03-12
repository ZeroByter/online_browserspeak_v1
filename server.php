<?php
	include("server_functions.php");
	include("channel_functions.php");
	
	$host = "127.0.0.1";
	$port = "8080";
	$start_time = time();
	echo "started on $host:$port at $start_time\n";
	$null = NULL;

	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

	socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

	socket_bind($socket, 0, $port);

	socket_listen($socket);
	
	$client_uid = 0;
	$clients = array($socket);
	$channels_array = [
		0 => [
			"name" => "* Default *",
			"default" => true,
		],
		1 => [
			"name" => "Test channel",
			"default" => false,
		],
	];
	$clients_info = array();

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
				"username" => "",
			];
			
			$header = socket_read($socket_new, 1024);
			perform_handshaking($header, $socket_new, $host, $port);
			
			socket_getpeername($socket_new, $ip);
			//TO-DO: Make it so this message sends both uid of client and the channel that he connected to
			$response = mask(json_encode(array("type"=>"user_connected", "id"=>$clients_info[get_socket_sessionid($changed_socket)]["uid"])));
			send_message($response); //Send to everyone a general message that someone connected
			
			$connected_channel = $channels_array[$clients_info[get_socket_sessionid($socket_new)]["channel"]]["name"];
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
				if($ws_type == "user_message"){
					$user_channel = get_sessionid_user_info(get_socket_sessionid($changed_socket))["channel"];
					
					$response_text = mask(json_encode(array("type"=>$ws_type, "username"=>$ws_msg->username, "message"=>$ws_msg->message)));
					send_message_to_channel($user_channel, $response_text);
					echo "($ip) $ws_msg->username: sent a message '" . $ws_msg->message . "' to channel '{$channels_array[$user_channel]["name"]}'\n";
				}
				if($ws_type == "user_change_name"){
					$uid = $clients_info[get_socket_sessionid($changed_socket)]["uid"];
					$old_username = $clients_info[get_socket_sessionid($changed_socket)]["username"];
					if($old_username == ""){
						echo "($ip) set username to '$ws_msg->name'\n";
					}else{
						echo "($ip) $old_username: switched username to '$ws_msg->name'\n";
					}
					$clients_info[get_socket_sessionid($changed_socket)]["username"] = $ws_msg->name;
					
					$response_text = mask(json_encode(array("type"=>$ws_type, "old_name"=>$old_username, "new_name"=>$ws_msg->name, "id"=>$uid)));
					send_message($response_text);
				}
				if($ws_type == "user_change_channel"){
					$uid = $clients_info[get_socket_sessionid($changed_socket)]["uid"];
					$username = $clients_info[get_socket_sessionid($changed_socket)]["username"];
					$old_channel = $clients_info[get_socket_sessionid($changed_socket)]["channel"];
					
					$clients_info[get_socket_sessionid($changed_socket)]["channel"] = $ws_msg->channel;
					echo "($ip) $username: switched to channel '$ws_msg->channel'\n";
					
					$response_text = mask(json_encode(array("type"=>$ws_type, "name"=>$username, "old_channel"=>$old_channel, "new_channel"=>$ws_msg->channel, "id"=>$uid)));
					send_message($response_text);
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
						$clients_send_list[] = [
							"username" => $info["username"],
							"channel" => $info["channel"],
							"id" => $info["uid"],
						];
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
				
				unset($clients_info[get_socket_sessionid($changed_socket)]);
				
				$found_socket = array_search($changed_socket, $clients);
				unset($clients[$found_socket]);
				
				echo "$ip disconnected\n";
			}
		}
	}
	socket_close($socket);
?>