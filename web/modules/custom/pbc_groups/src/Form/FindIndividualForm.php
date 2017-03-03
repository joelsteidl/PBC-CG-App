<?php

/**
 * @file
 * Contains \Drupal\pbc_groups\Form\FindIndividualForm.
 */

namespace Drupal\pbc_groups\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class FindIndividualForm.
 *
 * @package Drupal\pbc_groups\Form
 */
class FindIndividualForm extends FormBase {

  /**
   * Entity type manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $currentRouteMatch;

  /**
   * Constructor.
   */
  public function __construct(RouteMatchInterface $currentRouteMatch) {
    $this->currentRouteMatch = $currentRouteMatch;
  }

  /**
   * Create.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pbc_groups_find_individual_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['firstname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First Name'),
      '#required' => 1,
      '#weight' => 1,
    ];

    $form['lastname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last Name'),
      '#required' => 1,
      '#weight' => 2,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#weight' => 3,
      '#value' => $this->t('Add Person'),
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
    $node = $this->currentRouteMatch->getParameter('node');

    $params = [
      'redirect' => $node->id(),
      'firstname' => $form_state->getValue('firstname'),
      'lastname' => $form_state->getValue('lastname'),
    ];

    $options = [
      'query' => [
        'firstname' => $form_state->getValue('firstname'),
        'lastname' => $form_state->getValue('lastname'),
      ]
    ];

    $form_state->setRedirect(
      'pbc_groups.find_individual_controller_getContent',
      $params,
      $options
    );
  }

}
