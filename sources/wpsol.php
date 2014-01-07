<?php
/*
Plugin Name: wpSOL
Plugin URI: http://bitbucket.org/gerritjanf/wpsol
Description: Connect WordPress to the Scouting Nederland OpenID Server
Author: Gerrit Jan en Dennis
Author URI: http://www.gerritjanfaber.nl
Version: 0.5
License: 
Text Domain: wpSOL
*/

require 'openid.php';
require 'common.php';

// Init wpsol-plugin
function wpsol_init()
{
	// Translation-support (i18n)
	load_plugin_textdomain( 'wpsol', false, 'wpsol/languages' );

	// Gebruikersnaam veld toevoegen aan de login pagina
	add_action( 'login_form', 'wpsol_wp_login_form' ); 
	add_filter( 'login_form_middle', 'wpsol_wp_login_form_middle' ); 

	// Inhaken op het authenticatie process
	add_filter('authenticate',  'wpsol_authenticate_username_password', 9);

	// Registreer sidebar widget
	register_sidebar_widget('SOL Sidebar Login', 'wpsol_sidebar_login');
}
add_action("plugins_loaded", "wpsol_init");

// Init wpsol-admin
function wpsol_admin_menu()
{
	add_options_page( 'wpSOL', 'wpSOL', 'manage_options', 'wpsol_settings', 'wpsol_admin_options' );
}
add_action( 'admin_menu', 'wpsol_admin_menu' );

// Setup defaults during installation
register_activation_hook( __FILE__, 'wpsol_install' );
