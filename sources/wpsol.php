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

// Gebruikersnaam veld toevoegen aan de login pagina
add_action( 'login_form', 'scoutingnl_wp_login_form' ); 

// Inhaken op het authenticatie process
add_filter('authenticate',  'authenticate_username_password', 9);


?>