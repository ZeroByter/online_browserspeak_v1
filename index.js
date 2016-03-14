var websocket = new WebSocket("ws://213.57.241.208:8080") //Make the websocket connection

//Client stored settings
var username = ""//The username
var username_min_length = 4 //The minimum length for a username

window.onbeforeunload = function(){
    websocket.onclose = function(){}
    websocket.close()
}

websocket.onopen = function(){ //When the websocket connection opens
	add_chat("system", "", "Connected to server")
	
	var ws_msg = {
		type: "get_channels",
	}
	websocket.send(JSON.stringify(ws_msg))
	
	setTimeout(function(){
		var ws_msg = {
			type: "get_users_in_channels",
		}
		websocket.send(JSON.stringify(ws_msg))
	}, 100)
}

websocket.onerror = function(){ //When the websocket connection crashes
	add_chat("system", "", "Connection error")
}

websocket.onclose = function(event){ //When the websocket connection closes
	var reason = []
	reason[1000] = ""
	reason[1001] = " (going away)"
	reason[1006] = " (server timed out)"
	reason[1011] = " (internal server error)"
	var reason_string = reason[event.code] || ""
	add_chat("system", "", "Connection closed!" + reason_string)
	
	$("#channels_div").html("<br><br><font color=\"red\">Connection closed!</font>")
}

websocket.onmessage = function(event){ //When a websocket message is received
	var ws_msg = JSON.parse(event.data)
	var type = ws_msg.type
	
	console.log("message received -- type: " + type)
	
	if(type == "admin_enter"){
		var valid = ws_msg.valid
		var enabled = ws_msg.enabled
		if(valid){
			if(enabled){
				add_chat("system", "", "Admin access granted and enabled!")
			}else{
				add_chat("system", "", "Admin access granted and disabled!")
			}
		}else{
			add_chat("error", "", "Admin access denied!")
		}
	}
	
	if(type == "admin_name_change"){
		var id = ws_msg.id
		var enabled = ws_msg.enabled
		//TO-DO: When a admin toggles on or off, send this message to all users, a message will appear in chat and name will be changed with ' [Admin]' or nothing.
	}
	
	if(type == "system_message"){
		add_chat("system", "", ws_msg.message)
	}
	
	if(type == "global_message"){
		add_chat("global", ws_msg.username, ws_msg.message)
	}
	
	if(type == "error_message"){
		add_chat("error", "", ws_msg.message)
	}
	
	if(type == "user_message"){
		add_chat("user", ws_msg.username, ws_msg.message)
	}
	
	if(type == "get_channels"){
		$.each(ws_msg.channels, function(i, v){
			add_channel(v["name"], i, i == ws_msg.active_channel)
		})
	}
	
	if(type == "get_users_in_channel"){
		clear_channel_users(ws_msg.channel)
		$.each(ws_msg.users, function(i, v){
			add_user_to_channel(ws_msg.channel, {
				"id": v["id"],
				"name": v["username"],
			})
		})
	}
	
	if(type == "get_users_in_channels"){
		$.each(ws_msg.users, function(i, v){
			add_user_to_channel(v["channel"], {
				"id": v["id"],
				"name": v["username"],
			})
		})
	}
	
	if(type == "user_change_channel"){
		clear_channel_users(ws_msg.channel)
		$.each(ws_msg.users, function(i, v){
			add_user_to_channel(ws_msg.channel, {
				"id": v["id"],
				"name": v["username"],
			})
		})
	}
	
	if(type == "client_active_channel"){
		$(".active_channel").removeClass("active_channel")
		$(".channel").each(function(i, v){
			if($(this).data("id") == ws_msg.channel){
				$(this).addClass("active_channel")
			}
		})
	}
	
	if(type == "user_change_name"){
		user_change_name(ws_msg.id, ws_msg.new_name)
	}
	
	if(type == "user_connected"){
		add_user_to_channel(ws_msg.channel_id, {
			"id": ws_msg.id,
			"name": ws_msg.username,
		})
	}
	
	if(type == "user_disconnected"){
		remove_user(ws_msg.id)
	}
	
	if(type == "remove_user"){
		remove_user(ws_msg.id)
	}
	
	if(type == "clear_channel_users"){
		clear_channel_users(ws_msg.channel)
	}
}

function add_channel(name, id, active_channel){ //Add a channel to the channels div
	var active_channel_str = ""
	if(active_channel){
		active_channel_str = "active_channel"
	}
	$("#channels_div").append("<span class=\"channel " + active_channel_str + "\" data-channel-name=\"" + name + "\" data-id=\"" + id + "\">Channel: " + name + "</span>")
	$("#channels_div").append("<span class=\"channel_users\" data-id=\"" + id + "\"></span>")
	return true
}

