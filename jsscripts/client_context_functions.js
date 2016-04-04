//Channel context menu functions
$("#ctx_switch_channel").click(function(){
	if(own_channel_id != $(context_menu_target).data("id")){
		if($(context_menu_target).data("requires-password")){
			var password = prompt("Please enter password")
			if(password){
				var ws_msg = {
					type: "user_change_channel",
					channel: $(context_menu_target).data("id"),
					password: password,
				}
				websocket.send(JSON.stringify(ws_msg))
			}
		}else{
			var ws_msg = {
				type: "user_change_channel",
				channel: $(context_menu_target).data("id"),
				password: "",
			}
			websocket.send(JSON.stringify(ws_msg))
		}
	}else{
		add_chat("error", "", "Cant join your own channel!")
	}
})

$("#ctx_change_channel_name").click(function(){
	//$("#chat_input").val("/change_channel_name " + $(context_menu_target).data("id") + " ")
	//$("#chat_input").focus()
	
	var name = prompt("Please enter a new name for channel '" + $(context_menu_target).data("channel-name") + "'")
	if(name){
		var ws_msg = {
			type: "change_channel_name",
			id: $(context_menu_target).data("id"),
			name: name,
		}
		websocket.send(JSON.stringify(ws_msg))
	}
})

$("#ctx_add_channel_after").click(function(){
	//$("#chat_input").val("/add_channel_after " + $(context_menu_target).data("id") + " ")
	//$("#chat_input").focus()
	
	var name = prompt("Please enter name for new channel")
	if(name){
		var ws_msg = {
			type: "add_channel_after",
			id: $(context_menu_target).data("id"),
			name: name,
		}
		websocket.send(JSON.stringify(ws_msg))
	}
})

$("#ctx_delete_channel").click(function(){
	var ws_msg = {
		type: "delete_channel",
		id: $(context_menu_target).data("id"),
	}
	websocket.send(JSON.stringify(ws_msg))
})

$("#ctx_make_channel_default").click(function(){
	var ws_msg = {
		type: "make_channel_default",
		id: $(context_menu_target).data("id"),
	}
	websocket.send(JSON.stringify(ws_msg))
})

$("#ctx_change_channel_password").click(function(){
	var password = prompt("Please enter a new password for channel '" + $(context_menu_target).data("chanenl-name") + "'")
	if(password){
		var ws_msg = {
			type: "change_channel_password",
			id: $(context_menu_target).data("id"),
			password: password,
		}
		websocket.send(JSON.stringify(ws_msg))
	}
})

$("#ctx_remove_channel_password").click(function(){
	var ws_msg = {
		type: "remove_channel_password",
		id: $(context_menu_target).data("id"),
	}
	websocket.send(JSON.stringify(ws_msg))
})

//Channel context submenu functions
$("#ctx_toggle_admin_enter_only").click(function(){
	var ws_msg = {
		type: "toggle_admin_enter_only",
		id: $(context_menu_target).data("id"),
	}
	websocket.send(JSON.stringify(ws_msg))
})

$("#ctx_toggle_subscribe_enter_only").click(function(){
	var ws_msg = {
		type: "toggle_subscribe_enter_only",
		id: $(context_menu_target).data("id"),
	}
	websocket.send(JSON.stringify(ws_msg))
})

//User context menu functions
$("#ctx_kick_user_server").click(function(){
	var ws_msg = {
		type: "kick_user_server",
		target_name: $(context_menu_target).data("name"),
	}
	websocket.send(JSON.stringify(ws_msg))
})

$("#ctx_kick_user_channel").click(function(){
	var ws_msg = {
		type: "kick_user_channel",
		target_name: $(context_menu_target).data("name"),
	}
	websocket.send(JSON.stringify(ws_msg))
})

$("#ctx_kick_all_from_channel").click(function(){
	var ws_msg = {
		type: "kick_all_from_channel",
		channel: $(context_menu_target).data("id"),
	}
	websocket.send(JSON.stringify(ws_msg))
})

$("#ctx_bring_user").click(function(){
	var ws_msg = {
		type: "bring_user",
		target_name: $(context_menu_target).html(),
	}
	websocket.send(JSON.stringify(ws_msg))
})

$("#ctx_pmsg_user").click(function(){
	$("#chat_input").val("/pmsg " + $(context_menu_target).data("id") + " ")
	$("#chat_input").focus()
})