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
function wpsol_wp_login_form_middle() // login_form_middle filter
{
	return wpsol_wp_login_form(array('echo'=>false,'sidebar'=>true));
}
function wpsol_wp_login_form($args = array()) // login_form action
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
	if( is_user_logged_in() )
	{ // ingelogd
		// ToDo - display ingelogde gebruikersnaam o.i.d.
		$result = "";
	}
	else
	{
		// haal het "normale" login formulier op
		// ToDo - misschien beter een nieuw formulier opbouwen?
		$result = wp_login_form(array('echo'=>false, 'remember'=>false));

		// verwijder username-input
		$result = substr($result, 0, strpos($result, '<p class="login-username">')).substr($result, strpos($result, '</p>', strpos($result, '<p class="login-username">'))+4);

		// verwijder password-input
		$result = substr($result, 0, strpos($result, '<p class="login-password">')).substr($result, strpos($result, '</p>', strpos($result, '<p class="login-password">'))+4);
	}

	if($result != "")
		echo "<li>".$result."</li>";
}

// Authenticatie Hook
function wpsol_authenticate_username_password()
{
	# Change 'localhost' to your domain name.
	$openid = new LightOpenID(str_replace(array("http://", "https://"), "", get_site_url()));

	if ( array_key_exists('openid_identifier', $_POST) && $_POST['openid_identifier'] )
	{ // Attempt to authenticate user
		try
		{
			if(!$openid->mode)
			{
				if(isset($_POST['openid_identifier']))
				{
					$openid->identity = 'https://login.scouting.nl/user/' . $_POST['openid_identifier'];
					//$openid->returnUrl = plugins_url( 'return.php', __FILE__ );
					# The following two lines request email, full name, and a nickname
					# from the provider. Remove them if you don't need that data.
					$openid->required = array('contact/email','namePerson', 'namePerson/friendly');
					$openid->optional = array('birthDate','person/gender','contact/postalCode/home','contact/country/home','pref/language','pref/timezone');
					header('Location: ' . $openid->authUrl());
				}
			} 
		}
		catch(ErrorException $e)
		{
			return new WP_Error( 'exception_error', "<strong>ERROR</strong>: " . $e->getMessage() );
		}
	}
	if ($openid->mode)
	{
		if($openid->validate())
		{
			$new_user = false;

			$gegevens = $openid->getAttributes();
			$username = get_option('wpsol_username_prefix').$gegevens['namePerson/friendly'];
			$email = $gegevens['contact/email'];

			$user_id = username_exists( $username );
			$email_id = email_exists($email);

			if( !$user_id and !$email_id )
			{ // geen user_id, geen email_id, create new user.
				if(get_option('wpsol_autocreate') == true)
				{
					$random_password = wp_generate_password( 18, false );
					$user_id = wp_create_user( $username, $random_password, $email );
					$new_user = true;
				}
				else
				{
					return false;
				}
			}
			elseif( !$user_id )
			{ // geen user_id, wel email_id, login
				$user = get_user_by( 'id', $email_id );
			}
			elseif( !$email_id )
			{ // geen email_id, wel user_id, login
				$user = get_user_by( 'id', $user_id );
				// ToDo - update email for user ?
			}
			elseif( $user_id == $email_id )
			{ // login.
				$user = get_user_by( 'id', $user_id );
			}
			else
			{ // uhm.
				// ToDo - notificatie naar site-admin o.i.d.
				// mogelijke fouten: 
				//  - user_id != email_id ( wat te doen )
				//  - ???
				return false;
			}

			if($new_user OR get_option('wpsol_force_display_name'))
			{
				switch(get_option('wpsol_display_name'))
				{
					case 'firstname':
						$display_name = substr($gegevens['namePerson'], 0, strpos($gegevens['namePerson'], " ") );
						break;
					case 'lastname':
						$display_name = substr($gegevens['namePerson'], strpos($gegevens['namePerson'], " ")+1 );
						break;
					case 'username':
						$display_name = $username;
						break;
					case 'fullname':
					default:
						$display_name = $gegevens['namePerson'];
						break;
				}

				update_user_meta( $user->ID, 'nickname', $display_name );
				wp_update_user( array ('ID' => $user->ID, 'display_name' => $display_name));    
			}

			if($new_user OR get_option('wpsol_force_first_last_name'))
			{
				update_user_meta( $user->ID, 'first_name', substr($gegevens['namePerson'], 0, strpos($gegevens['namePerson'], " ") ) );
				update_user_meta( $user->ID, 'last_name', substr($gegevens['namePerson'], strpos($gegevens['namePerson'], " ")+1 ) );
			}			

			return $user;
		}
	}
}

