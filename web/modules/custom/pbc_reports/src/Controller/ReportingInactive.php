<?php

namespace Drupal\pbc_reports\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBuilder;

/**
 * Class ReportingInactive.
 */
class ReportingInactive extends ControllerBase {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;
  /**
   * Drupal\Core\Form\FormBuilder definition.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * Constructs a new ReportingInactive object.
   */
  public function __construct(EntityTypeManager $entity_type_manager, FormBuilder $form_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('form_builder')
    );
  }

  /**
   * Build.
   *
   * @return string
   *   Return Hello string.
   */
  public function build() {
    $build = [];
    $build['intro'] = [
      '#type' => 'markup',
      '#prefix' => '<p>',
      '#markup' => $this->t('This report shows individuals that were once active in a group and currently reported as NOT being active in one. An individuals "Membership Status" can be updated in Planning Center Online and be reflected here.'),
      '#suffix' => '</p>',
    ];
    foreach ($this->getInactiveList() as $nid => $individual) {
      $history = views_embed_view('group_connection_history', 'block_1', $nid);
      // TODO: lazy...should be in a template.
      $prefix = '<h3>' . $individual['name'] . '</h3>';
      if (!empty($individual['relationship'])) {
        $prefix .= '<em>' . $individual['relationship'] . '</em>';
      }
      $build[$nid] = [
        '#type' => 'markup',
        '#prefix' => $prefix,
        '#markup' => render($history),
      ];
    }

    return $build;
  }

  /**
   * Grabs inactive folks... anyone without an active connection.
   *
   * @return array
   *   Array of Individuals.
   */
  public function getInactiveList() {
    $storage = $this->entityTypeManager->getStorage('node');

    $results = $storage->getQuery()
      ->condition('type', 'group_connection')
      ->condition('field_group_connection_status.entity.name', 'Inactive Member')
      ->sort('changed', 'DESC')
      ->execute();

    $inactives = $storage->loadMultiple($results);
    $inactivesList = [];
    foreach ($inactives as $inactive) {
      $individual = $inactive->field_individual->target_id;
      if (!$this->hasActiveConnection($individual)) {
        $inactivesList[$individual] = [
          'name' => $inactive->field_individual->entity->getTitle(),
        ];
        if (!$inactive->field_individual->entity->field_membership->isEmpty()) {
          $inactivesList[$individual]['relationship'] = $inactive->field_individual->entity->field_membership->entity->getName();
        }
      }
    }

    return $inactivesList;
  }

  /**
   * Checks if an individual has an active group connection.
   *
   * @param int $nid
   *   Node ID of an individual node.
   *
   * @return bool
   *   True or False.
   */
  public function hasActiveConnection($nid) {
    $storage = $this->entityTypeManager->getStorage('node');

    $results = $storage->getQuery()
      ->condition('type', 'group_connection')
      ->condition('field_individual', $nid)
      ->condition('field_group.entity.field_group_status', 'Active')
      ->condition('field_group_connection_status.entity.name', 'Active Member')
      ->execute();

    if (!empty($results)) {
      return TRUE;
    }

    return FALSE;
  }

}
