<?php
/*
Plugin Name: wpSOL
Plugin URI: 
Description: Gebruik OpenID op je wordpress site om in te loggen met je Scouts online gegevens
Author: Gerrit Jan
Author URI: http://gerritjanfaber.nl
Version: 0.1
License: 
Text Domain: wpSOL
*/

require 'openid.php';
require 'common.php';

require_once('wp-updates-plugin.php');
new WPUpdatesPluginUpdater_241( 'http://wp-updates.com/api/2/plugin', plugin_basename(__FILE__));

// Init wpsol-plugin
function wpsol_init()
{
	// Gebruikersnaam veld toevoegen aan de login pagina
	add_action( 'login_form', 'scoutingnl_wp_login_form' ); 
	add_filter( 'login_form_middle', 'scoutingnl_wp_login_form_middle' ); 

	// Inhaken op het authenticatie process
	add_filter('authenticate',  'authenticate_username_password', 9);

	// Registreer sidebar widget
	register_sidebar_widget(__('SOL Sidebar Login'), 'wpsol_sidebar_login');
}
add_action("plugins_loaded", "wpsol_init");
