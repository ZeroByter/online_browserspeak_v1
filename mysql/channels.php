<?
	function channels_create_db(){
		$conn = sql_connect();
		mysqli_query($conn, "CREATE TABLE IF NOT EXISTS channels(id int(6) NOT NULL auto_increment, 
			name varchar(48) NOT NULL, 
			listorder int(6) NOT NULL, 
			is_default boolean NOT NULL, 
			subscribe_admin_only boolean NOT NULL, 
			enter_admin_only boolean NOT NULL, 
			requires_password boolean NOT NULL, 
			password varchar(48) NOT NULL, 
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
		$id = mysql_real_escape_string($id);
		
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
		$name = mysql_real_escape_string($name);
		$order = mysql_real_escape_string($order);
		$default = mysql_real_escape_string($default);
		$subscribe_admin_only = mysql_real_escape_string($subscribe_admin_only);
		$enter_admin_only = mysql_real_escape_string($enter_admin_only);
		$is_secure = mysql_real_escape_string($is_secure);
		
		if($order == null || gettype($order) == "boolean"){
			$order = 0;
		}
		
		$conn = sql_connect();
		
		$rows_result = mysqli_query($conn, "SELECT id FROM channels");
		$rows_cnt = mysqli_num_rows($rows_result);
		
		$stmt = mysqli_prepare($conn, "INSERT INTO channels(name, listorder, is_default, subscribe_admin_only, enter_admin_only, is_secure) VALUES ('$name', '$order', '$default', '$subscribe_admin_only', '$enter_admin_only', '$is_secure')");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		$last_id = mysqli_insert_id($conn);
		sql_disconnect($conn);
		
		return $last_id;
	}
	
	function store_delete_channel($id){
		$id = mysql_real_escape_string($id);
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
		$listorder_to_push = mysql_real_escape_string($listorder_to_push);
		
		$conn = sql_connect();
		$stmt = mysqli_prepare($conn, "UPDATE channels SET listorder=listorder+1 WHERE listorder>$listorder_to_push");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
	}
	
	function store_channel_name($id, $name){
		$id = mysql_real_escape_string($id);
		$name = mysql_real_escape_string($name);
		
		$conn = sql_connect();
		$stmt = mysqli_prepare($conn, "UPDATE channels SET name='$name' WHERE id='$id'");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
	}
	
	function store_channel_order($id, $order){
		$id = mysql_real_escape_string($id);
		$order = mysql_real_escape_string($order);
		
		$conn = sql_connect();
		$stmt = mysqli_prepare($conn, "UPDATE channels SET listorder='$order' WHERE id='$id'");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
	}
	
	function store_channel_make_default($id){
		$id = mysql_real_escape_string($id);
		
		$conn = sql_connect();
		$stmt = mysqli_prepare($conn, "UPDATE channels SET is_default='0'");
		mysqli_stmt_execute($stmt);
		$stmt = mysqli_prepare($conn, "UPDATE channels SET is_default='1' WHERE id='$id'");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
	}
	
	function store_channel_change_password($id, $password){
		$id = mysql_real_escape_string($id);
		$password = mysql_real_escape_string($password);
		
		$conn = sql_connect();
		$stmt = mysqli_prepare($conn, "UPDATE channels SET password='$password',requires_password='1' WHERE id='$id'");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
	}
	
	function store_channel_remove_password($id){
		$id = mysql_real_escape_string($id);
		
		$conn = sql_connect();
		$stmt = mysqli_prepare($conn, "UPDATE channels SET password='',requires_password='false' WHERE id='$id'");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
	}
	
	function store_channel_toggle_admin_enter_only($id){
		$id = mysql_real_escape_string($id);
		
		$conn = sql_connect();
		$stmt = mysqli_prepare($conn, "UPDATE channels SET enter_admin_only=NOT enter_admin_only WHERE id='$id'");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
	}
	
	function store_channel_toggle_admin_subscribe_only($id){
		$id = mysql_real_escape_string($id);
		
		$conn = sql_connect();
		$stmt = mysqli_prepare($conn, "UPDATE channels SET subscribe_admin_only=NOT subscribe_admin_only WHERE id='$id'");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
	}
?>
