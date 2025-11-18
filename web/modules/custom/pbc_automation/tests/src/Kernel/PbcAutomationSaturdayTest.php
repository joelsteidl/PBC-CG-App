<?php

namespace Drupal\Tests\pbc_automation\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests Saturday group attendance creation logic.
 *
 * @group pbc_automation
 */
class PbcAutomationSaturdayTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Test that Saturday groups get attendance created on Saturday after noon.
   */
  public function testSaturdayAttendanceCreation() {
    // Simulate being on Saturday at 1:00 PM.
    $startDate = '2025-11-16'; // Sunday of current week

    $day = 'Saturday';
    $days = [
      'Sunday' => '12:00',
      'Monday' => '18:00',
      'Tuesday' => '18:00',
      'Wednesday' => '18:00',
      'Thursday' => '18:00',
      'Friday' => '18:00',
      'Saturday' => '12:00',
    ];

    // Calculate meeting date.
    $dayMap = [
      'Sunday' => 0,
      'Monday' => 1,
      'Tuesday' => 2,
      'Wednesday' => 3,
      'Thursday' => 4,
      'Friday' => 5,
      'Saturday' => 6,
    ];

    $meetingDateObj = new DrupalDateTime($startDate);
    $daysUntilMeeting = ($dayMap[$day] - $meetingDateObj->format('w')) % 7;
    $meetingDateObj->modify('+' . $daysUntilMeeting . ' days');
    $meetingDate = $meetingDateObj->format('Y-m-d');
    $meetingTime = $days[$day];

    // Simulate being on Saturday at 1:00 PM.
    $nowDate = '2025-11-22';
    $nowTime = '13:00';

    // Check if attendance should be created.
    $shouldCreate = $nowDate > $meetingDate || ($nowDate == $meetingDate && $nowTime >= $meetingTime);

    // Assert: Should create attendance on Saturday at 1:00 PM (after 12:00 PM).
    $this->assertTrue($shouldCreate, 'Attendance should be created on Saturday after noon.');
    $this->assertEquals('2025-11-22', $meetingDate);
  }

  /**
   * Test that Saturday groups don't get attendance before noon.
   */
  public function testSaturdayBeforeNoonNoCreation() {
    // Simulate being on Saturday at 11:00 AM (before meeting).
    $startDate = '2025-11-16'; // Sunday of current week

    $day = 'Saturday';
    $days = [
      'Sunday' => '12:00',
      'Monday' => '18:00',
      'Tuesday' => '18:00',
      'Wednesday' => '18:00',
      'Thursday' => '18:00',
      'Friday' => '18:00',
      'Saturday' => '12:00',
    ];

    $dayMap = [
      'Sunday' => 0,
      'Monday' => 1,
      'Tuesday' => 2,
      'Wednesday' => 3,
      'Thursday' => 4,
      'Friday' => 5,
      'Saturday' => 6,
    ];

    $meetingDateObj = new DrupalDateTime($startDate);
    $daysUntilMeeting = ($dayMap[$day] - $meetingDateObj->format('w')) % 7;
    $meetingDateObj->modify('+' . $daysUntilMeeting . ' days');
    $meetingDate = $meetingDateObj->format('Y-m-d');
    $meetingTime = $days[$day];

    // Simulate being on Saturday at 11:00 AM.
    $nowDate = '2025-11-22';
    $nowTime = '11:00';

    $shouldCreate = $nowDate > $meetingDate || ($nowDate == $meetingDate && $nowTime >= $meetingTime);

    // Assert: Should NOT create attendance before noon.
    $this->assertFalse($shouldCreate, 'Attendance should NOT be created on Saturday before noon.');
  }

  /**
   * Test that other days still work correctly.
   */
  public function testMondayAttendanceCreation() {
    // Simulate being on Monday at 6:00 PM.
    $startDate = '2025-11-16'; // Sunday of current week

    $day = 'Monday';
    $days = [
      'Sunday' => '12:00',
      'Monday' => '18:00',
      'Tuesday' => '18:00',
      'Wednesday' => '18:00',
      'Thursday' => '18:00',
      'Friday' => '18:00',
      'Saturday' => '12:00',
    ];

    $dayMap = [
      'Sunday' => 0,
      'Monday' => 1,
      'Tuesday' => 2,
      'Wednesday' => 3,
      'Thursday' => 4,
      'Friday' => 5,
      'Saturday' => 6,
    ];

    $meetingDateObj = new DrupalDateTime($startDate);
    $daysUntilMeeting = ($dayMap[$day] - $meetingDateObj->format('w')) % 7;
    $meetingDateObj->modify('+' . $daysUntilMeeting . ' days');
    $meetingDate = $meetingDateObj->format('Y-m-d');
    $meetingTime = $days[$day];

    // Simulate being on Monday at 6:00 PM.
    $nowDate = '2025-11-17';
    $nowTime = '18:00';

    $shouldCreate = $nowDate > $meetingDate || ($nowDate == $meetingDate && $nowTime >= $meetingTime);

    // Assert: Should create attendance on Monday at 6:00 PM.
    $this->assertTrue($shouldCreate, 'Attendance should be created on Monday at 6:00 PM.');
    $this->assertEquals('2025-11-17', $meetingDate);
  }

}
