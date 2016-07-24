<?php
/**
 * Plugin Name: Sudo Mode
 */

// DEBUG
add_action( 'in_admin_header', function() {

	return;

	echo 'Expiration: '.sudo_mode_get_expiration() . ' // ' . date('H:i:s', sudo_mode_get_expiration() ). '<br />';
	echo 'Current: '.time() . ' // ' . date('H:i:s' ). '<br />';
	echo 'Wait: '. (sudo_mode_get_expiration()-time()) . ' // ' . date('H:i:s',sudo_mode_get_expiration()-time() ). '<br />';
	echo 'Warning: ' . sudo_mode_user_is_sudo_warning();
	echo '<br />';
	echo 'start warning: '. (sudo_mode_get_expiration() - SUDO_MODE_EXPIRATION_WARN) . ' // ' . date('H:i:s',(sudo_mode_get_expiration() - SUDO_MODE_EXPIRATION_WARN) ). '<br />';
});

/** How long does sudo mode last?
 * in seconds
 */
if ( ! defined( 'SUDO_MODE_EXPIRATION' ) ) {
	define( 'SUDO_MODE_EXPIRATION', HOUR_IN_SECONDS/2 );
}

/** How close to expiration should we warn?
 * in seconds
 */
if ( ! defined( 'SUDO_MODE_EXPIRATION_WARN' ) ) {
	define( 'SUDO_MODE_EXPIRATION_WARN', MINUTE_IN_SECONDS*5 );
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
function sudo_mode_user_is_sudo_warning() {
	if ( ! sudo_mode_user_is_sudo() ) {
		return false;
	}
	$expiration = sudo_mode_get_expiration();
	return ( ( $expiration - SUDO_MODE_EXPIRATION_WARN ) < time() && $expiration > time() );
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
	$classes = array();
	$classes[] = sudo_mode_user_is_sudo() ? 'lock' : '';
	$classes[] = sudo_mode_user_is_sudo_warning() ? 'warn' : '';
	$classes = array_filter( $classes );

	$wp_admin_bar->add_menu( array(
		'id'     => 'locked',
		'meta'   => array(
			'class' => implode(' ', $classes ),
		),
		'title'  => '<span class="ab-icon"></span><span class="ab-label">' .
		         (sudo_mode_user_is_sudo() ? __( 'Lock', 'sudo-mode' ) :  __( 'Unlock', 'sudo-mode' ) ) .
		         '</span>',
	) );

	$wp_admin_bar->add_menu( array(
		'parent' => 'locked',
		'id'     => 'locked-field',
		'meta'   => array(
			'class' => ( sudo_mode_user_is_sudo() && ! sudo_mode_user_is_sudo_warning() ? 'hidden' : '' )
		),
		'title'  => '<form id="sudo-unlock">'.
		            '<input id="sudo-unlock-password" type="password" style="height: 20px;" placeholder="user password" />'.
		            '<input id="sudo-unlock-username" type="hidden" value="'. wp_get_current_user()->data->user_login .'" />'.
		            '</form>',
	) );
	if ( sudo_mode_user_is_sudo() )
	$wp_admin_bar->add_menu( array(
		'parent' => 'locked',
		'id'     => 'locked-timer',
		'title'  => sprintf( __( 'Expiring in %s', 'sudo-mode' ), date( 'H:i:s', sudo_mode_get_expiration()-time() ) ),
	) );
}
add_action( 'admin_bar_menu', 'sudo_mode_admin_bar_menu', 88 );

function sudo_mode_admin_css() {
	?>
	<style>
	#wpadminbar #wp-admin-bar-locked .ab-icon:before {
		content: "\f160";
	}
	#wpadminbar #wp-admin-bar-locked.lock .ab-icon:before {
		content: "\f528";
	}
	#wpadminbar #wp-admin-bar-locked.lock .ab-item {
		cursor: pointer;
	}
	#wpadminbar #wp-admin-bar-locked.lock.warn .ab-icon:before {
		color: red;
	}
	</style>
	<?php
}
add_action( 'admin_head', 'sudo_mode_admin_css' );
add_action( 'wp_head', 'sudo_mode_admin_css' );

function sudo_mode_admin_enqueue_scripts() {
	ob_start();
	?>

	var wp = window.wp,
		sudoMode = {
			'lock': '<?php _e('Unlocked. Refresh', 'sudo-mode'); ?>',
			'unlock': '<?php _e('Locked. Refresh', 'sudo-mode'); ?>',
			'err': '<?php _e('Error', 'sudo-mode'); ?>'
		};

	jQuery('#sudo-unlock').submit( function(event) {
		event.preventDefault();
		if ( jQuery('#sudo-unlock-password').val().length == 0 ) {
			return;
		}
		wp.ajax.send( 'sudo-mode-ajax-log-in', {
			data: {
				username : jQuery('#sudo-unlock-username').val(),
				password : jQuery('#sudo-unlock-password').val()
			},
			success: function( data ) {
				jQuery('#wp-admin-bar-locked').addClass('lock');
				jQuery('#wp-admin-bar-locked .ab-label').text( sudoMode.lock );
				// console.log( data );
			},
			error: function( data ) {
				if ( jQuery('#sudo-mode-error').length > 0 ) {
					jQuery('#sudo-mode-error').replaceWith( data );
				} else {
					jQuery('#wp-admin-bar-locked li').append( data );
				}
				jQuery('#sudo-unlock-password').val('');
				// console.log( data );
			}
		} );

	} );

	jQuery('#wp-admin-bar-locked.lock').on ('click', function(event) {

		event.preventDefault();
		wp.ajax.send( 'sudo-mode-ajax-log-out', {
			data: {
			},
			success: function( data ) {
				jQuery('#wp-admin-bar-locked').removeClass('lock');
				jQuery('#wp-admin-bar-locked .ab-label').text( sudoMode.unlock );
			},
			error: function( data ) {
				alert( data );
			}
		} );

	} );

	<?php
	$script = ob_get_clean();
	wp_enqueue_script( 'wp-util' );
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
		if ( 'incorrect_password' == $auth->get_error_code() ) {
			wp_send_json_error( '<span id="sudo-mode-error" class="ab-empty-item">'. __( 'Password incorrect', 'sudo-mode' ) .'</span>' );
		}
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
