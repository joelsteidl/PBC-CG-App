<?php

namespace Drupal\pbc_reports\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\pbc_reports\ReportsUtility;
use Drupal\node\NodeInterface;

/**
 * Class ReportingTestController.
 *
 * @package Drupal\pbc_reports\Controller
 */
class ReportingTestController extends ControllerBase {

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
    return [
      '#markup' => '<div id="container"></div>',
      '#attached' => [
        'library' => [
          'pbc_reports/high-charts',
          'pbc_reports/attendance-by-week',
        ],
      ],
    ];
  }

}
