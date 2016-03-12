<?
	function get_socket_sessionid($socket){
		global $clients;
		return array_search($socket, $clients);
	}
	
	function get_sessionid_socket($sessionid){
		global $clients;
		return $clients[$sessionid];
	}
	
	function get_sessionid_user_info($sessionid){
		global $clients_info;
		return $clients_info[$sessionid];
	}
	
	function send_message_private($socket, $msg){
		global $clients;
		foreach($clients as $changed_socket){
			if($socket == $changed_socket){
				@socket_write($changed_socket,$msg,strlen($msg));
			}
		}
		return true;
	}

	function send_message_but_local($local_socket, $msg){
		global $clients;
		foreach($clients as $changed_socket){
			if($local_socket != $changed_socket){
				@socket_write($changed_socket,$msg,strlen($msg));
			}
		}
		return true;
	}

	function send_message($msg){
		global $clients;
		foreach($clients as $changed_socket){
			@socket_write($changed_socket,$msg,strlen($msg));
		}
		return true;
	}

	function send_message_to_channel($send_channel, $msg){
		global $clients;
		global $clients_info;
		foreach($clients_info as $sessionid=>$info){
			if($info["channel"] == $send_channel){
				@socket_write(get_sessionid_socket($sessionid),$msg,strlen($msg));
			}
		}
		return true;
	}

	function unmask($text){
		$length = ord($text[1]) & 127;
		if($length == 126) {
			$masks = substr($text, 4, 4);
			$data = substr($text, 8);
		}
		elseif($length == 127){
			$masks = substr($text, 10, 4);
			$data = substr($text, 14);
		}
		else{
			$masks = substr($text, 2, 4);
			$data = substr($text, 6);
		}
		$text = "";
		for($i = 0; $i < strlen($data); ++$i){
			$text .= $data[$i] ^ $masks[$i%4];
		}
		return $text;
	}

	function mask($text)
	{
		$b1 = 0x80 | (0x1 & 0x0f);
		$length = strlen($text);
		
		if($length <= 125)
			$header = pack('CC', $b1, $length);
		elseif($length > 125 && $length < 65536)
			$header = pack('CCn', $b1, 126, $length);
		elseif($length >= 65536)
			$header = pack('CCNN', $b1, 127, $length);
		return $header.$text;
	}

	function perform_handshaking($receved_header,$client_conn, $host, $port)
	{
		$headers = array();
		$lines = preg_split("/\r\n/", $receved_header);
		foreach($lines as $line)
		{
			$line = chop($line);
			if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
			{
				$headers[$matches[1]] = $matches[2];
			}
		}

		$secKey = $headers['Sec-WebSocket-Key'];
		$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
		$upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
		"Upgrade: websocket\r\n" .
		"Connection: Upgrade\r\n" .
		"WebSocket-Origin: $host\r\n" .
		"WebSocket-Location: ws://$host:$port/server.php\r\n".
		"Sec-WebSocket-Accept:$secAccept\r\n\r\n";
		socket_write($client_conn,$upgrade,strlen($upgrade));
	}
?>