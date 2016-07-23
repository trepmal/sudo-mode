<?php
/**
 * Plugin Name: Sudo Mode
 */
/*
 @todo
 - toggle menu label
 - pointer hand on label
 - had password when in sudo. idea: make red when less than 5 mins left, show field

*/

// DEBUG
add_action( 'in_admin_header', function() {

		echo 'Expiration: '.sudo_mode_get_expiration() . '<br />';
		echo 'Current: '.time() . '<br />';
		var_dump( sudo_mode_get_expiration() > time() );

});


if ( ! defined( 'SUDO_MODE_EXPIRATION' ) ) {
	define( 'SUDO_MODE_EXPIRATION', HOUR_IN_SECONDS/2 );
}

/**
 * Filter permissions
 */
function sudo_mod_map_meta_cap( $caps, $cap, $user_id, $args ) {
	if ( ! sudo_mode_user_is_sudo() && 'manage_options' == $cap ) {
		return false;
	}

	return $caps;
}
add_filter( 'map_meta_cap', 'sudo_mod_map_meta_cap', 10, 4 );

/**
 * Whether sudo has been unlocked for user
 *
 * @return bool
 */
function sudo_mode_user_is_sudo() {
	$expiration = sudo_mode_get_expiration();
	return $expiration > time();
}

/**
 * Whether sudo has been unlocked for user
 *
 * @return bool
 */
function sudo_mode_get_expiration() {
	return get_user_meta( get_current_user_id(), 'sudo_expire', true );
}

/**
 * Set/update sudo expiration
 *
 * @return bool
 */
function sudo_mode_user_set_sudo() {
	update_user_meta( get_current_user_id(), 'sudo_expire', time() + SUDO_MODE_EXPIRATION );
	return true;
}

/**
 * Unset sudo
 *
 * @return bool
 */
function sudo_mode_user_unset_sudo() {
	update_user_meta( get_current_user_id(), 'sudo_expire', time() - HOUR_IN_SECONDS );
	return true;
}

/**
 */
function sudo_mode_admin_bar_menu( $wp_admin_bar ) {
	$wp_admin_bar->add_menu( array(
		'id'     => 'unlock',
		'meta' => array( 'class' => ( sudo_mode_user_is_sudo() ? 'lock' : 'asdfasdf' ) ),
		'title'  => '<span class="ab-icon"></span><span class="ab-label">' . __( 'Unlock', 'sudo-mode' ) . '</span>',
	) );
	$wp_admin_bar->add_menu( array(
		'parent' => 'unlock',
		'id'     => 'unlock-field',
		'title'  => '<form id="sudo-unlock">'.
		            '<input id="sudo-unlock-password" type="password" style="height: 20px;" />'.
		            '<input id="sudo-unlock-username" type="hidden" value="'. wp_get_current_user()->data->user_login .'" />'.
		            '</form>',
	) );
}
add_action( 'admin_bar_menu', 'sudo_mode_admin_bar_menu', 88 );

function sudo_mode_admin_css() {
	?>
	<style>
	#wpadminbar #wp-admin-bar-unlock .ab-icon:before {
		content: "\f160";
	}
	#wpadminbar #wp-admin-bar-unlock.lock .ab-icon:before {
		content: "\f528";
	}
	</style>
	<?php
}
add_action( 'admin_head', 'sudo_mode_admin_css' );
add_action( 'wp_head', 'sudo_mode_admin_css' );

function sudo_mode_admin_enqueue_scripts() {
	ob_start();
	?>

	var wp = window.wp;

	jQuery('#sudo-unlock').submit( function(event) {

		event.preventDefault();
		wp.ajax.send( 'sudo-mode-ajax-log-in', {
			data: {
				username : jQuery('#sudo-unlock-username').val(),
				password : jQuery('#sudo-unlock-password').val()
			},
			success: function( data ) {
				jQuery('#wp-admin-bar-unlock').addClass('lock');
			},
			error: function( data ) {
				jQuery('#sudo-unlock-password').val('');
				alert( 'err' );
				console.log( data );
			}
		} );

	} );

	jQuery('#wp-admin-bar-unlock.lock').on ('click', function(event) {

		event.preventDefault();
		wp.ajax.send( 'sudo-mode-ajax-log-out', {
			data: {
			},
			success: function( data ) {
				jQuery('#wp-admin-bar-unlock').removeClass('lock');
				alert( data );
			},
			error: function( data ) {
				alert( data );
			}
		} );

	} );

	<?php
	$script = ob_get_clean();
	wp_add_inline_script( 'wp-util', $script );
}
add_action( 'admin_enqueue_scripts', 'sudo_mode_admin_enqueue_scripts', 1000 );

function sudo_mode_ajax_log_in() {

	$password = $_POST['password'];
	$username = $_POST['username'];

	$auth = wp_authenticate( $username, $password );

	if ( ! is_wp_error( $auth ) ) {
		sudo_mode_user_set_sudo();
		wp_send_json_success( $auth->ID );
	} else {
		wp_send_json_error( $auth->get_error_message() );
	}

}
add_action( 'wp_ajax_sudo-mode-ajax-log-in', 'sudo_mode_ajax_log_in' );

function sudo_mode_ajax_log_out() {

	if ( sudo_mode_user_unset_sudo() ) {
		wp_send_json_success( sudo_mode_get_expiration() );
	} else {
		wp_send_json_error( 'uh oh' );
	}

}
add_action( 'wp_ajax_sudo-mode-ajax-log-out', 'sudo_mode_ajax_log_out' );