function clear_channel_users(channel_id){
	$(".channel_users").each(function(){
		if($(this).data("id") == channel_id){
			$(this).html("")
		}
	})
}

function add_user_to_channel(channel_id, user_properties){
	$(".channel_users").each(function(){
		if($(this).data("id") == channel_id){
			//$(this).html("")
			$(this).append("<span class=\"user\" data-id=\"" + user_properties["id"] + "\">" + user_properties["name"] + "</span>")
		}
	})
}

function user_change_name(id, new_name){
	$(".user").each(function(){
		if($(this).data("id") == id){
			$(this).html(new_name)
		}
	})
}

function remove_user(id){
	$(".user").each(function(){
		if($(this).data("id") == id){
			$(this).remove()
		}
	})
}

$("body").on("click", ".channel:not(.active_channel)", function(){
	var ws_msg = {
		type: "user_change_channel",
		channel: $(this).data("id"),
	}
	websocket.send(JSON.stringify(ws_msg))
})

$("#chat_input").bind("keypress", function(e){ //When a user sends a message via the chat input box
	var code = e.keyCode || e.which
	if(code == 13){
		var input_text = $("#chat_input").val()
		if(input_text.startsWith("/")){ //Was the text a command?
			var command = input_text.split("/") //The command entered
			delete command[0]
			command = command.join("").split(" ")
			command = command[0]
			var args = input_text.split(" ") //The arguments entered, in an array
			delete args[0]
			$.each(args, function(i, v){
				args[i-1] = v
				delete args[i]
			})
			var args_string = input_text.split(" ") //The arguments entered, in a string
			delete args_string[0]
			args_string = args_string.join(" ").replace(" ", "")
			
			if(command == "username"){ //Set username command
				if(args_string.length >= username_min_length){
					var ws_msg = {
						type: "user_change_name",
						name: args_string,
					}
					websocket.send(JSON.stringify(ws_msg))
					
					add_chat("system", "", "Username set to '" + args_string + "' (" + args_string.length + ")")
					username = args_string
					$("#chat_input").val("")
					return false
				}else{
					add_chat("error", "", "Username must be longer than " + username_min_length + " charachers! The username you entered (" + args_string + ") was " + args_string.length + " long!")
					$("#chat_input").val("/username ")
					return false
				}
			}
			if(command == "admin"){ //Admin login command
				var ws_msg = {
					type: "admin_enter",
					password: args_string,
				}
				websocket.send(JSON.stringify(ws_msg))
				$("#chat_input").val("")
				return
			}
			if(command == "global"){ //Global chat command
				var ws_msg = {
					type: "global_message",
					message: args_string,
				}
				websocket.send(JSON.stringify(ws_msg))
				$("#chat_input").val("")
				return
			}
			if(command == "bring"){ //Global chat command
				var ws_msg = {
					type: "bring_user",
					target_name: args_string,
				}
				websocket.send(JSON.stringify(ws_msg))
				$("#chat_input").val("")
				return
			}
			if(command == "test"){ //test command
				var ws_msg = {
					type: "test",
				}
				websocket.send(JSON.stringify(ws_msg))
				add_chat("system", "", "** test function ran **")
				$("#chat_input").val("")
				return
			}
		}
		
		if(input_text == ""){
			add_chat("error", "", "Message must not be empty!")
			$("#chat_input").val("")
			return false
		}
		
		if(username == ""){
			add_chat("error", "", "Please select a username! Type in chat '/username {username}' to select a username!")
			$("#chat_input").val("")
			return false
		}
		
		var ws_msg = {
			type: "user_message",
			username: username,
			message: input_text,
		}
		websocket.send(JSON.stringify(ws_msg))
		$("#chat_input").val("")
		return true
	}
})

function add_chat(type, username, message){ //Add a chat message to the chat box
	if(type == "error"){
		$("#chat_div").append("<p><i><font color=\"red\">" + message + "</font></i></p>")
	}
	if(type == "system"){
		$("#chat_div").append("<p><i>" + message + "</i></p>")
	}
	if(type == "user"){
		$("#chat_div").append("<p>" + username + ": " + message + "</p>")
	}
	if(type == "global"){
		$("#chat_div").append("<p>" + username + " (GLOBAL): " + message + "</p>")
	}
	
	var height = 0
	$("#chat_div p").each(function(i, value){
		height = height + parseInt($(this).height())
	})
	height = height + ""
	$("#chat_div").animate({scrollTop: height}, 200)
}