// Install function
function wpsol_install()
{
	// set default option-values
	update_option('wpsol_display_name', 'fullname');
	update_option('wpsol_force_display_name', false);
	update_option('wpsol_force_first_last_name', false);
	update_option('wpsol_username_prefix', 'sn_');
	update_option('wpsol_autocreate', true);
}

// Admin Settings Pagina
function wpsol_admin_options()
{
	if ( !current_user_can( 'manage_options' ) )
	{
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

    // variables for the field and option names 
	$options = array(
		'wpsol_display_name' => array(
			'name' => 'Stel display_name in op: ',
			'type' => 'select',
			'options' => array(
				'fullname' => 'Volledige naam', 
				'firstname' => 'Voornaam', 
				'lastname' => 'Achternaam', 
				'username' => 'Gebruikersnaam',
			),
		),
		'wpsol_force_display_name' => array(
			'name' => 'Forceer display_name bij elke login: ',
			'type' => 'checkbox',
		),
		'wpsol_force_first_last_name' => array(
			'name' => 'Forceer voornaam en achternaam bij elke login: ',
			'type' => 'checkbox',
		),
		'wpsol_username_prefix' => array(
			'name' => 'Voorvoegsel voor alle Scouting Nederland logins: ',
			'type' => 'text',
		),
		'wpsol_autocreate' => array(
			'name' => 'Automatisch nieuwe gebruikers aanmaken: ',
			'type' => 'checkbox',
		),
	);

    // See if the user has posted us some information
    $hidden_field_name = 'wpsol_hidden';
    if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' )
	{
		foreach($options as $key => $opt)
		{
			// Save the posted value in the database
			update_option( $key, $_POST[$key] );
		}
		// Put an settings updated message on the screen
		echo "<div class=\"updated\"><p><strong>Instellingen Opgeslagen</strong></p></div>";
    }

    // Now display the settings editing screen
    ?>
<div class="wrap">
	<div id="icon-options-general" class="icon32"><br></div>
	<h2>wpSOL [ScoutsOnLine] Settings</h2>
	<form name="wpsol_settings_form" method="post" action="">
	<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
	<table class="form-table">
	<?php

	foreach($options as $key => $opt)
	{
		$input = "";
		switch($opt['type'])
		{
			case 'text':
				$input .= "<input type=\"text\" name=\"".$key."\" value=\"".get_option($key)."\" size=\"20\" />";
				break;
			case 'checkbox':
				if(get_option($key) == 1)
					$sel = "CHECKED";
				else
					$sel = "";
				$input .= "<input type=\"checkbox\" name=\"".$key."\" value=\"1\" ".$sel." />";
				break;
			case 'select':
				$input .= "<select name=\"".$key."\">";
				foreach($opt['options'] as $select_key => $select_name)
				{
					if(get_option($key) == $select_key)
						$sel = "SELECTED";
					else
						$sel = "";
					$input .= "<option value=\"".$select_key."\" ".$sel." size=\"20\">".$select_name."</option>";
				}
				$input .= "</select>";
				break;
		}
		echo "
		<tr valign=\"top\">
			<th scope=\"row\"><label for=\"".$key."\">".$opt['name']."</label></th>
			<td>".$input."</td>
		</tr>";
	}
	?>
	</table>
	<p class="submit">
	<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
	</p>

	</form>
</div>
<?php
}
