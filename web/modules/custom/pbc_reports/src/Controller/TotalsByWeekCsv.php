<?php

namespace Drupal\pbc_reports\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\pbc_reports\ReportsUtility;
use Drupal\pbc_groups\GroupsUtility;
use Symfony\Component\HttpFoundation\Request;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class TotalsByWeekCsv.
 *
 * @package Drupal\pbc_reports\Controller
 */
class TotalsByWeekCsv extends ControllerBase {

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
   * {@inheritdoc}
   */
  public function __construct(
    EntityTypeManager $entity_type_manager,
    GroupsUtility $pbc_groups_utility,
    ReportsUtility $reports_utility
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->pbcGroupsUtility = $pbc_groups_utility;
    $this->reportsUtility = $reports_utility;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('pbc_groups.utility'),
      $container->get('pbc_reports.utility')
    );
  }

  /**
   * Build.
   *
   * @return array
   *   Return a render array.
   */
  public function index() {
    $data = $this->getData();

    $csv = Writer::fromString();
    $csv->insertOne($data['header']);
    $csv->insertAll($data['rows']);

    $response = new Response();
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="totals-report.csv"');
    $response->setContent($csv->toString());

    return $response;
  }

  /**
   * Get dates for various things we need dates for.
   *
   * @return array
   *   Array of dates.
   */
  public function getDates() {
    $endDate = \Drupal::service('date.formatter')->format(time(), 'html_date');
    $startDate = \Drupal::service('date.formatter')->format(strtotime('-12 weeks'), 'html_date');
    $reversedDates = array_reverse($this->reportsUtility->getDatesbyWeek($startDate, $endDate));
    return $reversedDates;
  }

  /**
   * Get data.
   *
   * @return array
   *   Return array of date needed.
   */
  public function getData() {
    $data = [];
    $header = [''];
    $items = [
      'Total',
      'Adults',
      'Kids',
    ];
    $dates = $this->getDates();
    foreach ($dates as $date) {
      foreach ($items as $item) {
        $header[] = $date['start_display'] . ' - ' . $item;
      }
    }

    $rows = [];

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
        $rows[$group->id()][] = $dateCounts['total'];
        $rows[$group->id()][] = $dateCounts['adults'];
        $rows[$group->id()][] = $dateCounts['kids'];
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

      if (!isset($rows[999999999])) {
        $rows[999999999] = ['Totals'];
      }

      $rows[999999999][] = $weekSums['total'];
      $rows[999999999][] = $weekSums['adults'];
      $rows[999999999][] = $weekSums['kids'];
    }

    $data = [
      'header' => $header,
      'rows' => $rows,
    ];

    return $data;
  }

}
