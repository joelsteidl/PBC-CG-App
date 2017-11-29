<?php

namespace Drupal\pbc_reports\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBuilder;
use Drupal\pbc_groups\GroupsUtility;
use Drupal\pbc_reports\ReportsUtility;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ReportingWeekController.
 *
 * @package Drupal\pbc_reports\Controller
 */
class ReportingWeekController extends ControllerBase {

  /**
   * Drupal\Core\Form\FormBuilder definition.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

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
   * Drupal\pbc_groups\GroupsUtility definition.
   *
   * @var \Drupal\pbc_groups\GroupsUtility
   */
  protected $groupsUtility;

  /**
   * {@inheritdoc}
   */
  public function __construct(FormBuilder $form_builder, EntityTypeManager $entity_type_manager, GroupsUtility $groups_utility, ReportsUtility $reports_utility) {
    $this->formBuilder = $form_builder;
    $this->entityTypeManager = $entity_type_manager;
    $this->groupsUtility = $groups_utility;
    $this->reportsUtility = $reports_utility;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('entity_type.manager'),
      $container->get('pbc_groups.utility'),
      $container->get('pbc_reports.utility')
    );
  }

  /**
   * Create.
   *
   * @return string
   *   Return Hello string.
   */
  public function index(Request $request) {
    $build = [];

    $groups = $request->request->get('groups');
    $startInput = $request->request->get('start_date');
    $endInput = $request->request->get('end_date');

    if (empty($groups) || empty($startInput) || empty($endInput)) {
      $build['report_config']['form'] = $this->formBuilder->getForm('Drupal\pbc_reports\Form\ReportConfigForm');
      $build['#attached'] = [
        'library' => [
          'pbc_reports/base-styles',
        ],
      ];
      return $build;
    }

    $dates = $this->reportsUtility->getDatesbyWeek($startInput, $endInput);
    $labels = $this->reportsUtility->getCategoryLabels($dates);
    $data = $this->reportsUtility->getSeriesData($groups, $dates);

    $build['charts']['#markup'] = '<div id="container"></div>';
    $build['#attached'] = [
      'drupalSettings' => [
        'highCharts' => [
          'labels' => $labels,
          'seriesData' => $data,
        ],
      ],
      'library' => [
        'pbc_reports/high-charts',
        'pbc_reports/attendance-by-week',
      ],
    ];
    return $build;
  }

}
