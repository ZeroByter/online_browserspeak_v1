<?
	function channels_create_db(){
		$conn = sql_connect();
		mysqli_query($conn, "CREATE TABLE IF NOT EXISTS channels(id int(6) NOT NULL auto_increment, 
			name varchar(48) NOT NULL, 
			listorder int(6) NOT NULL, 
			is_default boolean NOT NULL, 
			subscribe_admin_only boolean NOT NULL, 
			enter_admin_only boolean NOT NULL, 
			is_secure boolean NOT NULL, 
			PRIMARY KEY(id))");
		sql_disconnect($conn);
	}
	
	function get_all_channels_by_order(){
		$conn = sql_connect();
		$result = mysqli_query($conn, "SELECT * FROM channels ORDER BY listorder ASC");
		sql_disconnect($conn);
		
		$array = array();
		while($array[] = mysqli_fetch_object($result));
		
		return $array;
	}
	
	function get_all_channels(){
		$conn = sql_connect();
		$result = mysqli_query($conn, "SELECT * FROM channels");
		sql_disconnect($conn);
		
		$array = array();
		while($array[] = mysqli_fetch_object($result));
		
		return $array;
	}
	
	function get_channel_by_id($id){
		$conn = sql_connect();
		$stmt = mysqli_prepare($conn, "SELECT * FROM channels WHERE id='$id'");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_bind_result($stmt, $result["id"], $result["listorder"], $result["name"], $result["subscribe_admin_only"], $result["enter_admin_only"], $result["is_secure"], $result["is_default"]);
		mysqli_stmt_fetch($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
		
		return (object) $result;
	}
	
	function store_channel($name, $order, $default, $subscribe_admin_only, $enter_admin_only, $is_secure){
		if($order == null || gettype($order) == "boolean"){
			$order = 0;
		}
		
		$conn = sql_connect();
		
		$rows_result = mysqli_query($conn, "SELECT id FROM channels");
		$rows_cnt = mysqli_num_rows($rows_result);
		
		$name = str_replace("'", "\'", $name);
		$name = str_replace('"', '\"', $name);
		
		$stmt = mysqli_prepare($conn, "INSERT INTO channels(name, listorder, is_default, subscribe_admin_only, enter_admin_only, is_secure) VALUES ('$name', '$order', '$default', '$subscribe_admin_only', '$enter_admin_only', '$is_secure')");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		$last_id = mysqli_insert_id($conn);
		sql_disconnect($conn);
		
		return $last_id;
	}
	
	function store_delete_channel($id){
		$listorder = get_channel_by_id($id)->listorder;
		
		$conn = sql_connect();
		$stmt = mysqli_prepare($conn, "UPDATE channels SET listorder=listorder-1 WHERE listorder>$listorder");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		$stmt = mysqli_prepare($conn, "DELETE FROM channels WHERE id=$id");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
	}
	
	function store_push_channels_down($listorder_to_push){
		$conn = sql_connect();
		$stmt = mysqli_prepare($conn, "UPDATE channels SET listorder=listorder+1 WHERE listorder>$listorder_to_push");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
	}
	
	function store_channel_name($id, $name){
		$conn = sql_connect();
		$stmt = mysqli_prepare($conn, "UPDATE channels SET name='$name' WHERE id>'$id'");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
	}
	
	function store_channel_order($id, $order){
		$conn = sql_connect();
		$stmt = mysqli_prepare($conn, "UPDATE channels SET listorder='$order' WHERE id='$id'");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
	}
?>
