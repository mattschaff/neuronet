<?php

/**
 * Load services definition file.
 */
$settings['container_yamls'][] = __DIR__ . '/services.yml';

/**
 * Include the Pantheon-specific settings file.
 *
 * n.b. The settings.pantheon.php file makes some changes
 *      that affect all envrionments that this site
 *      exists in.  Always include this file, even in
 *      a local development environment, to insure that
 *      the site settings remain consistent.
 */
include __DIR__ . "/settings.pantheon.php";

/**
 * If there is a local settings file, then include it
 */
$local_settings = __DIR__ . "/settings.local.php";
if (file_exists($local_settings)) {
  include $local_settings;
}

/**
 * If there is a medical settings file, then include it
 */
$med_settings = __DIR__ . "/settings.med.php";
if (file_exists($med_settings) && !file_exists($local_settings)) {
  include $med_settings;
}

/**
 * If on the med (production) server, configure redirects
 * @see https://pantheon.io/docs/redirects
 */
if (defined('MED_SERVER') && constant('MED_SERVER') && php_sapi_name() !== 'cli') {
  $primary_domain = 'www.neuronetupenn.org';
  $requires_redirect = FALSE;

  if ($_SERVER['HTTP_HOST'] !== $primary_domain) {
    $requires_redirect = TRUE;
    $redirect_path = $_SERVER['REQUEST_URI'];
  }

  if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off'
      && $_SERVER['SERVER_PORT'] === 80) {
    $requires_redirect = TRUE;
    $redirect_path = $_SERVER['REQUEST_URI'];
  }

  if ($requires_redirect) {
    header('HTTP/1.0 301 Moved Permanently');
    header('Location: https://' . $primary_domain . $redirect_path);
    exit();
  }

  // Drupal 8 Trusted Host Settings
  if (is_array($settings)) {
    $settings['trusted_host_patterns'] = array('^' . preg_quote($primary_domain) . '$');
  }
}
