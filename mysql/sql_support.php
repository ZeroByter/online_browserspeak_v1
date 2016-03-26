<?
	function sql_connect(){
		$conn = mysqli_connect("localhost", "main_use", "xcZQGUx2DM9vQRjT", "teamspeak_v1");
		return $conn;
	}
	
	function sql_disconnect($conn){
		mysqli_close($conn);
	}
?>