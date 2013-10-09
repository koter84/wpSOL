<?php
/*
Plugin Name: SOLOpenID
Plugin URI: 
Description: Gebruik OpenID op je wordpress site om in te loggen met je Scouts online gegevens
Author: Gerrit Jan
Author URI: 
Version: 0.1
License: 
Text Domain: SOL
*/

require 'openid.php';
require 'common.php';

// Gebruikersnaam veld toevoegen aan de login pagina
add_action( 'login_form', 'scoutingnl_wp_login_form' ); 

// Inhaken op het authenticatie process
add_filter('authenticate',  'authenticate_username_password', 9);


?>