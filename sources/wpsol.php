<?php
/*
Plugin Name: wpSOL
Plugin URI: https://github.com/koter84/wpSOL
Description: Connect WordPress to the Scouting Nederland OpenID Server
Author: Dennis Koot
Author URI: http://wordpress.org/plugins/wpsol/
Version: 1.1.11
License: GPLv2 or later
Text Domain: wpSOL
*/

include_once 'openid.php';
include_once 'common.php';

// Init wpsol-plugin
function wpsol_init()
{
	// Translation-support (i18n)
	load_plugin_textdomain('wpsol', false, 'wpsol/languages');

	// Gebruikersnaam veld toevoegen aan de login pagina
	add_action('login_form', 'wpsol_wp_login_form');
	add_filter('login_form_middle', 'wpsol_wp_login_form_middle');

	// Inhaken op het authenticatie process
	add_filter('authenticate', 'wpsol_authenticate_username_password', 9);

	// Geef extra links in de plugin-overzichtspagina
	add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'wpsol_plugin_action_links');

	// Registreer sidebar widget
	wp_register_sidebar_widget('wpsol_widget', 'SOL Sidebar Login', 'wpsol_sidebar_login');
	wp_register_widget_control('wpsol_widget', 'SOL Sidebar Login', 'wpsol_sidebar_login_control');
}
add_action('plugins_loaded', 'wpsol_init');

// Init wpsol-admin
function wpsol_admin_menu()
{
    $text = __('wpSOL', 'wpsol');
    add_menu_page($text, $text, 'manage_options', 'wpsol', 'wpsol_admin_settings');

    $text = __('Settings', 'wpsol');
    add_submenu_page('wpsol', $text, $text, 'manage_options', 'wpsol', 'wpsol_admin_settings');

	$text = __('Import', 'wpsol');
    add_submenu_page('wpsol', $text, $text, 'manage_options', 'wpsol_import', 'wpsol_admin_import');
}
add_action('admin_menu', 'wpsol_admin_menu');

// add logout redirect
add_action('wp_logout', 'wpsol_logout_redirect');

// Setup defaults during installation
register_activation_hook( __FILE__, 'wpsol_install');
