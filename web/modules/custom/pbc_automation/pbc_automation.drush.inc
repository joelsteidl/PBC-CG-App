<?php

/**
 * @file Contains custom drush commands.
 */

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Implements hook_drush_command().
 */
function pbc_automation_drush_command() {
  $items = [];
  $items['pco-refresh-data'] = [
    'description' => 'Grabs fresh data from planning center and updates Drupal nodes.',
    'drupal dependencies' => ['pbc_automation'],
  ];
  return $items;
}

/**
* Call back function drush_custom_drush_command_say_hello()
*/
function drush_pbc_automation_pco_refresh_data() {

  $now = new DrupalDateTime('now');
  $nowDate = $now->format('Y-m-d');
  $task = Drupal::service('pbc_automation.pco_tasks');
  // Sync data from Planning Center Online.
  Drupal::state()->set('pbc_automation_next', 1);
  Drupal::state()->set('pbc_automation_offset', 0);

  do {
    $offset = Drupal::state()->get('pbc_automation_offset');

    // See https://people.planningcenteronline.com/lists/198379
    $listId = 198379;
    if ($results = $task->getPcoPeopleFromList($offset, 100, $listId)) {
      if (isset($results->meta->next)) {
        Drupal::state()->set('pbc_automation_next', 1);
      }
      else {
        Drupal::state()->set('pbc_automation_next', 0);
      }

      if (isset($results->meta->next->offset)) {
        Drupal::state()->set('pbc_automation_offset', $results->meta->next->offset);
      }

      foreach ($results->data as $result) {
        $task->createOrUpdateNode($result, TRUE);
      }
      // Set a last updated date.
      Drupal::state()->set('pbc_automation_sync', $nowDate);
    }
    else {
      // No results returned.
      Drupal::state()->set('pbc_automation_next', 0);
    }
  } while (Drupal::state()->get('pbc_automation_next'));
}
