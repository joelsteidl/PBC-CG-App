<?php

namespace Drupal\pbc_reports\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\pbc_reports\ReportsUtility;
use Drupal\pbc_groups\GroupsUtility;
use Drupal\Core\Form\FormBuilder;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Render\Markup;

/**
 * Class TotalsByWeek.
 *
 * @package Drupal\pbc_reports\Controller
 */
class TotalsByWeek extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Drupal\pbc_groups\GroupsUtility definition.
   *
   * @var \Drupal\pbc_groups\GroupsUtility
   */
  protected $pbcGroupsUtility;

  /**
   * Drupal\pbc_reports\ReportsUtility definition.
   *
   * @var \Drupal\pbc_reports\ReportsUtility
   */
  protected $reportsUtility;
  /**
   * Drupal\Core\Form\FormBuilder definition.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeManager $entity_type_manager,
    GroupsUtility $pbc_groups_utility,
    ReportsUtility $reports_utility,
    FormBuilder $form_builder
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->pbcGroupsUtility = $pbc_groups_utility;
    $this->reportsUtility = $reports_utility;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('pbc_groups.utility'),
      $container->get('pbc_reports.utility'),
      $container->get('form_builder')
    );
  }

  /**
   * Build.
   *
   * @return array
   *   Return a render array.
   */
  public function index(Request $request) {
    $build = [];
    $build['prefix']['#markup'] = '<div class="row"><div class="col-sm-12">';
    $build['table'] = $this->buildTable();
    $build['suffix']['#markup'] = '</div></div>';

    return $build;
  }

  /**
   * Build attendance table.
   *
   * @return array
   *   Return a render array.
   */
  public function buildTable() {
    $header = [''];
    $endDate = \Drupal::service('date.formatter')->format(time(), 'html_date');
    $startDate = \Drupal::service('date.formatter')->format(strtotime('-6 weeks'), 'html_date');
    $dates = $this->reportsUtility->getDatesbyWeek($startDate, $endDate);
    $dates = array_reverse($dates);
    foreach ($dates as $date) {
      $header[] = $date['start_display'];
    }

    $rows = [
      'totals' => [Markup::create('<span class="label label-primary">Total</span> <span class="label label-success">Adults</span> <span class="label label-default">Kids</span>')],
    ];

    $groups = $this->pbcGroupsUtility->getGroupNodes('active', 'object');

    $counts = [];
    foreach ($groups as $group) {
      $rows[$group->id()] = [$group->getTitle()];
      foreach ($dates as $date) {
        $dateCounts = [
          'kids' => 0,
          'adults' => 0,
          'total' => 0,
        ];
        $attendanceRecord = $this->reportsUtility->getAttendanceRecord($group, $date['start_query'], $date['end_query']);
        if ($attendanceRecord) {
          if (!$attendanceRecord->field_children_count->isEmpty()) {
            $dateCounts['kids'] = $attendanceRecord->get('field_children_count')->value;
          }
          $dateCounts['adults'] = $this->reportsUtility->getAttendanceByGroup($attendanceRecord->id(), 1, [1, 3]);
          $dateCounts['total'] = $dateCounts['kids'] + $dateCounts['adults'];
        }
        $rows[$group->id()][] = Markup::create('<span class="label label-primary">' . $dateCounts['total'] . '</span> <span class="label label-success">' . $dateCounts['adults'] . '</span> <span class="label label-default">' . $dateCounts['kids'] . '</span>');
        $counts[$date['start_display']][] = $dateCounts;
      }
    }

    foreach ($counts as $countWeek) {
      $weekSums = [
        'kids' => 0,
        'adults' => 0,
        'total' => 0,
      ];
      // Sum all the groups for each week.
      foreach ($countWeek as $countWeek) {
        $weekSums['kids'] += $countWeek['kids'];
        $weekSums['adults'] += $countWeek['adults'];
        $weekSums['total'] += $countWeek['total'];
      }

      $rows['totals'][] = Markup::create('<span class="label label-primary">' . $weekSums['total'] . '</span> <span class="label label-success">' . $weekSums['adults'] . '</span> <span class="label label-default">' . $weekSums['kids'] . '</span>');
    }

    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];

    return $table;
  }

  /**
   * Build table header.
   *
   * @param object $node
   *   A Group Attendance Record Node.
   * @param string $status
   *   The status of meeting or not.
   *
   * @return array
   *   Return a render array
   */
  public function buildTableHeader($node, $status) {
    $date = $node->field_meeting_date->date->format('M. d');
    return [
      'data' => [
        '#markup' => '<div>' . $date . '</div><small>' . $status . '</small>',
      ],
    ];
  }

  /**
   * Formats the reason a group met or didn't.
   *
   * @param string $reason
   *   Reason a group met or didn't.
   *
   * @return string
   *   Text about if a group met or not.
   */
  public function formatReason($reason, $length) {
    $status = '';
    switch ($reason) {
      case 'no':
        $label = $this->t('No Meeting');
        if ($length === 'short') {
          $label = $this->t('N/A');
        }
        $status = Markup::create('<span class="label label-warning" data-toggle="tooltip" data-placement="top" title="Group Did Not Meet">' . $label . '</span>');
        break;

      case 'not_a_group':
        $label = $this->t('Not Yet a Group');
        $status = Markup::create('<span class="label label-default" data-toggle="tooltip" data-placement="top" title="Date before group was formed.">' . $label . '</span>');
        break;

      case 'not_submitted':
        $label = $this->t('Not Submitted');
        if ($length === 'short') {
          $label = $this->t('N/A');
        }
        $status = Markup::create('<span class="label label-default" data-toggle="tooltip" data-placement="top" title="Group Attendance Not Submitted">' . $label . '</span>');
        break;

      case 'yes':
        $label = $this->t('Group Met');
        if ($length === 'short') {
          $label = '&#10004';
        }
        $status = Markup::create('<span class="label label-success" data-toggle="tooltip" data-placement="top" title="Group Met this Week">' . $label . '</span>');
        break;
    }

    return $status;
  }

  /**
   * Gets a groups attendance records.
   *
   * @param int $nid
   *   A Group NID.
   * @param int $count
   *   Number of results you want to get.
   *
   * @return array
   *   Array of fully loaded nodes.
   */
  public function getGroupAttendance($nid, $count = 12) {
    $storage = $this->entityTypeManager->getStorage('node');
    $results = $storage->getQuery()
      ->condition('type', 'group_attendance_record')
      ->condition('field_group', $nid)
      ->condition('status', 1)
      ->range(0, $count)
      ->sort('created', 'DESC')
      ->execute();

    return $storage->loadMultiple($results);
  }

  /**
   * Gets an individuals attendance record count.
   *
   * @param int $nid
   *   A Group Attendance Record Nid.
   *
   * @return array
   *   Array of fully loaded nodes.
   */
  public function getIndAttCount($nid) {
    $storage = $this->entityTypeManager->getStorage('node');
    $results = $storage->getQuery()
      ->condition('type', 'individual_attendance_record')
      ->condition('field_group_attendance_record', $nid)
      ->condition('status', 1)
      ->execute();

    return $storage->loadMultiple($results);
  }

}
