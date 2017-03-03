<?php

namespace Drupal\pbc_groups\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\node\NodeInterface;
use Drupal\pbc_groups\GroupsUtility;
use Symfony\Component\HttpFoundation\RequestStack;
/**
 * Class ManageGroupsApi.
 *
 * @package Drupal\pbc_groups\Controller
 */
class ManageGroupsApi extends ControllerBase {

  /**
   * Drupal\pbc_groups\GroupsUtility definition.
   *
   * @var \Drupal\pbc_groups\GroupsUtility
   */
  protected $pbcGroupsUtility;
  /**
   * Symfony\Component\HttpFoundation\Request.
   *
   * @var Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  public function __construct(GroupsUtility $pbc_groups_utility, RequestStack $request) {
    $this->pbcGroupsUtility = $pbc_groups_utility;
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('pbc_groups.utility'),
      $container->get('request_stack')
    );
  }

  /**
   * Add.
   *
   * Takes querystring params and creates nodes.
   *
   * @return redirect
   *   Return Hello string.
   */
  public function add(NodeInterface $redirect) {

    $params = $this->request->getCurrentRequest()->query->all();

    if (count($params)) {
      $this->pbcGroupsUtility->createNode($params);

      drupal_set_message(t('Success!'), 'success', FALSE);

      return $this->redirect('entity.node.canonical', ['node' => $redirect->id()]);
    }
  }

}
