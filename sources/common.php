<?php
/*
 * 'namePerson/friendly'     => 'nickname',
 * 'contact/email'           => 'email',
 * 'namePerson'              => 'fullname',
 * 'birthDate'               => 'dob',
 * 'person/gender'           => 'gender',
 * 'contact/postalCode/home' => 'postcode',
 * 'contact/country/home'    => 'country',
 * 'pref/language'           => 'language',
 * 'pref/timezone'           => 'timezone'
 */

// Login Formulier
function scoutingnl_wp_login_form_middle() // login_form_middle filter
{
	return scoutingnl_wp_login_form(array('echo'=>false,'sidebar'=>true));
}
function scoutingnl_wp_login_form($args = array()) // login_form action
{
	$defaults = array(
		'echo' => true,
		'sidebar' => false,
	);
	$args = wp_parse_args( $args, $defaults );
	
	if( !$args['sidebar'] )
		$echo = '<hr id="openid_split" style="clear: both; margin-bottom: 1.0em; border: 0; border-top: 1px solid #999; height: 1px;" />';

	$echo .= '
	<style>
	#openid_enabled_link, .openid_link, #openid_identifier, #commentform #openid_identifier {
		background-image: url(\'' . plugins_url( 'scnllogo.png', __FILE__ ) .  '\');
		background-position: 3px 50%;
		background-repeat: no-repeat; 
		padding-left: 21px !important; 
	}
	</style>
	<p style="margin-bottom: 8px;">
		<label style="display: block; margin-bottom: 5px;">Log in met je SOL account<br />
		<input type="text" name="openid_identifier" id="openid_identifier" class="input openid_identifier" value="" size="20" /></label>
	</p>';

	if( $args['echo'] )
		echo $echo;
	else
		return $echo;
}

// Sidebar Login
function wpsol_sidebar_login()
{
	// haal het "normale" login formulier op
	// ToDo - misschien beter een nieuw formulier opbouwen?
	$result = wp_login_form(array('echo'=>false, 'remember'=>false));

	// verwijder username-input
	$begin = strpos($result, '<p class="login-username">');
	$einde = strpos($result, '</p>', $begin)+4;
	$result = substr($result, 0, $begin).substr($result, $einde);

	// verwijder password-input
	$begin = strpos($result, '<p class="login-password">');
	$einde = strpos($result, '</p>', $begin)+4;
	$result = substr($result, 0, $begin).substr($result, $einde);

	if( is_user_logged_in() )
	{ // ingelogd
		// ToDo - display ingelogde gebruikersnaam o.i.d.
	}
	else
	{ // niet ingelogd, display formulier
		echo $result;
	}
}

// Authenticatie Hook
function authenticate_username_password()
{
	# Change 'localhost' to your domain name.
	$openid = new LightOpenID(str_replace(array("http://", "https://"), "", get_site_url()));

	if ( array_key_exists('openid_identifier', $_POST) && $_POST['openid_identifier'] ) {
	// Attempt to authenticate user
		try {
			if(!$openid->mode) {
				if(isset($_POST['openid_identifier'])) {
					$openid->identity = 'https://login.scouting.nl/user/' . $_POST['openid_identifier'];
					//$openid->returnUrl = plugins_url( 'return.php', __FILE__ );
					# The following two lines request email, full name, and a nickname
					# from the provider. Remove them if you don't need that data.
					$openid->required = array('contact/email','namePerson', 'namePerson/friendly');
					$openid->optional = array('birthDate','person/gender','contact/postalCode/home','contact/country/home','pref/language','pref/timezone');
					header('Location: ' . $openid->authUrl());
				}
			} 
		} catch(ErrorException $e) {
			return new WP_Error( 'exception_error', "<strong>ERROR</strong>: " . $e->getMessage() );
		}
	}
	if ($openid->mode) {
		if($openid->validate())
		{
			$gegevens = $openid->getAttributes();
			$username = $gegevens['namePerson/friendly'];

			$user_id = username_exists( $username );

			if( !$user_id and email_exists($email) == false )
			{
				$random_password = wp_generate_password( 18, false );
				$user_id = wp_create_user( $username, $random_password, $gegevens['contact/email'] );
			}
			$user = get_user_by( 'id', $user_id );

			// update nickname, voornaam, achternaam en display_name bij wijziging
			if(get_user_meta( $user->ID , 'nickname') != $gegevens['namePerson'])
			{
				update_user_meta( $user->ID, 'nickname', $gegevens['namePerson'] );
				update_user_meta( $user->ID, 'first_name', substr($gegevens['namePerson'], 0, strpos($gegevens['namePerson'], " ") ) );
				update_user_meta( $user->ID, 'last_name', substr($gegevens['namePerson'], strpos($gegevens['namePerson'], " ")+1 ) );
				wp_update_user( array ('ID' => $user->ID, 'display_name' => $gegevens['namePerson']));    
			}

			return $user;
		}
	}
}
