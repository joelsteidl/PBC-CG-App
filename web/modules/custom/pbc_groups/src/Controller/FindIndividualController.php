<?php

namespace Drupal\pbc_groups\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBuilder;

/**
 * Class FindIndividualController.
 *
 * @package Drupal\pbc_groups\Controller
 */
class FindIndividualController extends ControllerBase {

  /**
   * Drupal\Core\Form\FormBuilder definition.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(FormBuilder $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * Getcontent.
   *
   * @return string
   *   Return Hello string.
   */
  public function getContent(NodeInterface $redirect, $firstname, $lastname) {
    $defaults = [
      'firstname' => $firstname,
      'lastname' => $lastname,
      'redirect' => $redirect->id(),
    ];
    $build = [];
    $build['individual_search'] = views_embed_view('search_individuals', 'block_1');

    $build['add_individual']['form'] = $this->formBuilder->getForm('Drupal\pbc_groups\Form\AddIndividualForm', $defaults);

    return $build;
  }

}
