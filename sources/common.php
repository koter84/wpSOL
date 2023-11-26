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

	$echo = "";

	if( !$args['sidebar'] )
		$echo .= '<hr id="openid_split" style="clear: both; margin-bottom: 1.0em; border: 0; border-top: 1px solid #999; height: 1px;" />';

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
		<label style="display: block; margin-bottom: 5px;">'.__('Login with your SOL account', 'wpsol').'<br />
		<input type="text" name="openid_identifier" id="openid_identifier" class="input openid_identifier" value="" size="20" /></label>
	</p>
	<script type="text/javascript">
		document.getElementById("openid_identifier").addEventListener("change", (e)=>{
				const user = document.getElementById("user_login");
				const pass = document.getElementById("user_pass");

				if(e.target.value) {
						user.required = false;
						pass.required = false;
				}
				else {
						user.required = true;
						pass.required = true;
				}
		});
	</script>';

	if( $args['echo'] )
		echo $echo;
	else
		return $echo;
}

// Sidebar Login
function wpsol_sidebar_login()
{
	$result = "";

	if( is_user_logged_in() )
	{ // ingelogd
		if( get_option('wpsol_widget_links_show') )
		{
			$result = "<h1 class=\"widget-title\">".__('Members-Area', 'wpsol')."</h1>";

			$result .= "<ul>";
			// link naar nieuw bericht
			if(current_user_can('edit_posts'))
				$result .= "<li><a href=\"/wp-admin/post-new.php\">".__('Write a Post', 'wpsol')."</a></li>";
			// link naar upload
			if(current_user_can('upload_files'))
				$result .= "<li><a href=\"/wp-admin/media-new.php\">".__('Upload Media', 'wpsol')."</a></li>";
			// link naar logout
			$result .= "<li><a href=\"/wp-login.php?action=logout\">".__('Log Out')."</a></li>";
			$result .= "</ul>";
		}
	}
	else
	{
		// haal het "normale" login formulier op
		$result = wp_login_form(array('echo'=>false, 'remember'=>false));

		// verwijder username-input
		$result = substr($result, 0, strpos($result, '<p class="login-username">')).substr($result, strpos($result, '</p>', strpos($result, '<p class="login-username">'))+4);

		// verwijder password-input
		$result = substr($result, 0, strpos($result, '<p class="login-password">')).substr($result, strpos($result, '</p>', strpos($result, '<p class="login-password">'))+4);
	}

	if($result != "")
		echo "<aside id=\"wpsol_widget-5\" class=\"widget widget_wpsol\">".$result."</aside>";
}

function wpsol_sidebar_login_control()
{
	//the form is submitted, save into database
	if (isset($_POST['submitted']))
	{
		update_option('wpsol_widget_links_show', $_POST['widget_links_show']);
	}

	if(get_option('wpsol_widget_links_show') == 1)
		$sel = "CHECKED";
	else
		$sel = "";
	echo "<br/>".__('Show links when logged in: ', 'wpsol')." <input type=\"checkbox\" name=\"widget_links_show\" value=\"1\" ".$sel." /><br/>";

	echo "<br/><input type=\"hidden\" name=\"submitted\" value=\"1\" />";
}

