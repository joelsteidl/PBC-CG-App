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
   * Given a PCO record, create or update.
   *
   * @param object $pcoRecord
   *   An object returned from the PCO API.
   *
   * @return node object
   *   a node object.
   */
  public function createOrUpdateNode($pcoRecord, $force);

  /**
   * Given a date, return PCO records that have been updated since then.
   *
   * @return array
   *   array of PCO records.
   */
  public function getPcoPeopleFromList($offset, $perPage, $listId);

  /**
   * Given a PCO record, convert it to node data.
   *
   * @param object $pcoRecord
   *   An object returned from the PCO API.
   *
   * @return array
   *   values a node needs.
   */
  public function convertPcoToNode($pcoRecord);

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
   * Create a new custom field value in PCO API.
   *
   * @param Drupal\node\NodeInterface $node
   *   Node object.
   * @param array $fieldInfo
   *   Array containing PCO field ID and the value.
   * @param int $personId
   *   Planning Center Online Person ID.
   *
   * @return int
   *   Planning Center Online Email ID.
   */
  public function createPcoFieldData(NodeInterface $node, $fieldInfo, $personId);

  /**
   * Create a new email in Planning Center.
   *
   * @param int $emailId
   *   Planning Center Online Person ID.
   *
   * @return string
   *   Planning Center Online Email Address.
   */
  public function getPcoEmail($emailId);

  /**
   * Create a new person in Planning Center.
   *
   * @param int $perPage
   *   Number of results per page.
   *   TODO: Possibly limit pass date.
   *
   * @return array
   *   Planning Center Online returned JSON converted to Array.
   */
  public function getPcoPeople($offset, $perPage);

}
