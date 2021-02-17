<?php

$storage = Drupal::getContainer()->get('entity_type.manager')->getStorage('node');
$individuals = $storage->getQuery()
  ->condition('type', 'individual')
  ->condition('field_pco_deleted', NULL, 'IS NULL')
  ->accessCheck(FALSE)
  ->execute();

foreach ($individuals as $individual) {
  print_r($individual . PHP_EOL);
  $individualNode = $storage->load($individual);
  $individualNode->set('field_pco_deleted', FALSE);
  $individualNode->save();
}