// Authenticatie Hook
function wpsol_authenticate_username_password()
{
	# get domain-name from wordpress-settings
	$openid = new LightOpenID(get_site_url());

	if( array_key_exists('openid_identifier', $_POST) && $_POST['openid_identifier'] )
	{ // Attempt to authenticate user
		try
		{
			if( !$openid->mode )
			{
				if( isset( $_POST['openid_identifier'] ) )
				{
					$openid->identity = 'https://login.scouting.nl/user/' . $_POST['openid_identifier'];
					# The following two lines request email, full name, and a nickname
					# from the provider. Remove them if you don't need that data.
					$openid->required = array('contact/email','namePerson', 'namePerson/friendly');
					$openid->optional = array('birthDate','person/gender','contact/postalCode/home','contact/country/home','pref/language','pref/timezone');
					header('Location: ' . $openid->authUrl());
				}
			}
		}
		catch( ErrorException $e )
		{
			return new WP_Error( 'exception_error', "<strong>ERROR</strong>: " . $e->getMessage() );
		}
	}
	if( $openid->mode )
	{
		if( $openid->validate() )
		{
			$new_user = false;

			$gegevens = $openid->getAttributes();
			$username = get_option('wpsol_username_prefix').$gegevens['namePerson/friendly'];
			$email = $gegevens['contact/email'];

			$user_id = username_exists( $username );
			$email_id = email_exists( $email );

			if( !$user_id && !$email_id )
			{ // geen user_id, geen email_id, create new user.
				if( get_option('wpsol_autocreate') )
				{
					$random_password = wp_generate_password( 18, false );
					$user_id = wp_create_user( $username, $random_password, $email );
					$new_user = true;
					$user = get_user_by( 'email', $email );
				}
				else
				{
					global $error;
					$error = __('New user registrations through login.scouting.nl have been disabled for this site, please contact the site administrator if you feel this is incorrect', 'wpsol').'<br/><a href="https://wordpress.org/plugins/wpsol/installation/">'.__('wpSOL Setup Instructions', 'wpsol').'</a>';
					return false;
				}
			}
			elseif( !$user_id )
			{ // geen user_id, wel email_id, login
				// gebruiker bestaat maar met een andere username dan bij SOL, bijvoorbeeld de site beheerder o.i.d.
				$user = get_user_by( 'id', $email_id );
			}
			elseif( !$email_id )
			{ // geen email_id, wel user_id, login
				$user = get_user_by( 'id', $user_id );
				// update email voor de user, aangezien die blijkbaar veranderd is
				wp_update_user( array('ID' => $user_id, 'user_email' => $email) );
			}
			elseif( $user_id == $email_id )
			{ // login.
				$user = get_user_by( 'id', $user_id );
			}
			elseif( $user_id != $email_id )
			{ // user_id en email_id komen niet overeen
				global $error;
				$error = sprintf(__('wp-user-id based on username (%s) does not match wp-user-id based on email (%s)', 'wpsol'), $username, $email).'<br/><a href="https://wordpress.org/plugins/wpsol/installation/">'.__('wpSOL Setup Instructions', 'wpsol').'</a>';
				return false;
			}
			else
			{ // uhm, geen idee wat er fout gaat...
				global $error;
				$error = sprintf(__('Error 14: Something went wrong, please notify the site administrator [%s|%s|%s|%s]', 'wpsol'), $username, $email, $user_id, $email_id).'<br/><a href="https://wordpress.org/plugins/wpsol/installation/">'.__('wpSOL Setup Instructions', 'wpsol').'</a>';
				return false;
			}

			if( $new_user || get_option('wpsol_force_display_name') )
			{
				switch( get_option('wpsol_display_name') )
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
				wp_update_user( array( 'ID' => $user->ID, 'display_name' => $display_name ) );
			}

			if( $new_user || get_option('wpsol_force_first_last_name') )
			{
				update_user_meta( $user->ID, 'first_name', substr($gegevens['namePerson'], 0, strpos($gegevens['namePerson'], " ") ) );
				update_user_meta( $user->ID, 'last_name', substr($gegevens['namePerson'], strpos($gegevens['namePerson'], " ")+1 ) );
			}

			if( get_option('wpsol_store_profile_birthdate') )
			{
				update_user_meta( $user->ID, 'wpsol_birthdate', $gegevens['birthDate'] );
			}

			if( get_option('wpsol_store_profile_gender') )
			{
				update_user_meta( $user->ID, 'wpsol_gender', $gegevens['person/gender'] );
			}

			if( get_option('wpsol_store_profile_scouting_id') )
			{
				update_user_meta( $user->ID, 'wpsol_scouting_id', $gegevens['contact/postalCode/home'] );
			}

			// add login filter to redirect
			add_filter( 'login_redirect', 'wpsol_login_redirect' );

			return $user;
		}
		elseif($openid->mode == "cancel")
		{
			global $error;
			$error = sprintf(__('The login was cancelled, either the user cancelled the request, or login.scouting.nl isn\'t aware of your domain (%s)', 'wpsol'), get_site_url()).'<br/><a href="https://wordpress.org/plugins/wpsol/installation/">'.__('wpSOL Setup Instructions', 'wpsol').'</a>';
		}
		else
		{
			global $error;
			$error = sprintf(__('The login failed with openid-mode: "%s"', 'wpsol'), $openid->mode).'<br/><a href="https://wordpress.org/support/plugin/wpsol">'.__('wpSOL Support', 'wpsol').'</a>';
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
	update_option('wpsol_store_profile_birthdate', false);
	update_option('wpsol_store_profile_gender', false);
	update_option('wpsol_store_profile_scouting_id', false);
	update_option('wpsol_username_prefix', 'sn_');
	update_option('wpsol_autocreate', true);
	update_option('wpsol_login_redirect', 'default');
	update_option('wpsol_logout_redirect', 'default');
	// widget options
	update_option('wpsol_widget_links_show', false);
}

// Redirect after login
function wpsol_login_redirect()
{
	if( get_option('wpsol_login_redirect') == "frontpage" )
	{
		return "/";
	}
	elseif( get_option('wpsol_login_redirect') == "dashboard" )
	{
		return "/wp-admin/";
	}
}

// Redirect after logout
function wpsol_logout_redirect()
{
	if( get_option('wpsol_logout_redirect') == "frontpage" )
	{
		wp_redirect( home_url() );
		exit;
	}
}

// Geef extra links op de plugin-overzichtspagina
function wpsol_plugin_action_links( $links )
{
	array_unshift($links, '<a href="'.esc_url(get_admin_url(null, 'options-general.php?page=wpsol_settings')).'">'.__('Settings', 'wpsol').'</a>');
	return $links;
}

// Show extra fields (birthdate/gender/scouting_id) on profile page
function extra_user_profile_fields( $user )
{
	echo "<h3>".__('wpSOL profile information', 'wpsol')."</h3>";
    echo "<table class='form-table'>";

	if( get_option('wpsol_store_profile_birthdate') )
	{
		echo "
		<tr>
			<th><label for='birthdate'>".__('Birthdate', 'wpsol')."</label></th>
			<td>
				<input disabled type='text' name='birthdate' id='birthdate' value='".esc_attr( get_the_author_meta( 'wpsol_birthdate', $user->ID ) )."' class='regular-text' /><br />
			</td>
		</tr>";
	}

	if( get_option('wpsol_store_profile_gender') )
	{
		echo "
		<tr>
			<th><label for='gender'>".__('Gender', 'wpsol')."</label></th>
			<td>
				<input disabled type='text' name='gender' id='gender' value='".esc_attr( get_the_author_meta( 'wpsol_gender', $user->ID ) )."' class='regular-text' /><br />
			</td>
		</tr>";
	}

	if( get_option('wpsol_store_profile_scouting_id') )
	{
		echo "
		<tr>
			<th><label for='gender'>".__('Scouting ID', 'wpsol')."</label></th>
			<td>
				<input disabled type='text' name='scouting_id' id='scouting_id' value='".esc_attr( get_the_author_meta( 'wpsol_scouting_id', $user->ID ) )."' class='regular-text' /><br />
			</td>
		</tr>";
	}

	echo "</table>";
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
			'name' => __('Set display_name to: ', 'wpsol'),
			'type' => 'select',
			'options' => array(
				'fullname' => __('Full name', 'wpsol'),
				'firstname' => __('First name', 'wpsol'),
				'lastname' => __('Last name', 'wpsol'),
				'username' => __('Username', 'wpsol'),
			),
		),
		'wpsol_force_display_name' => array(
			'name' => __('Force display_name on each login: ', 'wpsol'),
			'type' => 'checkbox',
		),
		'wpsol_force_first_last_name' => array(
			'name' => __('Force first and last name on each login: ', 'wpsol'),
			'type' => 'checkbox',
		),
		'wpsol_store_profile_birthdate' => array(
			'name' => __('Store birthdate to local profile: ', 'wpsol'),
			'type' => 'checkbox',
		),
		'wpsol_store_profile_gender' => array(
			'name' => __('Store gender to local profile: ', 'wpsol'),
			'type' => 'checkbox',
		),
		'wpsol_store_profile_scouting_id' => array(
			'name' => __('Store Scouting ID to local profile: ', 'wpsol'),
			'type' => 'checkbox',
		),
		'wpsol_autocreate' => array(
			'name' => __('Automatically create new users: ', 'wpsol'),
			'type' => 'checkbox',
		),
		'wpsol_username_prefix' => array(
			'name' => __('Prefix for all Scouting Nederland users: ', 'wpsol'),
			'type' => 'text',
			'help' => __('By giving a prefix like sn_ you can easily identify which accounts are from Scouting Nederland', 'wpsol'),
		),
		'wpsol_login_redirect' => array(
			'name' => __('After a successful login redirect user to: ', 'wpsol'),
			'type' => 'select',
			'options' => array(
				'default' => __('Default (no action)', 'wpsol'),
				'frontpage' => __('Frontpage', 'wpsol'),
				'dashboard' => __('Dashboard', 'wpsol'),
			),
		),
		'wpsol_logout_redirect' => array(
			'name' => __('After logout redirect user to: ', 'wpsol'),
			'type' => 'select',
			'options' => array(
				'default' => __('Default (no action)', 'wpsol'),
				'frontpage' => __('Frontpage', 'wpsol'),
			),
		),
	);

	// See if the user has posted us some information
	$hidden_field_name = 'wpsol_hidden';
	if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' )
	{
		foreach($options as $key => $opt)
		{
			// Save the posted value in the database
			if(isset($_POST[$key]))
			{
				update_option( $key, $_POST[$key] );
			}
			elseif($opt['type'] == "checkbox")
			{
				update_option( $key, 0 );
			}
		}
		// Put an settings updated message on the screen
		echo "<div class=\"updated\"><p><strong>".__('Settings Saved', 'wpsol')."</strong></p></div>";
    }

    // Now display the settings editing screen
    ?>
<div class="wrap">
	<div id="icon-options-general" class="icon32"><br></div>
	<h2><?php _e('wpSOL [ScoutsOnLine] Settings', 'wpsol'); ?></h2>
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

		if(isset($opt['help']))
			$input .= "<p class=\"description\">".$opt['help']."</p>";

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
