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
use Drupal\pbc_groups\GroupsUtilityInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Class ManageAttendanceForm.
 *
 * @package Drupal\pbc_groups\Form
 */
class ManageAttendanceForm extends FormBase {

  /**
   * Drupal\pbc_groups\GroupsUtilityInterface.
   *
   * @var \Drupal\pbc_groups\GroupsUtilityInterface;
   */
  protected $groupsUtility;

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
  public function __construct(EntityTypeManagerInterface $entityTypeManager, RouteMatchInterface $currentRouteMatch, GroupsUtilityInterface $groups_utility) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentRouteMatch = $currentRouteMatch;
    $this->groupsUtility = $groups_utility;
  }

  /**
   * Create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('pbc_groups.utility')
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
    $this->groupAttendance = $this->currentRouteMatch->getParameter('node');

    $options = [];
    $defaults = [];

    // Set a timestamp when the form loads.
    $now = new DrupalDateTime('now');
    $now = $now->format('U');
    $input = &$form_state->getUserInput();
    if (isset($input['now'])) {
      $now = $input['now'];
    }

    $form['now'] = [
      '#type' => 'hidden',
      '#value' => $now,
    ];

    $records = $storage->getQuery()
      ->condition('type', 'individual_attendance_record')
      ->condition('field_group_attendance_record', $this->groupAttendance->id())
      ->condition('status', 1)
      ->condition('created', $now, '<')
      ->sort('field_group_connection_status.entity.weight', 'ASC')
      ->sort('field_group_connection.entity.field_individual.entity.field_last_name', 'ASC')
      ->sort('field_group_connection.entity.field_individual.entity.field_first_name', 'ASC')
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

    $form['notice'] = [
      '#prefix' => '<div class="alert alert-warning">',
      '#markup' => '<strong>Attention!</strong> Please be sure to click "Save" before leaving this page.',
      '#suffix' => '</div>',
      '#weight' => 0,
    ];

    $form['field_group_meeting_status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Did you meet this week?'),
      '#options' => ['yes' => $this->t('Yes'), 'no' => $this->t('No')],
      '#required' => TRUE,
      '#weight' => 1,
    ];

    if (!$this->groupAttendance->field_group_meeting_status->isEmpty() || $this->groupAttendance->field_group_meeting_status->value != 'not_submitted') {
      $form['field_group_meeting_status']['#default_value'] = $this->groupAttendance->field_group_meeting_status->value;
    }

    $form['attendance'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Mark everyone that was present.'),
      '#options' => $options,
      '#default_value' => $defaults,
      '#weight' => 2,
    ];

    $notes = '';
    if (!$this->groupAttendance->field_notes->isEmpty()) {
      $notes = $this->groupAttendance->field_notes->getString();
    }

    $form['field_notes'] = [
      '#prefix' => '<div class="col-sm-8">',
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#default_value' => $notes,
      '#description' => $this->t('Pass along any important information from this week.'),
      '#rows' => 3,
      '#weight' => 3,
      '#suffix' => '</div>',
    ];

    $form['submit'] = [
      '#prefix' => '<div class="submit-attendance">',
      '#type' => 'submit',
      '#weight' => 4,
      '#value' => $this->t('Save'),
      '#suffix' => '</div>',
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
    $records = $form_state->getValue('attendance');

    // Update individual attendance records.
    foreach ($records as $nid => $record) {
      $values = [];
      $attendance = 0;
      if ($record != 0) {
        $attendance = 1;
      }
      $values['field_in_attendance'] = $attendance;
      $this->groupsUtility->updateNode($values, $nid);
    }

    // Update group attendance values.
    $groupAttendValues = [
      'field_notes' => $form_state->getValue('field_notes'),
      'field_group_meeting_status' => $form_state->getValue('field_group_meeting_status'),
    ];
    $this->groupsUtility->updateNode($groupAttendValues, $this->groupAttendance->id());

    drupal_set_message(t('Your attendance has been updated. You can review below.'), 'status', FALSE);

    $form_state->setRedirect(
      'pbc_groups.group_attendance_finished_controller_content',
      [
        'group' => $this->groupAttendance->field_group->target_id,
        'attendance' => $this->groupAttendance->id(),
      ]
    );
  }

}
