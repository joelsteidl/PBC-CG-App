<?php

namespace Drupal\pbc_automation;

use Drupal\node\NodeInterface;

/**
 * Interface PcoTasksInterface.
 *
 * @package Drupal\pbc_automation
 */
interface PcoTasksInterface {

  /**
   * Create a new person in Planning Center.
   *
   * @param Drupal\node\NodeInterface $node
   *   Node object.
   *
   * @return int
   *   Planning Center Online Person ID.
   */
  public function createPcoPerson(NodeInterface $node);

  /**
   * Get PCO Person.
   *
   * @param int $pcoId
   *   Planning center individual id.
   *
   * @return mixed
   *   FALSE if not found.
   */
  public function getPcoPerson($pcoId);

  /**
   * Create a new email in Planning Center.
   *
   * @param Drupal\node\NodeInterface $node
   *   Node object.
   * @param int $personId
   *   Planning Center Online Person ID.
   *
   * @return int
   *   Planning Center Online Email ID.
   */
  public function createPcoEmail(NodeInterface $node, $personId);

  /**
   * Get PCO Emails.
   *
   * @param int $pcoId
   *   Planning center individual id.
   *
   * @return string
   *   A single email address.
   */
  public function getPcoEmails($pcoId);

  /**
   * Lookup individuals by the PCO id.
   *
   * @param int $pcoId
   *   Planning center individual id.
   *
   * @return mixed
   *   FALSE if not found.
   */
  public function getIndividualbyId($pcoId);

  /**
   * Mark an individual as deleted.
   *
   * Might do more with this one day.
   *
   * @param \Drupal\node\NodeInterface $individual
   *   Planning center individual id.
   *
   * @return bool
   *   Node save successful or not.
   */
  public function deleteIndividual(NodeInterface $individual);

  /**
   * Create an individual.
   *
   * @param int $pcoId
   *   PCO ID.
   * @param array $payload
   *   PCO Webhook payload.
   *
   * @return bool
   *   Node save successful or not.
   */
  public function createIndividual($pcoId, array $payload);

  /**
   * Update an individual.
   *
   * @param \Drupal\node\NodeInterface $individual
   *   Planning center individual id.
   * @param array $payload
   *   PCO Webhook payload.
   *
   * @return bool
   *   Node save successful or not.
   */
  public function updateIndividual(NodeInterface $individual, array $payload);

  /**
   * Grab the email owner.
   *
   * @param array $payload
   *   Payload from an email webhook.
   *
   * @return mixed
   *   FALSE if not found.
   */
  public function getPcoEmailParent(array $payload);

  /**
   * Update individual email.
   *
   * @param array $payload
   *   PCO Webhook payload.
   *
   * @return bool
   *   Node save successful or not.
   */
  public function updateIndividualEmail(array $payload);

  /**
   * Transfer Group Connections.
   *
   * Aids in a PCO merger.
   *
   * @param \Drupal\node\NodeInterface $keep
   *   The individual node we are keeping.
   * @param \Drupal\node\NodeInterface $remove
   *   The individual node we are removing.
   *
   * @return bool
   *   True if successful and False if not.
   */
  public function transferGroupConnections(NodeInterface $keep, NodeInterface $remove);

}
