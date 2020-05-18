=== XLogin ===
Contributors: scoop082110
Donate link: https://www.paypal.me/scoop082110
Tags: login, oauth, google, yahoo, facebook
Requires at least: 5.3
Tested up to: 5.4
Stable tag: 1.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Login to WordPress with external services like Facebook, Google, and Yahoo.

== Description ==

XLogin enhances the WordPress login page (usually wp-login.php) to
allow users to authenticate with the following external services:

* Facebook Login
* Google Sign-In
* Yahoo! OAuth

These services can be enabled or disabled individually. XLogin adds a
button to the WordPress login page for each enabled service. Clicking
the button sends the browser to the corresponding external service
where the user can authorize the WordPress site to access their
information. Having been granted access to, for example, the Facebook
public profile of a user, XLogin retrieves their email address to
find a matching WordPress user to complete the login process.

XLogin also maintains a list of external aliases. They are additional
email addresses for mapping to WordPress users. Some scenarios where
external aliases can be handy include:

1.  A user's email address in his WordPress profile is not used in any
    of the external services. For example, a corporate WordPress site
    may mandate the use of company email addresses in user
    profiles. If a user has for example his Gmail address in the
    external alias list, they can still nevertheless sign in Google.

1.  A WordPress user account is shared by a group of actual
    users. With XLogin it is not necessary to have the users share a
    single password. Instead just register their email addresses (as
    known by the external services) as external aliases; it becomes a
    simple matter to add and remove user.

XLogin has the option to restrict an external service to only users with
external aliases. This allows tight control on who can use external
services to login.

A user's profile in WordPress and in the external service may be
different.  Email address is one, and display name (or its component
family and given names) is another. XLogin offers the option to import
the external profile information into the current session. The imported
display name will be tagged with the external service name. For
example, if a user has display name 'John Doe' in WordPress, but is
known as 'Johnny D' in his Facebook account, then he would be
displayed as 'Johnny D (Facebook)' (instead of 'John Doe') in his
WordPress session when he logs in with Facebook.

== Installation ==

1.  Upload the plugin files to the '.../wp-content/plugins/xlogin'
    directory, or install the plugin through the 'Plugin's screen in
    WordPress.
1.  Activate the plugin.
1.  Configure external services for login on the Settings->XLogin page.
    * Enter configuration data for the external service. For an
      OAuth2 based service, that means client ID and client
      secret. Note that the redirect URI for OAuth2 is displayed here.
    * Set per-service options:
      - Restrict to users with external aliases.
      - Import profile information (email address and name) from
        external service into session.
    * Enable external services.
1.  Maintain external aliases on the Settings->XLogin page.
    * Aliases may be added/updated/deleted one at a time.
    * Filters may be applied to the list of aliases displayed.
    * Multiple aliases may be uploaded in a CSV file. Each line in the
      file contains an email address and a WordPress user name,
      separated by comma.

If WordPress permalinks are 'plain', one may need to configure the
web server to route callbacks from external service to WordPress
index.php script. For Apache that would mean rewrite rules in
.htaccess like theses:
```
  RewriteEngine On
  RewriteRule wp-content/plugins/xlogin/callback/ index.php [L]
```

== Frequently Asked Questions ==

= Does this work with WordPress version X? =

This plugin is developed on WordPress 5.3. It has not been tried on any
other version.

= Does this work with PHP 5.x? =

No. This plugin uses various PHP 7.x features. Backporting to PHP 5.x
should not be difficult however.

= How do I obtain client ID and secret to configure an OAuth2 based external service( e.g. Facebook)? =

Here are some pointers:

* Google. A project must first be set up. OAuth2 clients are
  managed on the [API credentials][lk1] page. Use an existing or
  create a new OAuth client, of 'web application' type. 

* Facebook. An 'app' must first be set up. Use the 'App ID' and
  'App Secret' from the its basic settings page for client ID and
  secret respectively. Add Facebook Login to the product list of the
  app, and configures the redirect URI there.

* Yahoo. A Yahoo app corresponds to an OAuth2 client. Make sure
  your app has email and profile permissions for OpenID Connect.

[lk1]: https://console.developers.google.com/apis/credentials

= A user tries to login with Google, but gets sent back to the WordPress login page with a "Google user not recognized" error. What does this mean? =

XLogin uses the email address provided by Google (or whatever external
service) to map to a WordPress user. Check the following:

* Is the email address registered as an external alias in XLogin?

* Does email address belong to a WordPress user profile? If so,
  make sure the external service is not configured as 'restricted'.

= The "*XYZ* user not recognized" error is confusing. Can the login page show only external login buttons that are applicable to the user? =

Generally XLogin may not know anything about the user until the end of
authentication/authorization with the external service, so it would be
a challenge.

= How does XLogin override the email and display name of a user? =

This is rather technical, but is important for ongoing maintenance of
the plugin. This answer is intended for PHP developers working with
WordPress.

WordPress keeps track of the user of the current request in various PHP
global variables. XLogin installs a callback for the `set_current_user`
action to inject profile information from external service into them.
The action name and the global data structures may be specific to
WordPress versions; the file `init.php` and the PHP method
`XLogin::importXUser()` are expected to be modified to support more
WordPress versions.

= Can XLogin be used for new user registration? =

This is not supported currently.

= Can XLogin support other external services? =

XLogin uses the [OAuth 2 Client][lk2] from The League of
Extraordinary Packages. It should be straightforward to add an
additional OAuth2 based login service as long as a compatible
*provider* is available. See the [list of providers][lk3].

[lk2]: https://oauth2-client.thephpleague.com/
[lk3]: https://oauth2-client.thephpleague.com/providers/league/

== Screenshots ==

1.  WordPress login page with buttons for external logins.
2.  Display name of user imported from external service, e.g. Facebook.
3.  XLogin settings page.
4.  Configuration of OAuth2 based external service, e.g. Google.
    Note the redirect URI that should be added to the OAuth2 client
    configuration in the external service.
5.  Add or update an external alias.
6.  Upload CSV file of external aliases.

== Changelog ==

= 1.0.1 (in progress) =
* Custom message to display with external login buttons.

= 1.0 =
* First version published.

== Upgrade Notice ==

None yet.
