<?php

namespace Drupal\pbc_automation\Plugin\QueueWorker;

use Drupal\pbc_automation\PcoTasks;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the pco_webhooks_processor queueworker.
 *
 * @QueueWorker (
 *   id = "pco_webhooks_processor",
 *   title = @Translation("Process PCO webhooks data."),
 *   cron = {"time" = 30}
 * )
 */
class PcoWebhooksProcessor extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructor.
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param \Drupal\pbc_automation\PcoTasks $pco_tasks
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PcoTasks $pco_tasks) {
    parent::__construct($configuration, $pco_tasks, $plugin_definition);
    $this->pcoTasks = $pco_tasks;
  }

  /**
   * Implementation of the container interface to allow dependency injection.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      empty($configuration) ? [] : $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('pbc_automation.pco_tasks')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($content) {
    $data = Json::decode($content);
    if (!isset($data['data'][0]['attributes']['name'], $data['data'][0]['attributes']['payload'])) {
      return;
    }

    $subscription = $data['data'][0]['attributes']['name'];
    $subscriptionParts = explode('.', $subscription);
    // Example people.v2.events.person.destroyed.
    if (count($subscriptionParts) != 5) {
      return;
    }

    $payload = $data['data'][0]['attributes']['payload'];
    $payload = Json::decode($payload);

    $pcoObject = $subscriptionParts[3];
    $pcoOp = ucfirst($subscriptionParts[4]);
    $method = $pcoObject . $pcoOp;
    if (!method_exists($this, $method)) {
      return;
    }

    $pcoId = $payload['data']['id'];
    $this->{$method}($pcoId, $payload);
  }

  /**
   * Event people.v2.events.person.destroyed.
   *
   * @param int $pcoId
   *   PCO ID.
   * @param array $payload
   *   PCO Webhook payload.
   *
   * @return bool
   *   True or FALSE based on the node being updated.
   */
  public function personDestroyed($pcoId, array $payload) {
    if ($individual = $this->pcoTasks->getIndividualbyId($pcoId)) {
      return $this->pcoTasks->deleteIndividual($individual);
    }
    return FALSE;
  }

  /**
   * Event people.v2.events.person.updated.
   *
   * @param int $pcoId
   *   PCO ID.
   * @param array $payload
   *   PCO Webhook payload.
   *
   * @return bool
   *   True or FALSE based on the node being updated.
   */
  public function personUpdated($pcoId, array $payload) {
    $individual = $this->pcoTasks->getIndividualbyId($pcoId);
    if (!$individual) {
      return $this->pcoTasks->createIndividual($individual, $payload);
    }

    return $this->pcoTasks->updateIndividual($individual, $payload);
  }

  /**
   * Event people.v2.events.person.created.
   *
   * @param int $pcoId
   *   PCO ID.
   * @param array $payload
   *   PCO Webhook payload.
   *
   * @return bool
   *   True or FALSE based on the node being created.
   */
  public function personCreated($pcoId, array $payload) {
    $individual = $this->pcoTasks->getIndividualbyId($pcoId);
    if (!$individual) {
      return $this->pcoTasks->createIndividual($pcoId, $payload);
    }
    return $this->pcoTasks->updateIndividual($individual, $payload);
  }

  /**
   * Event people.v2.events.email.destroyed.
   *
   * @param int $pcoEmailId
   *   PCO Email ID.
   * @param array $payload
   *   PCO Webhook payload.
   *
   * @return bool
   *   True or FALSE based on the individual being saved.
   */
  public function emailDestroyed($pcoEmailId, array $payload) {
    return $this->pcoTasks->updateIndividualEmail($payload);
  }

  /**
   * Event people.v2.events.email.created.
   *
   * @param int $pcoEmailId
   *   PCO Email ID.
   * @param array $payload
   *   PCO Webhook payload.
   *
   * @return bool
   *   True or FALSE based on the individual being saved.
   */
  public function emailCreated($pcoEmailId, array $payload) {
    return $this->pcoTasks->updateIndividualEmail($payload);
  }

  /**
   * Event people.v2.events.email.updated.
   *
   * @param int $pcoEmailId
   *   PCO Email ID.
   * @param array $payload
   *   PCO Webhook payload.
   *
   * @return bool
   *   True or FALSE based on the individual being saved.
   */
  public function emailUpdated($pcoEmailId, array $payload) {
    return $this->pcoTasks->updateIndividualEmail($payload);
  }

  /**
   * Event people.v2.events.person_merger.created.
   *
   * Most complex scenario, but fairly easy overall.
   *
   * @param int $pcoMergerId
   *   PCO Merger ID.
   * @param array $payload
   *   PCO Webhook payload.
   *
   * @return bool
   *   True or FALSE based on the individual being saved.
   */
  public function person_mergerCreated($pcoMergerId, array $payload) {
    $keepId = $payload['data']['attributes']['person_to_keep_id'];
    $removeId = $payload['data']['attributes']['person_to_remove_id'];

    $keep = $this->pcoTasks->getIndividualbyId($keepId);
    $remove = $this->pcoTasks->getIndividualbyId($removeId);

    if ($keep && !$remove) {
      // Do nothing. In good standing already.
      return FALSE;
    }
    elseif ($keep && $remove) {
      // Mark the remove account as deleted.
      $this->pcoTasks->deleteIndividual($remove);
      $keep->set('field_pco_deleted', FALSE);
      $keep->save();
      // Transfer group connections to the keep account.
      return $this->pcoTasks->transferGroupConnections($keep, $remove);
    }
    elseif (!$keep && $remove) {
      // Simply change out the PCO ID on the remove Ind to the keep.
      $remove->set('field_pco_deleted', FALSE);
      $remove->set('field_planning_center_id', $keepId);
      return $remove->save();
    }
    elseif (!$keep && !$remove) {
      // Create the new individual.
      if (!$data = $this->pcoTasks->getPcoPerson($keepId)) {
        return FALSE;
      }

      return $this->pcoTasks->createIndividual($keepId, $data);
    }
  }

}
