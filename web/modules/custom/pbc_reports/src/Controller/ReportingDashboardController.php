<?php

namespace Drupal\pbc_reports\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\pbc_reports\ReportsUtility;
use Drupal\node\NodeInterface;

/**
 * Class ReportingDashboardController.
 *
 * @package Drupal\pbc_reports\Controller
 */
class ReportingDashboardController extends ControllerBase {

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
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManager $entity_type_manager, ReportsUtility $reports_utility) {
    $this->entityTypeManager = $entity_type_manager;
    $this->reportsUtility = $reports_utility;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('pbc_reports.utility')
    );
  }

  /**
   * Create.
   *
   * @return string
   *   Return Hello string.
   */
  public function index() {
    $build = [];

    $header = [
      $this->t('Title'),
      $this->t('Value'),
    ];
    $rows = [];
    // $rows = [
    //   ['one', 'two'],
    //   ['three', 'four'],
    // ];
    $rows['times_met']['label'] = $this->t('# Times Met');
    $rows['times_met']['value'] = $this->reportsUtility->getGroupAttendance('', 'yes');
    $build['data_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
    return $build;
  }

}
