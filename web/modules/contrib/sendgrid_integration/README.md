SendGrid Integration for Drupal
--------------------------------------------------------------------------------
This project is not affiliated with SendGrid, Inc.

Use the issue tracker located
at [Drupal.org](https://www.drupal.org/sendgrid_integration)
for bug reports or questions about this module. If you want more info about
SendGrid services, contact [SendGrid](https://sendgrid.com).

This module uses a wrapper library for the SendGrid API. At the moment the
wrapper library is for V2 of the API. V3 upgrade is being developed.

FUNCTIONALITY
--------------------------------------------------------------------------------
This module overrides default email sending behaviour and sending emails through
SendGrid Transactional Email service instead. Emails are sent via a web service
and does not function like SMTP therefore there are certain caveats with other
email formatting modules. Read below for details.

Failures to send are re-queued for sending later. Queue of failed messages are
run on a 60 minute interval.

REQUIREMENTS
--------------------------------------------------------------------------------

- [Mailsystem](https://www.drupal.org/project/mailsystem) - A module to create
  an agnostic management layer for Mail. Very useful for controlling the mail
  system on Drupal.
- [Key module](https://www.drupal.org/project/key) - []
- PHP dependencies for this module are loaded via Composer in Drupal 8.

INSTALLATION
--------------------------------------------------------------------------------
Before starting your installation, be aware that this module uses composer to
load dependencies. In Drupal 8, there are different ways to configure your site
to
use [composer for contributed modules](https://www.drupal.org/node/2718229#managing-contributed).

As of recent changes with Drush for Drupal 8.4, there is no option to download
a module with Drush. All downloading of modules now resides with composer.

## Installation via command line and composer

- Start at the root of your Drupal installation and run this command:

        composer require drupal/sendgrid_integration

  The module will be downloaded from drupal.org, the dependency API wrapper will
  be downloaded from Github, and the site's composer.json and composer.lock
  files will be updated.
- Navigate to the "Extend" page in the Drupal UI and enable the "SendGrid
  Integration" item in the "Mail" category.

Composer
Documentation: [https://getcomposer.org/doc/](https://getcomposer.org/doc/)

### Configuration without the Key module

If the [Key module](https://www.drupal.org/project/key) is not installed,
perform the following steps:

- Go to Configuration > System > Sendgrid Integration
  (`admin/config/services/sendgrid`).
- Copy the API key value from the SendGrid dashboard to the "API Secret Key"
  field on the form.
- Click "Save" to store the key.

Note: it is not currently possible to perform this simpler approach to
configuration if the Key module is installed, the steps below must be used.

#### Optional: manage the API key secure via settings.php

Modern security practices recommend storing API keys in a file that is not
stored in the code repository, to avoid anyone else using the key should they
gain unauthorized access to the repository. The use of the Key module makes this
a little more complicated.

In order to control the API key via settings.php it is first necessary to build
the configuration as described above so that it all works correctly. Once that
has been confirmed, make the following changes:

- Modify the settings.php file (or a file loaded by it) to add the following:

        // SendGrid Integration: API key.
        $config['sendgrid_integration.settings']['apikey'] = 'THEAPIKEY';

  Replace "THEAPIKEY" with the API key provided by SendGrid.
- Modify the configuration in the UI to replace the stored API key with a
  placeholder, e.g. `API-KEY-IS-IN-SETTINGS-FILE`.
- Try sending a test message from `admin/config/services/sendgrid/test` to
  ensure it is still working.
- Presuming everything is still working correctly, export the site's
  configuration and commit the changes to the key yml file, but not the
  settings.php (or file loaded by settings.php).

### Configuration with the Key module

If the [Key module](https://www.drupal.org/project/key) is installed it will be
used for managing the API key; it is not currently possible to

Configure the site as follows:

- Create a key definition using the Key module:
    - Load the Key module's settings page: `admin/config/system/keys`
    - Click the "Add key" button.
    - Fill in a name for this key, e.g. "SendGrid API key".
    - Fill in a description for this key, if needed.
    - For the "Key type" field select "Authentication".
    - For the "Key provider" field select "Configuration".
    - For the "Key value" field, paste in the API key provided by SendGrid.
    - Click "Save".
- In the SendGrid settings (`admin/config/services/sendgrid`) on the "API-key"
  field select the key created above.
- Confirm that the mail system is setup to use Sendgrid for how you wish to run
  your website. If you want it all to run through Sendgrid then you set the
  System-wide default MailSystemInterface class to "SendGridMailSystem". As an
  example, see this image:
  `https://www.drupal.org/files/issues/sengrid-integration-mailsystem-settings-example.png`

#### Optional: manage the API key secure via settings.php

Modern security practices recommend storing API keys in a file that is not
stored in the code repository, to avoid anyone else using the key should they
gain unauthorized access to the repository. The use of the Key module makes this
a little more complicated.

In order to control the API key via settings.php it is first necessary to build
the configuration as described above so that it all works correctly. Once that
has been confirmed, make the following changes:

- Modify the settings.php file (or a file loaded by it) to add the following:

        // SendGrid Integration: API key.
        $config['key.key.SENDGRIDKEYNAME']['key_provider_settings']['key_value'] = 'THEAPIKEY';

  Replace "SENDGRIDKEYNAME" with the machine name of the key item created
  earlier, and "THEAPIKEY" with the API key provided by SendGrid.
- Modify the configuration in the UI to replace the stored API key with a
  placeholder, e.g. `API-KEY-IS-IN-SETTINGS-FILE`.
- Try sending a test message from `admin/config/services/sendgrid/test` to
  ensure it is still working.
- Presuming everything is still working correctly, export the site's
  configuration and commit the changes to the key yml file, but not the
  settings.php (or file loaded by settings.php).

HTML Email
--------------------------------------------------------------------------------
In order to send HTML email. Your installation of Drupal must generate an email
with the proper headers.

We recommend using the
module [Mimemail](https://www.drupal.org/project/mimemail)
for HTML formatting of emails.

NOTE: Please note that SendGrid Integration also adds a "per module" setting
for\
the mail formatter/sender below where the site default is defined. The formatter
there needs to be changed to Mimemail.

OPTIONAL
--------------------------------------------------------------------------------
If sending email fails with certain (predefined) response codes the message be
added to Cron Queue for later delivery. In order for this to function, you must
configure Cron running period and when it is possible also add your drupal site
to crontab (Linux only), read more about cron at https://www.drupal.org/cron.

If you would like a record of the emails being sent by the website, installing
Maillog (https://www.drupal.org/project/maillog) will allow you to store local
copies of the emails sent. Sendgrid does not store the content of the email.


DEBUGGING
--------------------------------------------------------------------------------
Debugging this module while installed can be done by installing the Maillog
module (https://www.drupal.org/project/maillog). This module will allow you to
store the emails locally before they are sent and view the message generated
in the Sendgrid email object.

RESOURCES
--------------------------------------------------------------------------------
Information about the Sendgrid PHP Library is available on Github:
https://github.com/taz77/sendgrid-php-ng
