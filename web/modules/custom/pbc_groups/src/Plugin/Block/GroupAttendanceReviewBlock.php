<?php

namespace Drupal\pbc_groups\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Cache\Cache;

/**
 * Provides a 'GroupAttendanceReviewBlock' block.
 *
 * @Block(
 *  id = "group_attendance_review_block",
 *  admin_label = @Translation("Need to make edits?"),
 * )
 */
class GroupAttendanceReviewBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Routing\CurrentRouteMatch definition.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;
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
        CurrentRouteMatch $current_route_match
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentRouteMatch = $current_route_match;
  }
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $groupAttendance = $this->currentRouteMatch->getParameter('attendance');
    $url = Url::fromRoute(
      'entity.node.canonical',
      [
        'node' => $groupAttendance->id(),
      ],
      [
        'attributes' => [
          'class' => ['btn', 'btn-info']
        ]
      ]
    );

    $build['link'] = [
      '#prefix' => '<div class="text-center bt">',
      'link' => Link::fromTextAndUrl($this->t('Edit Attendance'), $url)->toRenderable(),
      '#suffix' => '</div>',
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
   public function getCacheTags() {
     $groupAttendance = $this->currentRouteMatch->getParameter('attendance');
     return Cache::mergeTags(parent::getCacheTags(), ['node:' . $groupAttendance->id()]);
   }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}
