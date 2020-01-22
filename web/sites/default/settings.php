<?php

/**
 * Load services definition file.
 */
$settings['container_yamls'][] = __DIR__ . '/services.yml';


/**
 * Place the config directory outside of the Drupal root.
 */
$settings['config_sync_directory'] = dirname(DRUPAL_ROOT) . '/config/sync';

/**
 * If there is a local settings file, then include it.
 */
$local_settings = __DIR__ . "/settings.local.php";
if (file_exists($local_settings)) {
  include $local_settings;
}

/**
 * If there is a prod settings file, then include it.
 */
$prod_settings = __DIR__ . "/settings.prod.php";
if (file_exists($prod_settings)) {
  include $prod_settings;
}

/**
 * Always install the 'standard' profile to stop the installer from
 * modifying settings.php.
 *
 * See: tests/installer-features/installer.feature
 */
$settings['install_profile'] = 'minimal';

$settings['hash_salt'] = 'g4DmM0qyBHDGZU0yA6YZwa0jLwfys6KXQ4yfWUE7giP-EsxiPP2ClhPIf4vX4yS0iCQly7hyXg';

// Redirect to https if not on local.
if ($_SERVER['HTTP_HOST'] != 'groups.test' && php_sapi_name() != 'cli') {
  if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == "off") {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirect);
    exit();
  }
}

// Automatically generated include for settings managed by ddev.
if (file_exists($app_root . '/' . $site_path . '/settings.ddev.php')) {
  include $app_root . '/' . $site_path . '/settings.ddev.php';
}
