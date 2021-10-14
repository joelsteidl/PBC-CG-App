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
      'link' => Link::fromTextAndUrl($this->t('&larr; Cancel'), $url)->toRenderable(),
    ];

    return $build;
  }

}
