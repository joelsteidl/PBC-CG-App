<?php

namespace Drupal\pbc_reports\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\pbc_reports\ReportsUtility;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Link;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class IndividualsByGroupYear.
 *
 * @package Drupal\pbc_reports\Controller
 */
class IndividualsByGroupYear extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;
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
  public function __construct(EntityTypeManager $entity_type_manager, ReportsUtility $reports_utility, FormBuilder $form_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->reportsUtility = $reports_utility;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('pbc_reports.utility'),
      $container->get('form_builder')
    );
  }

  /**
   * Export as CSV
   */
  public function export(Request $request) {
    $query = $request->query;
    // Get group ids from the querystring.
    $gids = explode('+', $query->get('gids'));

    // Iterate over groups.
    $storage = $this->entityTypeManager->getStorage('node');
    $groups = $storage->loadMultiple($gids);
    $rows = [];
    // Build rows from each group.
    foreach ($groups as $group) {
       $groupRows = $this->buildRow($group->id(), $query);
       $rows = array_merge($rows, $groupRows);
    }

    // Adjust the header for CSV.
    $header = array_merge(['group' => 'Group', 'name' => 'Name'], $this->buildTableHeader());
    if (isset($header[''])) {
      unset($header['']);
    }

    // Write to CSV.
    $csv = Writer::createFromString();
    $csv->insertOne($header);
    $csv->insertAll($rows);

    $response = new Response();
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="totals-report.csv"');
    $response->setContent($csv->toString());

    return $response;
  }

  /**
   * Build.
   *
   * @return array
   *   Return a render array.
   */
  public function build(Request $request) {
    $build = [];
    $gids = $request->request->get('groups');

    if (empty($gids)) {
      $build['report_config']['form'] = $this->formBuilder->getForm('Drupal\pbc_reports\Form\ReportConfigForm');
      $build['#attached'] = [
        'library' => [
          'pbc_reports/base-styles',
        ],
      ];
      return $build;
    }

    $build['#attached'] = [
      'library' => [
        'pbc_reports/reports-base',
      ],
    ];

    // Add link to export CSV.
    $options = [
      'query' => [
        'start' => $request->request->get('start_date'),
        'end' => $request->request->get('end_date'),
        'gids' => implode('+', $gids),
      ]
    ];
    $url = Url::fromRoute('pbc_reports.individuals_by_group_year.csv', [], $options);
    $csv = Link::fromTextAndUrl($this->t('Export CSV Data'), $url);
    $build['export'] = $csv->toRenderable();

    // Loop over to create additional.
    $storage = $this->entityTypeManager->getStorage('node');
    $groups = $storage->loadMultiple($gids);
    foreach ($groups as $group) {
      $build['heading-' . $group->id()] = [
        '#prefix' => '<h3>',
        '#markup' => $group->getTitle(),
        '#suffix' => '</h3>',
      ];
      $build['table-' . $group->id()] = $this->buildTable($group->id(), $request);
    }

    return $build;
  }

  /**
   * Build attendance table.
   *
   * @param int $nid
   *   A Group NID.
   * @param array $query
   *   Relevant querystring params.
   *
   * @return array
   *   Return a render array.
   */
  public function buildRow($nid, $query) {
    $dates = [
      'start' => $query->get('start'),
      'end' => $query->get('end'),
    ];
    $connections = $this->getGroupConnections($nid);
    $groupAttendance = $this->getGroupAttendance($nid, $dates);
    $groupPotentialAttendance = $this->getGroupPotentialAttendance($nid, $dates);

    $rows = [];
    // Create a row starting with name for each person.
    foreach ($connections as $connection) {
      $name = $connection->field_individual->entity->getTitle();
      $rows[$connection->id()]['group'] = $connection->field_group->entity->getTitle();
      $rows[$connection->id()]['name'] = $name;
      $rows[$connection->id()]['num_group_potential'] = $groupPotentialAttendance;
      $rows[$connection->id()]['num_group_met'] = count($groupAttendance);
      $indPotentialAttendance = $this->getIndPotentialAtt($connection->id(), $groupAttendance);
      $indAttendance = $this->getIndAtt($connection->id(), $groupAttendance);
      $rows[$connection->id()]['ind_potential'] = $indPotentialAttendance;
      $rows[$connection->id()]['ind_present'] = $indAttendance;
      $percent = 0;
      if (!empty($indAttendance) && !empty($indPotentialAttendance)) {
        $quotient = $indAttendance / $indPotentialAttendance;
        $percent = number_format($quotient * 100, 0);
      }
      $rows[$connection->id()]['percent_present'] = $percent;
    }

    return $rows;
  }

  /**
   * Build attendance table.
   *
   * @param int $nid
   *   A Group NID.
   *
   * @return array
   *   Return a render array.
   */
  public function buildTable($nid, Request $request) {
    $dates = [
      'start' => $request->request->get('start_date'),
      'end' => $request->request->get('end_date'),
    ];
    $connections = $this->getGroupConnections($nid);
    $groupAttendance = $this->getGroupAttendance($nid, $dates);
    $groupPotentialAttendance = $this->getGroupPotentialAttendance($nid, $dates);

    $rows = [];
    // Create a row starting with name for each person.
    foreach ($connections as $connection) {
      $name = $connection->field_individual->entity->getTitle();
      $nameCell = '<strong>' . $name . '</strong>';
      if (!$connection->field_individual->entity->field_membership->isEmpty()) {
        $relationship = $connection->field_individual->entity->field_membership->entity->getName();
        $nameCell .= '<br><em>' . $relationship . '</em>';
      }
      $rows[$connection->id()] = [Markup::create($nameCell)];
      $rows[$connection->id()]['num_group_potential'] = $groupPotentialAttendance;
      $rows[$connection->id()]['num_group_met'] = count($groupAttendance);
      $indPotentialAttendance = $this->getIndPotentialAtt($connection->id(), $groupAttendance);
      $indAttendance = $this->getIndAtt($connection->id(), $groupAttendance);
      $rows[$connection->id()]['ind_potential'] = $indPotentialAttendance;
      $rows[$connection->id()]['ind_present'] = $indAttendance;
      $percent = 0;
      if (!empty($indAttendance) && !empty($indPotentialAttendance)) {
        $quotient = $indAttendance / $indPotentialAttendance;
        $percent = number_format($quotient * 100, 0);
      }
      $rows[$connection->id()]['percent_present'] = $percent;
    }

    $table = [
      '#type' => 'table',
      '#header' => $this->buildTableHeader(),
      '#rows' => $rows,
      '#caption' => $this->t('<span class="badge">@count</span> Active Participants', ['@count' => count($connections)]),
    ];

    return $table;
  }

  /**
   * Build table header.
   *
   * @return array
   *   Return a render array
   */
  public function buildTableHeader() {
    return [
      '' => '',
      'num_group_potential' => $this->t('# Potential Meetings'),
      'num_group_met' => $this->t('# Group Met'),
      'ind_potential' => $this->t('# Ind Potential Meetings'),
      'ind_present' => $this->t('# Ind Present'),
      'percent_present' => $this->t('% Present'),
    ];
  }

  /**
   * Gets a groups connections.
   *
   * @param int $nid
   *   A Group NID.
   *
   * @return array
   *   Array of fully loaded nodes.
   */
  public function getGroupConnections($nid) {
    $storage = $this->entityTypeManager->getStorage('node');
    $results = $storage->getQuery()
      ->condition('type', 'group_connection')
      ->condition('field_group', $nid)
      ->condition('field_group_connection_status.entity.name', 'Active Member')
      ->condition('status', 1)
      ->sort('field_individual.entity.field_last_name', 'ASC')
      ->execute();

    return $storage->loadMultiple($results);
  }

  /**
   * Gets a groups attendance records.
   *
   * @param int $nid
   *   A Group NID.
   * @param int $count
   *   Number of results you want to get.
   *
   * @return int
   *   Number of attenance nodes.
   */
  public function getGroupPotentialAttendance($nid, $dates) {
    $storage = $this->entityTypeManager->getStorage('node');
    $results = $storage->getQuery()
      ->count()
      ->condition('type', 'group_attendance_record')
      ->condition('field_group', $nid)
      ->condition('status', 1)
      ->condition('field_meeting_date', [$dates['start'], $dates['end']], 'BETWEEN')
      ->accessCheck(FALSE)
      ->execute();

    return $results;
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
   *   Array of nids.
   */
  public function getGroupAttendance($nid, $dates) {
    $storage = $this->entityTypeManager->getStorage('node');
    $results = $storage->getQuery()
      ->condition('type', 'group_attendance_record')
      ->condition('field_group', $nid)
      ->condition('status', 1)
      ->condition('field_meeting_date', [$dates['start'], $dates['end']], 'BETWEEN')
      ->condition('field_group_meeting_status', 'yes')
      ->accessCheck(FALSE)
      ->execute();

    return $results;
  }

  /**
   * Gets an individuals attendance records.
   *
   * @param int $cid
   *   A Groups Connection node id.
   * @param array $groupAttendance
   *   An array of group attendance node ids.
   *
   * @return array
   *   Array of fully loaded nodes.
   */
  public function getIndPotentialAtt($cid, $groupAttendance) {
    if (empty($groupAttendance)) {
      return 0;
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $results = $storage->getQuery()
      ->count()
      ->accessCheck(FALSE)
      ->condition('type', 'individual_attendance_record')
      ->condition('field_group_attendance_record', $groupAttendance, 'IN')
      ->condition('field_group_connection', $cid)
      ->condition('status', 1)
      ->execute();

    return $results;
  }

  /**
   * Gets an individuals attendance records.
   *
   * @param int $cid
   *   A Groups Connection node id.
   * @param array $groupAttendance
   *   An array of group attendance node ids.
   *
   * @return array
   *   Array of fully loaded nodes.
   */
  public function getIndAtt($cid, $groupAttendance) {
    if (empty($groupAttendance)) {
      return 0;
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $results = $storage->getQuery()
      ->count()
      ->accessCheck(FALSE)
      ->condition('type', 'individual_attendance_record')
      ->condition('field_group_attendance_record', $groupAttendance, 'IN')
      ->condition('field_group_connection', $cid)
      ->condition('field_in_attendance', 1)
      ->condition('status', 1)
      ->execute();

    return $results;
  }

}
