# Secure Login module

For sites that are available via both HTTP and HTTPS, Secure Login
module ensures that the user login and other forms are submitted
securely via HTTPS, thus preventing passwords and other private user
data from being transmitted in the clear.  Secure Login module locks
down not just the user/login page but also any page containing the user
login block, and any other forms that you configure to be secured.

In Drupal 7 or later, logging in via HTTPS automatically generates an
[HTTPS-only secure session](https://php.net/manual/session.configuration.php#ini.session.cookie-secure),
which prevents session cookies from being
[sent in cleartext](https://en.wikipedia.org/wiki/Session_hijacking).


## Requirements

Before enabling the module, you need to set up your server to support
HTTPS and ensure that it works correctly.  You can use
[Certbot](https://certbot.eff.org/) to obtain a free TLS certificate.
The result should be that if your Drupal site lives at
http://www.example.org/dir, it should also be accessible at
https://www.example.org/dir (the secure base URL, which is normally
determined automatically, but can be configured if you need to limit it
to one base URL).


## Installation

0. See the requirements section above.
1. Ensure the HTTPS version of your site works.
2. Run `composer require drupal/securelogin`
3. Read the README.md before enabling the module and before upgrading!
4. Enable the module at admin/modules.
5. Configure the module at admin/config/people/securelogin.


## Uninstallation

If you did not follow step 1 above, or you copied your Drupal site to a
local instance which does not have HTTPS enabled, you may not be able to
login to your Drupal site to disable Secure Login module normally.
Instead you will need to use Drush.

1. Uninstall Secure Login module: `drush pmu securelogin`
2. Clear your browser cache.


## Configuration

At admin/config/people/securelogin you can set which forms (login,
registration, node, comment, contact, webform, etc.) are secured by this
module.  By securing all forms that indicate they "must be checked to
enforce secure authenticated sessions," you can ensure that logins are
in fact "secure": all authenticated sessions will use HTTPS-only secure
session cookies which are immune to session hijacking by eavesdroppers.


## Recommendation: HTTP Strict Transport Security

In addition to installing Secure Login module, it is recommended to set
the [Strict-Transport-Security header](https://en.wikipedia.org/wiki/HTTP_Strict_Transport_Security)
in your webserver or [Security Kit module](https://www.drupal.org/project/seckit)
configuration. To instruct browsers to connect to your site only via
HTTPS, add your domain to the [HSTS preload list](https://hstspreload.org/).


## Upgrading from Drupal 7

Your Secure Login settings should be correctly migrated from Drupal 7
via the "Secure login settings" migration.  In addition, if you used
Secure Pages module with Drupal 7, the secure base URL setting can be
migrated from Secure Pages to Secure Login.


## Developer API

As with the Drupal 7 version of Secure Login module, developers may use
`$form['#https'] = TRUE` to indicate that a form should be secured by
Secure Login module, and `$options['https'] = TRUE` to indicate that an
HTTPS URL should be generated.

Additionally, this module provides two API functions for developers:

`\Drupal::service('securelogin.manager')->secureForm($form)` may be
called on a form to either redirect the current request to the secure
base URL or to submit the form to the secure base URL, depending on
Secure Login configuration.

`\Drupal::service('securelogin.manager')->secureRedirect()` may be
called to redirect the current request to the equivalent path on the
secure base URL.


## Maintainers

Secure Login module is currently maintained by
[mfb](https://www.drupal.org/u/mfb). You can support development by
[contributing](https://www.drupal.org/project/issues/securelogin) or
[sponsoring](https://github.com/sponsors/mfb).
