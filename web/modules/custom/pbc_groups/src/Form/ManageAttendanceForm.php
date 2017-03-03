<?php

/**
 * @file
 * Contains \Drupal\pbc_groups\Form\ManageAttendanceForm.
 */

namespace Drupal\pbc_groups\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ManageAttendanceForm.
 *
 * @package Drupal\pbc_groups\Form
 */
class ManageAttendanceForm extends FormBase {

  /**
   * Entity type manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity type manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $currentRouteMatch;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, RouteMatchInterface $currentRouteMatch) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentRouteMatch = $currentRouteMatch;
  }

  /**
   * Create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pbc_groups_manage_attendance_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $storage = $this->entityTypeManager->getStorage('node');
    $groupAttendance = $this->currentRouteMatch->getParameter('node');

    $options = [];
    $defaults = [];

    $records = $storage->getQuery()
      ->condition('type', 'individual_attendance_record')
      ->condition('field_group_attendance_record', $groupAttendance->id())
      ->condition('status', 1)
      ->execute();

    if (!count($records)) {
      // Add a message.
      return FALSE;
    }

    $records = $storage->loadMultiple($records);

    foreach ($records as $record) {
      $options[$record->id()] = $record->field_group_connection->entity->field_individual->entity->getTitle() . ' (' . $record->field_group_connection->entity->field_group_connection_status->entity->getName() . ')';
      if ($record->field_in_attendance->value == 1) {
        $defaults[] = $record->id();
      }
    }

    $form['attendance'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('In Attendance'),
      '#description' => $this->t('Mark everyone that was present.'),
      '#options' => $options,
      '#default_value' => $defaults,
      '#weight' => 1,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#weight' => 2,
      '#value' => $this->t('Update Attendance'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $storage = $this->entityTypeManager->getStorage('node');
    $records = $form_state->getValue('attendance');

    foreach ($records as $nid => $record) {
      $attendance = 0;
      $node = $storage->load($nid);
      if ($record != 0) {
        $attendance = 1;
      }
      $node->field_in_attendance->setValue($attendance);
      $node->save();
    }
  }

}
