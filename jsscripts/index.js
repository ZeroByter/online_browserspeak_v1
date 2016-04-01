if(getCookie("user_identity") == "" && $("#identity").val().length != 20){
	$.get("/user/get_identity", function(data){
		setCookie("user_identity", data, "365")
		$("#identity").val(data)
	})
}

$("#identity").val(getCookie("user_identity"))
if(getCookie("username") != ""){
	$("#username").val(getCookie("username"))
}
$("#server_ip").val(getCookie("server_ip"))
$("#server_port").val(getCookie("server_port"))

$("#connect_to_server").click(function(){
	if($("#identity").val().length == 20){
		setCookie("user_identity", $("#identity").val(), "365")
	}
	setCookie("username", $("#username").val(), "365")
	setCookie("server_ip", $("#server_ip").val(), "365")
	setCookie("server_port", $("#server_port").val(), "365")
	window.location = "/client"
})