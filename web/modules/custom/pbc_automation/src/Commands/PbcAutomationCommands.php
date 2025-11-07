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

  /**
   * Finds missing group attendance records.
   *
   * Checks all active groups and identifies missing attendance records for
   * dates that match their meeting day (e.g., Sunday, Monday, etc.).
   *
   * @param string $start_date
   *   The start date in Y-m-d format (e.g., 2025-01-01).
   * @param array $options
   *   Optional options for the command.
   */
  #[CLI\Command(name: 'pbc-automation:group-attendance-missing', aliases: ['group-attendance-missing'])]
  #[CLI\Argument(name: 'start_date', description: 'The start date in Y-m-d format (e.g., 2025-01-01).')]
  #[CLI\Option(name: 'create', description: 'Automatically create missing attendance records.')]
  #[CLI\Help('Find missing group attendance records between start and end dates. Use --create flag to auto-create them.')]
  public function groupAttendanceMissing(string $start_date, array $options = []): void {
    // Validate start date format.
    $startDateTime = \DateTime::createFromFormat('Y-m-d', $start_date);
    if (!$startDateTime || $startDateTime->format('Y-m-d') !== $start_date) {
      $this->logger()->error('Invalid start date format. Please use Y-m-d (e.g., 2025-01-01).');
      throw new \InvalidArgumentException('Invalid start date format.');
    }

    // Use today's date as end_date by default.
    $end_date = (new \DateTime('now'))->format('Y-m-d');
    $endDateTime = \DateTime::createFromFormat('Y-m-d', $end_date);

    // Ensure end date is not before start date.
    if ($endDateTime < $startDateTime) {
      $this->logger()->error('End date must be equal to or after the start date.');
      throw new \InvalidArgumentException('End date before start date.');
    }

    $storage = $this->entityTypeManager->getStorage('node');

    // Get all active groups.
    $groups = $storage->getQuery()
      ->condition('type', 'group')
      ->condition('status', 1)
      ->condition('field_group_status', 'Active')
      ->accessCheck(FALSE)
      ->execute();

    if (empty($groups)) {
      $this->logger()->notice('No active groups found.');
      return;
    }

    $groupNodes = $storage->loadMultiple($groups);
    $missing = [];
    $shouldCreate = isset($options['create']);

    // Iterate through each date in the range.
    $currentDate = clone $startDateTime;
    while ($currentDate <= $endDateTime) {
      $dateStr = $currentDate->format('Y-m-d');
      $dayName = $currentDate->format('l'); // Full day name: Sunday, Monday, etc.

      // Check each group for matching meeting day.
      foreach ($groupNodes as $group) {
        if ($group->field_meeting_day->isEmpty()) {
          continue;
        }

        // Skip this date if the group didn't exist yet.
        $groupCreatedTimestamp = $group->created->value ?? 0;
        $currentDateTimestamp = $currentDate->getTimestamp();
        if ($currentDateTimestamp < $groupCreatedTimestamp) {
          continue;
        }

        $meetingDay = $group->field_meeting_day->value;

        // Check if this date matches the group's meeting day.
        if ($meetingDay === $dayName) {
          // Check if attendance record exists for this date.
          $existingRecord = $storage->getQuery()
            ->condition('type', 'group_attendance_record')
            ->condition('field_group', $group->id())
            ->condition('field_meeting_date', $dateStr)
            ->condition('status', 1)
            ->accessCheck(FALSE)
            ->execute();

          if (empty($existingRecord)) {
            $missing[] = [
              'group_id' => $group->id(),
              'group_name' => $group->label(),
              'meeting_day' => $meetingDay,
              'date' => $dateStr,
            ];

            // Create the missing record if option is set.
            if ($shouldCreate) {
              try {
                $groupAttendValues = [
                  'type' => 'group_attendance_record',
                  'field_group' => $group->id(),
                  'field_meeting_date' => $dateStr,
                  'created' => $currentDate->getTimestamp(),
                ];

                $this->utility->createNode($groupAttendValues);
                $this->logger()->notice("Created attendance record for group '{$group->label()}' on {$dateStr}.");
              }
              catch (\Exception $e) {
                $this->logger()->error("Failed to create attendance record for group '{$group->label()}' on {$dateStr}: {$e->getMessage()}");
              }
            }
          }
        }
      }

      $currentDate->modify('+1 day');
    }

    // Output results in table format.
    if (empty($missing)) {
      $this->logger()->notice('No missing attendance records found.');
      return;
    }

    $this->logger()->notice(sprintf('Found %d missing attendance records from %s to %s.', count($missing), $start_date, $end_date));

    // Display results as a table.
    $rows = [];
    foreach ($missing as $record) {
      $rows[] = [
        $record['group_id'],
        $record['group_name'],
        $record['meeting_day'],
        $record['date'],
      ];
    }

    $this->io()->table(
      ['Group ID', 'Group Name', 'Meeting Day', 'Date'],
      $rows
    );
  }

}
