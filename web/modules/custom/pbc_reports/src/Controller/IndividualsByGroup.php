<?php

namespace Drupal\pbc_reports\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\pbc_reports\ReportsUtility;
use Drupal\Core\Form\FormBuilder;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Render\Markup;

/**
 * Class IndividualsByGroup.
 *
 * @package Drupal\pbc_reports\Controller
 */
class IndividualsByGroup extends ControllerBase {

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
   * Build.
   *
   * @return array
   *   Return a render array.
   */
  public function build(Request $request) {
    $build = [];
    $gids = $request->request->get('groups');

    if (empty($gids)) {
      $build['report_config']['form'] = $this->formBuilder->getForm('Drupal\pbc_reports\Form\ReportConfigForm', ['exclude' => 'dates']);
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

    // Loop over to create additional.
    $storage = $this->entityTypeManager->getStorage('node');
    $groups = $storage->loadMultiple($gids);
    foreach ($groups as $group) {
      $build['heading-' . $group->id()] = [
        '#prefix' => '<h3>',
        '#markup' => $group->getTitle(),
        '#suffix' => '</h3>',
      ];
      $build['table-' . $group->id()] = $this->buildTable($group->id());
    }

    return $build;
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
  public function buildTable($nid) {
    $connections = $this->getGroupConnections($nid);
    $groupAttendance = $this->getGroupAttendance($nid);

    $header = [''];
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
    }

    foreach ($groupAttendance as $gAttendance) {
      $reason = $gAttendance->field_group_meeting_status->getString();
      $status = $this->formatReason($reason, 'short');

      foreach ($connections as $connection) {
        $cell = $this->buildTableCell($connection->id(), $gAttendance->id(), $status, $reason);
        $rows[$connection->id()][$gAttendance->id()] = $cell;
      }

      $status = $this->formatReason($reason, 'long');
      $header[] = $this->buildTableHeader($gAttendance, $status);
    }
    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#caption' => $this->t('<span class="badge">@count</span> Active Participants', ['@count' => count($connections)]),
    ];

    return $table;
  }

  /**
   * Build table cell.
   *
   * @param int $gcid
   *   A Group Connection node id.
   * @param int $gaid
   *   Group attendance record node id.
   * @param string $status
   *   Status of meeting or not for the group.
   *
   * @return string
   *   Contents of a table cell.
   */
  public function buildTableCell($gcid, $gaid, $status, $reason) {
    $stats = $this->getIndAttStats($gaid);
    // Handle if the person has no individual
    // attendance record.
    if (!in_array($gcid, array_keys($stats))) {
      // Not part of group.
      $cell = Markup::create('<span class="label label-default" data-toggle="tooltip" data-placement="top" title="Not Part of Group Yet">N/A</span>');
      return $cell;
    }

    $cell = $status;
    if ($reason === 'yes') {
      $val = $stats[$gcid];
      $cell = Markup::create('<span class="label label-danger" data-toggle="tooltip" data-placement="top" title="Absent">&#10006;</span>');
      if ($val) {
        $cell = Markup::create('<span class="label label-success" data-toggle="tooltip" data-placement="top" title="Present">&#10004;</span>');
      }
    }

    return $cell;
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
      ->condition('field_group_connection_status.entity.name', 'Active')
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
   * Gets an individuals attendance records.
   *
   * @param int $nid
   *   A Group Attendance Record Nid.
   *
   * @return array
   *   Array of fully loaded nodes.
   */
  public function getIndAtt($nid) {
    $storage = $this->entityTypeManager->getStorage('node');
    $results = $storage->getQuery()
      ->condition('type', 'individual_attendance_record')
      ->condition('field_group_attendance_record', $nid)
      ->condition('status', 1)
      ->execute();

    return $storage->loadMultiple($results);
  }

  /**
   * Gets an individual attendance stats.
   *
   * @param int $nid
   *   A Group Attendance Record Nid.
   *
   * @return array
   *   Status of attendance.
   */
  public function getIndAttStats($nid) {
    $records = $this->getIndAtt($nid);
    $stats = [];
    foreach ($records as $record) {
      // Key is someone's group connection and the value
      // Is if they attended or not.
      $gcid = $record->field_group_connection->target_id;
      $stats[$gcid] = $record->field_in_attendance->value;
    }

    return $stats;
  }

}
