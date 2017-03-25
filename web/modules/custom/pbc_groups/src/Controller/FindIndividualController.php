<?php

namespace Drupal\pbc_groups\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

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
  public function getContent(NodeInterface $redirect) {
    drupal_set_message($this->t('Please help us prevent duplicate people! Take a second and scan through this list before adding a new person. Thanks!'), 'warning', FALSE);

    $build = [];

    $url = Url::fromRoute(
      'entity.node.canonical',
      [
        'node' => $redirect->id(),
      ],
      [
        'attributes' => [
          'class' => ['btn', 'btn-link']
        ]
      ]
    );

    $build['cancel'] = [
      // '#prefix' => '<div class="pull-right">',
      'link' => Link::fromTextAndUrl($this->t('&larr; Cancel'), $url)->toRenderable(),
      // '#suffix' => '</div>',
    ];

    $build['search_heading'] = [
      '#prefix' => '<h3>',
      '#markup' => $this->t('Search Existing People'),
      '#suffix' => '</h3>',
    ];
    $build['individual_search'] = views_embed_view('search_individuals', 'block_2');

    return $build;
  }

}
