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

    $form['field_first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#default_value' => $defaults['firstname'],
    ];
    $form['field_last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#default_value' => $defaults['lastname'],
    ];
    $form['field_email_address'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
    ];

    $form['field_group_connection_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => $this->groupsUtility->termsToOptions('group_membership_status'),
      '#default_value' => $defaults['status'],
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

    $individualValues = ['type' => 'individual'];
    $fields = [
      'field_first_name',
      'field_last_name',
      'field_email_address',
    ];
    foreach ($fields as $field) {
      $individualValues[$field] = $form_state->getValue($field);
    }

    // Create individual node.
    if ($individual = $this->groupsUtility->createNode($individualValues)) {
      // Redirect to the correct place.
      $form_state->setRedirect(
        'pbc_groups.add_group_connection_controller',
        [
          'redirect' => $this->redirect,
          'individual' => $individual->id(),
          'status' => $form_state->getValue('field_group_connection_status')
        ]
      );
    }

  }

}
