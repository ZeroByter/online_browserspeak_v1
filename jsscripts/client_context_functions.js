//Channel context menu functions
$("#cntx_switch_channel").click(function(){
	if(own_channel_id != $(context_menu_target).data("id")){
		var ws_msg = {
			type: "user_change_channel",
			channel: $(context_menu_target).data("id"),
		}
		websocket.send(JSON.stringify(ws_msg))
		own_channel_id = $(context_menu_target).data("id")
	}else{
		add_chat("error", "", "Cant join your own channel!")
	}
})

$("#cntx_add_channel_after").click(function(){
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

$("#cntx_delete_channel").click(function(){
	var ws_msg = {
		type: "delete_channel",
		id: $(context_menu_target).data("id"),
	}
	websocket.send(JSON.stringify(ws_msg))
})

$("#cntx_change_channel_name").click(function(){
	$("#chat_input").val("/change_channel_name " + $(context_menu_target).data("id") + " ")
	$("#chat_input").focus()
})

//User context menu functions
$("#ctx_kick_user").click(function(){
	var ws_msg = {
		type: "kick_user",
		target_name: $(context_menu_target).data("name"),
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