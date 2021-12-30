<?php

/**
 * @file
 * Create alumni accounts from CSV
 * Runs as a Drush script.
 * Looks like it only creates accounts for profiles that already exist.
 * Probably not useful for the current setup and would be have to be updated for Drush 10.
 */

use Drupal\Core\Entity\EntityInterface;

// Get CSV argument.
$argument_csv = drush_get_option('csv') ? drush_get_option('csv') : false;
if (!$argument_csv) {
  echo "\n No CSV file provided \n";
  exit();
}

// Parse CSV.
$csv = array_map('str_getcsv', file($argument_csv ));
if (!$csv) {
  echo "\n CSV file could not be parsed. \n";
  exit();
}

// Search through CSV.
foreach ($csv as $key => $value) {
  if ($node = _create_alumni_accounts__get_node($value)) {
    try {
      // Create User.
      _create_alumni_accounts__create_user($node, $value);
      // Change profile title, set email & alumni status.
      $node->set('field_first_name', ltrim(ucwords(strtolower($node->get('field_first_name')->value))));
      $node->set('field_last_name', ucwords(strtolower($node->get('field_last_name')->value)));
      $node->set('field_email', $value[2]);
      $node->set('field_alumni', true);
      $node->save();
      echo "\n Key: $key; Values: " . implode(", ",$value) ."; \n";
    } catch (\Exception $e) {
      echo "\n ERRROR!!! -- Key: $key; Values: " . implode(", ",$value) ."; \n";
      echo "\n Code: $e->getMessage() \n";
    }
  }
}

/**
 * Get node associated w/ CSV value
 *
 * @param array $value
 *   CSV row
 */
function _create_alumni_accounts__get_node($value) {
  $nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties([
      'field_first_name' => ' ' . ucwords($value[1]),
      'field_last_name' => ucwords($value[0]),
    ]);
  if (empty($nodes)) {
    $nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties([
      'field_first_name' => ucwords($value[1]),
      'field_last_name' => ucwords($value[0]),
    ]);
  }
  if (empty($nodes)) {
    return false;
  }
  else {
    return reset($nodes);
  }
}

/**
 * Create user and associate it with the provided profile node
 *
 * @param EntityInterface $node
 * @param array $value
 *   CSV row
 */
function _create_alumni_accounts__create_user(EntityInterface $node, $value) {
  //Create a User
  $user = \Drupal::entityTypeManager()
    ->getStorage('user')
    ->create();
  //Mandatory settings
  $user->setPassword(user_password());
  $user->enforceIsNew();
  $user->setEmail($value[2]);
  $user->set('field_profile', $node->id());
  $user->setUsername(ucwords(strtolower(ltrim($node->get('field_first_name')->value) . ' ' . $node->get('field_last_name')->value)));//This username must be unique and accept only a-Z,0-9, - _ @ .
  $user->activate();
  $user->addRole('alumni');
  //Set Language
  $language_interface = \Drupal::languageManager()->getCurrentLanguage();
  $user->set('langcode', $language_interface->getId());
  $user->set('preferred_langcode', $language_interface->getId());
  $user->set('preferred_admin_langcode', $language_interface->getId());
  // Save.
  $user->save();
}
