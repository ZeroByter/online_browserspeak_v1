<?
	function channels_create_db(){
		$conn = sql_connect();
		mysqli_query($conn, "CREATE TABLE IF NOT EXISTS channels(id int(6) NOT NULL auto_increment, 
			name varchar(48) NOT NULL, 
			order varchar(6) NOT NULL, 
			is_default boolean NOT NULL, 
			subscribe_admin_only boolean NOT NULL, 
			enter_admin_only boolean NOT NULL, 
			is_secure boolean NOT NULL, 
			PRIMARY KEY(id))");
		sql_disconnect($conn);
	}
	
	/*
	function does_identity_exist($identity){		
		$conn = sql_connect();
		$stmt = mysqli_prepare($conn, "SELECT id FROM channels WHERE identity='$identity'");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_bind_result($stmt, $result);
		mysqli_stmt_fetch($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
		
		return($result != null);
	}
	*/
	
	function get_all_channels(){
		$conn = sql_connect();
		$result = mysqli_query($conn, "SELECT * FROM channels");
		sql_disconnect($conn);
		
		$array = array();
		while($array[] = mysqli_fetch_object($result));
		
		return $array;
	}
	
	function store_channel($name, $order, $default, $subscribe_admin_only, $enter_admin_only, $is_secure){
		if($order == null || gettype($order) == "boolean"){
			$order = 0;
		}
		
		$conn = sql_connect();
		
		$rows_result = mysqli_query($conn, "SELECT id FROM channels");
		$rows_cnt = mysqli_num_rows($rows_result);
		
		$stmt = mysqli_prepare($conn, "INSERT INTO channels(name, is_default, subscribe_admin_only, enter_admin_only, is_secure) VALUES ('$name', '$default', '$subscribe_admin_only', '$enter_admin_only', '$is_secure')");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
	}
	//store_channel("Lobby #1", 1, true, false, false, true);
	
	/*
	function store_identity_username($identity, $username){
		$conn = sql_connect();
		$stmt1 = mysqli_prepare($conn, "UPDATE channels SET username='$username' WHERE identity='$identity'");
		mysqli_stmt_execute($stmt1);
		mysqli_stmt_close($stmt1);
		sql_disconnect($conn);
	}
	*/
?>
