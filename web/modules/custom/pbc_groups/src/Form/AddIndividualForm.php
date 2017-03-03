<?php

namespace Drupal\pbc_groups\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\pbc_groups\GroupsUtilityInterface;

/**
 * Class AddIndividualForm.
 *
 * @package Drupal\pbc_groups\Form
 */
class AddIndividualForm extends FormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Drupal\pbc_groups\GroupsUtilityInterface.
   *
   * @var \Drupal\pbc_groups\GroupsUtilityInterface;
   */
  protected $groupsUtility;

  public function __construct(
    EntityTypeManager $entity_type_manager,
    GroupsUtilityInterface $groups_utility
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->groupsUtility = $groups_utility;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('pbc_groups.utility')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pbc_groups_add_individual_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $defaults = NULL) {

    $this->redirect = $defaults['redirect'];

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    $statusOptions = [];

    $terms = $storage->loadByProperties([
      'vid' => 'group_membership_status',
    ]);

    foreach ($terms as $term) {
      $statusOptions[$term->id()] = $term->getName();
    }

    $form['firstname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#default_value' => $defaults['firstname'],
    ];
    $form['lastname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#default_value' => $defaults['lastname'],
    ];
    $form['email_address'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
    ];
    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => $statusOptions,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Add Person'),
    ];

    return $form;
  }

  /**
    * {@inheritdoc}
    */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Display result.
    $storage = $this->entityTypeManager->getStorage('node');
    $redirectNode = $storage->load($this->redirect);

    if ($redirectNode->getType() === 'group_attendance_record') {
      $groupId = $redirectNode->field_group->target_id;
    }
    elseif ($redirectNode->getType() === 'group') {
      $groupId = $redirectNode->id();
    }

    $individualValues = [
      'type' => 'individual',
      'field_first_name' => $form_state->getValue('firstname'),
      'field_last_name' => $form_state->getValue('lastname'),
      'field_email_address' => $form_state->getValue('email_address'),
    ];

    // Create individual node.
    $individual = $this->groupsUtility->createNode($individualValues);

    $groupConnectValues = [
      'type' => 'group_connection',
      'field_group' => $groupId,
      'field_individual' => $individual->id(),
      'field_group_connection_status' => $form_state->getValue('status'),
    ];

    // Create group_connection node.
    $groupConnection = $this->groupsUtility->createNode($groupConnectValues);

    if ($redirectNode->getType() === 'group_attendance_record') {

      $indAttendanceValues = [
        'type' => 'individual_attendance_record',
        'field_group_attendance_record' => $redirectNode->id(),
        'field_in_attendance' => 1,
        'field_group_connection' => $groupConnection->id(),
      ];

      // Create individual_attendance_record node.
      $this->groupsUtility->createNode($indAttendanceValues);
    }

    // Redirect to the correct place.
    $form_state->setRedirect(
      'entity.node.canonical',
      ['node' => $this->redirect]
    );

  }

}
