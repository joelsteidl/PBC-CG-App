# PBC CG App
This Drupal 8 application provides automation for community group management and attendance tracking and reporting.

## CRON
CRON runs every 15 minutes.

### Planning Center API
pbc_automation module defines `hook_cron` and checks for updated people from the Planning Center Online database. The PCO API (pco_api) module takes advantage of the Drupal httpClient https://api.drupal.org/api/drupal/core!lib!Drupal.php/function/Drupal%3A%3AhttpClient/8.2.x. The module will likely get released as a sandbox or a full project if I get a little more time. You are welcome to grab the code from this repo if you would benefit from it.

### Sending Emails
CRON also triggers emails througout the week based on when each group meets. The emails are sent with SMTP through sendgrid.

## Updating Drupal & Contrib
This site relies on composer. You can run `composer install` or `composer update`.

## People Involved
Product Owner - Grant Pestka
Developer - Joel Steidl
