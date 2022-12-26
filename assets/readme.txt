=== wpSOL ===
Contributors: koter84
Requires at least: 3.6
Tested up to: 6.1.1
Stable tag: 1.2.0
Tags: scouting, scouting nederland, sol, scoutsonline, openid, login, sidebar-widget
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Connect Wordpress to the Scouting Nederland OpenID-server

== Description ==

wpSOL connects WordPress to the Scouting Nederland OpenID-server to allow people to login and register with their login-account from scouting.nl

this plugin connects over https to login.scouting.nl to verify the login as part of the openid-standard

bugs and feature-requests can go to: [GitHub](https://github.com/koter84/wpSOL/issues) or [WordPress](https://wordpress.org/support/plugin/wpsol)

== Screenshots ==

1. wp-login.php with scouting-login
2. sidebar-widget in theme twenty-fifteen
3. sidebar-widget in theme twenty-fourteen
4. sidebar-widget in theme twenty-thirteen
5. sidebar-widget in theme twenty-twelve
6. sidebar-widget in custom theme

== Installation ==

1. login to sol.scouting.nl and change your role to "webmaster". 
1. go to login.scouting.nl move your mouse over "mijn websites" and click on "voeg beheerde website toe".
1. enter the domain which is setup for wordpress and select the organization you want to give access.
1. install and activate the plugin, that's it.

== Frequently Asked Questions ==

= It's not working! =

You probably need to add the domain of your website to login.scouting.nl, you can only do this when you are logged in as a webmaster.
Check the [installation-tab](https://wordpress.org/plugins/wpsol/installation/) for a full explanation of how to do this.

= Do i need to be part of Scouting Nederland to use this? =

Yes, the OpenID-server is used to identify people and only allow access when they are a member of the right organisation within Scouting Nederland.
To setup the system at Scouting Nederland you need the webmaster-privilege for your scouting-group.

= Are there settings for this plugin? =

Yes, there is a settings-page where you can setup a redirect after login or logout and setup the Name the user gets in their profile and enforce that.

== Changelog ==

= 1.2.0 =
* added optional profile fields birthdate, gender and scouting_id which get synced from scouting.nl on every login (when enabled in plugin settings)
* checked for compatibility with wordpress 6.1.1

= 1.1.13 =
* checked for compatibility with wordpress 5.4.0

= 1.1.12 =
* checked for compatibility with wordpress 5.2.3

= 1.1.11 =
* old-style array() instead of []

= 1.1.10 =
* fixed most ToDo's in code
* replaced require() with include_once()

= 1.1.9 =
* plugin authors changed to only include Dennis
* add settings link in plugins overview page

= 1.1.8 =
* fix automatically creating users

= 1.1.7 =
* fix for disabling checkbox options on the settings page
* return an error message to a new user when creating new users is disabled in settings

= 1.1.6 =
* checked for compatibility with wordpress 4.5
* initial support for translate.wordpress.org

= 1.1.5 =
* some code standarization

= 1.1.4 =
* checked for compatibility with wordpress 4.4

= 1.1.3 =
* checked for compatibility with wordpress 4.3

= 1.1.2 =
* added error message when username and email exist in wordpress, but are not the same account.

= 1.1.1 =
* minor fixes to supress some warnings

= 1.1.0 =
* Scouting Nederland changed the response from the server, now it works with standard LightOpenID again
* updated readme to include dutch translations
* show error when login is cancelled
* widget can now show links for creating a new message and upload files
* removed deprecated functions for widget ( you need to replace the widget )
* updated widget output to match standard widgets

= 1.0.3 =
* added a redirect option for login and logout to go to the frontpage

= 1.0.2 =
* moved code from bitbucket to github

= 1.0.1 =
* some minor changes

= 1.0 =
* code cleanup

= 0.5 =
* i18n-support added to plugin

= 0.4 =
* setup default options during installation
* better login-flow
* username_prefix and autocreate new user options added
* better display of options page

= 0.3 =
* first public wordpress.org release
* make sidebar widget follow wordpress coding guidelines

= 0.2 =
* added a sidebar-login-widget
* added a settings page
* removed static-setting of domain-names

= 0.1 =
* initial internal release

== Upgrade Notice ==

= 1.1.0 =
Beware! The login-widget has been re-written to have the same code-output as standard widgets.
Because of this you need to re-place the widget on your site, if you made custom CSS rules for this they won't work anymore!
Keep in mind that after this update you need to update your custom-theme.

== Translations ==

* Dutch
* English
