var ws_messages = []

ws_messages["get_own_info"] = function(ws_msg){
	username = ws_msg.client_info.username
	own_id = ws_msg.client_info.uid
	is_admin = ws_msg.client_info.is_admin
}

ws_messages["kick_message"] = function(ws_msg){
	alert(ws_msg.message)
}

ws_messages["system_message"] = function(ws_msg){
	add_chat("system", "", ws_msg.message)
}

ws_messages["private_message"] = function(ws_msg){
	add_chat("private", ws_msg.username, ws_msg.message)
}

ws_messages["global_message"] = function(ws_msg){
	add_chat("global", ws_msg.username, ws_msg.message)
}

ws_messages["error_message"] = function(ws_msg){
	add_chat("error", "", ws_msg.message)
}

ws_messages["user_message"] = function(ws_msg){
	add_chat("user", ws_msg.username, ws_msg.message)
}

ws_messages["change_channel_name"] = function(ws_msg){
	$(".channel").each(function(){
		if($(this).data("id") == ws_msg.id){
			$(this).html(ws_msg.name)
		}
	})
}

ws_messages["get_channels"] = function(ws_msg){
	own_channel_id = ws_msg.active_channel
	
	var array = []
	
	$.each(ws_msg.channels, function(i, v){
		array[i] = new Array()
		array[i]["id"] = v["id"]
		array[i]["name"] = v["name"]
		array[i]["listorder"] = v["listorder"]
		array[i]["default"] = v["default"]
		array[i]["subscribe_admin_only"] = v["subscribe_admin_only"]
		array[i]["enter_admin_only"] = v["enter_admin_only"]
		array[i]["requires_password"] = v["requires_password"]
	})
	
	array.sort(function(a, b){
		return a.listorder - b.listorder
	})
	
	$.each(array, function(i, v){
		add_channel(v["name"], v["listorder"], v["id"], v["id"] == ws_msg.active_channel, v)
	})
}

ws_messages["get_users_in_channel"] = function(ws_msg){
	clear_channel_users(ws_msg.channel)
	$.each(ws_msg.users, function(i, v){
		add_user_to_channel(ws_msg.channel, {
			"id": v["id"],
			"name": v["username"],
			"is_admin": v["is_admin"],
		})
	})
}

ws_messages["get_users_in_channels"] = function(ws_msg){
	$.each(ws_msg.users, function(i, v){
		add_user_to_channel(v["channel"], {
			"id": v["id"],
			"name": v["username"],
			"is_admin": v["is_admin"],
		})
	})
}

ws_messages["user_change_channel"] = function(ws_msg){
	clear_channel_users(ws_msg.channel)
	$.each(ws_msg.users, function(i, v){
		add_user_to_channel(ws_msg.channel, {
			"id": v["id"],
			"name": v["username"],
			"is_admin": v["is_admin"],
		})
	})
}

ws_messages["client_active_channel"] = function(ws_msg){
	$(".active_channel").removeClass("active_channel")
	$(".channel").each(function(i, v){
		if($(this).data("id") == ws_msg.channel){
			$(this).addClass("active_channel")
			own_channel_id = ws_msg.channel
		}
	})
}

ws_messages["user_change_name"] = function(ws_msg){
	user_change_name(ws_msg.id, ws_msg.new_name)
}

ws_messages["user_connected"] = function(ws_msg){
	add_user_to_channel(ws_msg.channel_id, {
		"id": ws_msg.id,
		"name": ws_msg.username,
		"is_admin": ws_msg.is_admin,
	})
}

ws_messages["add_channel_after"] = function(ws_msg){
	insert_channel_after(ws_msg.after_order, ws_msg.name, ws_msg.listorder, ws_msg.id, ws_msg.is_secure)
}

ws_messages["make_channel_default"] = function(ws_msg){
	$(".channel").each(function(i, v){
		if($(this).data("id") == ws_msg.id){
			$(this).data("is-default", "1")
		}else{
			$(this).data("is-default", "0")
		}
	})
}

ws_messages["change_channel_password"] = function(ws_msg){
	$(".channel").each(function(i, v){
		if($(this).data("id") == ws_msg.id){
			$(this).data("requires-password", "1")
		}
	})
}

ws_messages["remove_channel_password"] = function(ws_msg){
	$(".channel").each(function(i, v){
		if($(this).data("id") == ws_msg.id){
			$(this).data("requires-password", "0")
		}
	})
}

ws_messages["toggle_admin_enter_only"] = function(ws_msg){
	$(".channel").each(function(i, v){
		if($(this).data("id") == ws_msg.id){
			$(this).data("enter-admin-only", ws_msg.state)
		}
	})
}

ws_messages["toggle_subscribe_enter_only"] = function(ws_msg){
	$(".channel").each(function(i, v){
		if($(this).data("id") == ws_msg.id){
			$(this).data("subscribe-admin-only", ws_msg.state)
		}
	})
}

ws_messages["remove_channel"] = function(ws_msg){
	remove_channel(ws_msg.id)
}

ws_messages["remove_user"] = function(ws_msg){
	remove_user(ws_msg.id)
}

ws_messages["clear_channel_users"] = function(ws_msg){
	clear_channel_users(ws_msg.channel)
}