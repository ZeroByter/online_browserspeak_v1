<?
	function accounts_create_db(){
		$conn = sql_connect();
		mysqli_query($conn, "CREATE TABLE IF NOT EXISTS identities(id int(6) NOT NULL auto_increment, 
			username varchar(48) NOT NULL, 
			identity varchar(24) NOT NULL, 
			is_admin boolean NOT NULL, 
			PRIMARY KEY(id))");
		sql_disconnect($conn);
	}
	
	function does_identity_exist($identity){
		$identity = mysql_real_escape_string($identity);
		
		$conn = sql_connect();
		$stmt = mysqli_prepare($conn, "SELECT id FROM identities WHERE identity='$identity'");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_bind_result($stmt, $result);
		mysqli_stmt_fetch($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
		
		return($result != null);
	}
	
	function get_identity($identity){
		$identity = mysql_real_escape_string($identity);
		
		$conn = sql_connect();
		$stmt = mysqli_prepare($conn, "SELECT * FROM identities WHERE identity='$identity'");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_bind_result($stmt, $result["id"], $result["username"], $result["identity"], $result["is_admin"]);
		mysqli_stmt_fetch($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
		
		return (object) $result;
	}
	
	function store_identity($username, $identity, $is_admin){
		$username = mysql_real_escape_string($username);
		$identity = mysql_real_escape_string($identity);
		$is_admin = mysql_real_escape_string($is_admin);
		
		$conn = sql_connect();
		$stmt = mysqli_prepare($conn, "INSERT INTO identities(username, identity, is_admin) VALUES ('$username', '$identity', '$is_admin')");
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		sql_disconnect($conn);
	}
	
	function store_identity_username($identity, $username){
		$identity = mysql_real_escape_string($identity);
		$username = mysql_real_escape_string($username);
		
		$conn = sql_connect();
		$stmt1 = mysqli_prepare($conn, "UPDATE identities SET username='$username' WHERE identity='$identity'");
		mysqli_stmt_execute($stmt1);
		mysqli_stmt_close($stmt1);
		sql_disconnect($conn);
	}
	
	function store_identity_is_admin($identity, $is_admin){
		$identity = mysql_real_escape_string($identity);
		$is_admin = mysql_real_escape_string($is_admin);
		
		$conn = sql_connect();
		$stmt1 = mysqli_prepare($conn, "UPDATE identities SET is_admin='$is_admin' WHERE identity='$identity'");
		mysqli_stmt_execute($stmt1);
		mysqli_stmt_close($stmt1);
		sql_disconnect($conn);
	}
?>
