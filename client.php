<link rel="stylesheet" type="text/css" href="client.css">

<script type="text/javascript" src="/jsscripts/cookie.js"></script>
<script type="text/javascript" src="/jsscripts/jquery-2.2.0.min.js"></script>

<body>
	<text id="title">Browser Teamspeak version 1.0</text>
	<button id="disconnect">Disconnect from server</button>
	<div id="channels_div"></div>
	<div id="chat_div"></div>
	<input id="chat_input" placeholder="Click on me, type your message, and press enter to send..."></input>
	<div id="information_div">
		<center>
			<h1>Welcome to browser teamspeak version 1.0!</h1>
		</center>
	</div>
	
	<div id="channel_context_menu">
		<div class="ctx_menu_div">
			<button class="ctx_menu_btn" id="ctx_switch_channel" data-admin-only="false">Switch channel</button>
		</div>
		<div class="ctx_menu_div">
			<button class="ctx_menu_btn" id="ctx_change_channel_name" data-admin-only="true">Change channel name</button>
		</div>
		<div class="ctx_menu_div">
			<button class="ctx_menu_btn" id="ctx_add_channel_after" data-admin-only="true">Add channel after</button>
		</div>
		<div class="ctx_menu_div">
			<button class="ctx_menu_btn" id="ctx_delete_channel" data-admin-only="true">Delete channel</button>
		</div>
		<div class="ctx_menu_div">
			<button class="ctx_menu_btn" id="ctx_make_channel_default" data-admin-only="true">Make channel default</button>
		</div>
		<div class="ctx_menu_div">
			<button class="ctx_menu_btn" id="ctx_change_channel_password" data-admin-only="true">Change channel password</button>
		</div>
		<div class="ctx_menu_div">
			<button class="ctx_menu_btn" id="ctx_remove_channel_password" data-admin-only="true">Remove channel password</button>
		</div>
		<div class="ctx_menu_submenu"  id="ctx_permissions_submenu_div">Permissions ></div>
		<div class="ctx_menu_div">
			<button class="ctx_menu_btn" id="ctx_kick_all_from_channel" data-admin-only="true">Kick everyone out of channel</button>
		</div>
	</div>
	
	<div id="channel_context_submenu_permissions">
		<div class="ctx_menu_div">
			<button class="ctx_menu_btn" id="ctx_toggle_admin_enter_only" data-admin-only="true">Toggle entry for admins only</button>
		</div>
		<div class="ctx_menu_div">
			<button class="ctx_menu_btn" id="ctx_toggle_subscribe_enter_only" data-admin-only="true">Toggle subscription for admins only</button>
		</div>
	</div>
	
	<div id="user_context_menu">
		<div class="ctx_menu_div">
			<button class="ctx_menu_btn" id="ctx_kick_user_server" data-admin-only="true">Kick user from server</button>
		</div>
		<div class="ctx_menu_div">
			<button class="ctx_menu_btn" id="ctx_kick_user_channel" data-admin-only="true">Kick user channel</button>
		</div>
		<div class="ctx_menu_div">
			<button class="ctx_menu_btn" id="ctx_bring_user" data-admin-only="true">Bring user to channel</button>
		</div>
		<div class="ctx_menu_div">
			<button class="ctx_menu_btn" id="ctx_pmsg_user" data-admin-only="false">Private message user</button>
		</div>
	</div>
</body>

<script type="text/javascript" src="/jsscripts/client_ws_messages.js"></script>
<script type="text/javascript" src="/jsscripts/client.js"></script>
<script type="text/javascript" src="/jsscripts/client_context_functions.js"></script>
