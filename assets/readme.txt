=== wpSOL ===
Contributors: koter84, Gerrit Jan Faber
Requires at least: 3.6
Tested up to: 4.0
Stable tag: trunk
Tags: scouting, scouting nederland, sol, scoutsonline, openid, login, sidebar-widget
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Connect Wordpress to the Scouting Nederland OpenID-server

== Description ==
wpSOL connects WordPress to the Scouting Nederland OpenID server to allow people to login and register with their login-account from scouting.nl

this plugin connects over https to login.scouting.nl to verify the login as part of the openid-standard

bugs and feature-requests can go to: [GitHub](https://github.com/koter84/wpSOL/issues)

== Screenshots ==
1. wp-login.php met scouting-login
2. sidebar-widget in theme twenty-thirteen
3. sidebar-widget in custom theme

== Installation ==
1. login to sol.scouting.nl and change your role to "webmaster". 
1. go to login.scouting.nl move your mouse over "mijn websites" and click on "voeg beheerde website toe"
1. enter the domain which is setup for wordpress and select the organization you want to give access.

1. install and activate the plugin, that's it.

== Frequently Asked Questions ==

= Do i need to be part of Scouting Nederland to use this? =

Yes, the login-server is used to identify people and only allow access when they are a member of the right organisation within Scouting Nederland.
To setup the system at Scouting Nederland you need the webmaster-privilege for your scouting-group.

= Are there settings for this plugin? =

Yes, there is a settings-page where you can setup the Name the user gets in their profile and enforce that.

== Changelog ==

= v1.0.2 =
* moved code from bitbucket to github

= v1.0.1 =
* some minor changes

= v1.0 =
* code cleanup

= v0.5 =
* i18n-support added to plugin

= v0.4 =
* setup default options during installation
* better login-flow
* username_prefix and autocreate new user options added
* better display of options page

= v0.3 =
* first public wordpress.org release
* make sidebar widget follow wordpress coding guidelines

= v0.2 =
* added a sidebar-login-widget
* added a settings page
* removed static-setting of domain-names

= v0.1 =
* initial internal release
