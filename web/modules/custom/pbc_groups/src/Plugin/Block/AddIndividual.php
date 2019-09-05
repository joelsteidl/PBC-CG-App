<?php

namespace Drupal\pbc_groups\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Form\FormBuilder;

/**
 * Provides a 'AddIndividual' block.
 *
 * @Block(
 *  id = "add_individual_block",
 *  admin_label = @Translation("Add Someone New"),
 * )
 */
class AddIndividual extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Routing\CurrentRouteMatch definition.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $current_route_match;
  /**
   * Drupal\Core\Form\FormBuilder definition.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;
  /**
   * Construct.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        CurrentRouteMatch $current_route_match,
	FormBuilder $form_builder
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentRouteMatch = $current_route_match;
    $this->formBuilder = $form_builder;
  }
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('form_builder')
    );
  }
  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $route = $this->currentRouteMatch;
    $request = \Drupal::request();
    $defaults = [
      'firstname' => $request->get('firstname'),
      'lastname' => $request->get('lastname'),
      'redirect' => $route->getParameter('redirect')->id(),
    ];
    $build['add_individual']['form'] = $this->formBuilder->getForm('Drupal\pbc_groups\Form\AddIndividualForm', $defaults);

    return $build;
  }

}
