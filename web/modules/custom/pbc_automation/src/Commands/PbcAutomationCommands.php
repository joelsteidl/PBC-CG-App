<?php

namespace Drupal\pbc_automation\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\pbc_groups\GroupsUtilityInterface;

/**
 * Drush commands for pbc_automation.
 */
class PbcAutomationCommands extends DrushCommands {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The PBC Groups utility service.
   *
   * @var \Drupal\pbc_groups\GroupsUtilityInterface
   */
  protected GroupsUtilityInterface $utility;

  /**
   * Constructs the command class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\pbc_groups\GroupsUtilityInterface $utility
   *   The PBC Groups utility service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    GroupsUtilityInterface $utility
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->utility = $utility;
  }

  /**
   * Refreshes data from Planning Center Online.
   */
  #[CLI\Command(name: 'pbc-automation:pco-refresh', aliases: ['pco-refresh-data'])]
  #[CLI\Help('Grabs fresh data from planning center and updates Drupal nodes.')]
  public function pcoRefreshData(): void {
    $now = new DrupalDateTime('now');
    $nowDate = $now->format('Y-m-d');
    $task = \Drupal::service('pbc_automation.pco_tasks');

    // Sync data from Planning Center Online.
    \Drupal::state()->set('pbc_automation_next', 1);
    \Drupal::state()->set('pbc_automation_offset', 0);

    do {
      $offset = \Drupal::state()->get('pbc_automation_offset');

      // See https://people.planningcenteronline.com/lists/198379
      $listId = 198379;
      if ($results = $task->getPcoPeopleFromList($offset, 100, $listId)) {
        if (isset($results->meta->next)) {
          \Drupal::state()->set('pbc_automation_next', 1);
        }
        else {
          \Drupal::state()->set('pbc_automation_next', 0);
        }

        if (isset($results->meta->next->offset)) {
          \Drupal::state()->set('pbc_automation_offset', $results->meta->next->offset);
        }

        foreach ($results->data as $result) {
          $task->createOrUpdateNode($result, TRUE);
        }
        // Set a last updated date.
        \Drupal::state()->set('pbc_automation_sync', $nowDate);
      }
      else {
        // No results returned.
        \Drupal::state()->set('pbc_automation_next', 0);
      }
    } while (\Drupal::state()->get('pbc_automation_next'));

    $this->logger()->notice('PCO data refresh completed.');
  }

  /**
   * Creates a group attendance record for a specific date.
   *
   * @param int $group_id
   *   The group node ID.
   * @param string $date
   *   The meeting date in Y-m-d format (e.g., 2025-11-06).
   */
  #[CLI\Command(name: 'pbc-automation:group-attendance-create', aliases: ['group-attendance-create'])]
  #[CLI\Argument(name: 'group_id', description: 'The group node ID.')]
  #[CLI\Argument(name: 'date', description: 'The meeting date in Y-m-d format (e.g., 2025-11-06).')]
  #[CLI\Help('Create a group attendance record for a specific date.')]
  public function groupAttendanceCreate(int $group_id, string $date): void {
    // Validate the date format.
    $dateTime = \DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateTime || $dateTime->format('Y-m-d') !== $date) {
      $this->logger()->error('Invalid date format. Please use Y-m-d (e.g., 2025-11-06).');
      throw new \InvalidArgumentException('Invalid date format.');
    }

    // Load the group node.
    $storage = $this->entityTypeManager->getStorage('node');
    $group = $storage->load($group_id);

    if (!$group || $group->bundle() !== 'group') {
      $this->logger()->error("Group with ID $group_id not found or is not a group node.");
      throw new \InvalidArgumentException("Invalid group ID: $group_id");
    }

    // Create the attendance record.
    $dateTime = \DateTime::createFromFormat('Y-m-d', $date);
    $timestamp = $dateTime->getTimestamp();

    $groupAttendValues = [
      'type' => 'group_attendance_record',
      'field_group' => $group->id(),
      'field_meeting_date' => $date,
      'created' => $timestamp,
    ];

    try {
      if ($attendance = $this->utility->createNode($groupAttendValues)) {
        $this->logger()->notice("Attendance record created successfully for group '{$group->label()}' on {$date}.");
      }
      else {
        $this->logger()->error("Failed to create attendance record for group '{$group->label()}' on {$date}.");
        throw new \RuntimeException('Failed to create attendance record.');
      }
    }
    catch (\Exception $e) {
      $this->logger()->error("Error creating attendance record: {$e->getMessage()}");
      throw $e;
    }
  }

}
