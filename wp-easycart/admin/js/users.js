function ec_admin_user_overview_locked_click( el ){
	var gate = null;
	var raw  = el.getAttribute( 'data-gate' );
	if( raw ){
		try { gate = JSON.parse( raw ); } catch( e ){ gate = null; }
	}

	if( typeof wpec_gate !== 'undefined' && wpec_gate && typeof wpec_gate.locked_action === 'function' && gate ){
		return wpec_gate.locked_action( gate );
	}

	var update_url = el.getAttribute( 'data-update-url' );
	if( update_url ){
		window.location.href = update_url;
		return false;
	}

	if( typeof show_pro_required === 'function' ){
		show_pro_required();
	}
	return false;
}
