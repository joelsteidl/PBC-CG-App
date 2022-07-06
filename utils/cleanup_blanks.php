<?php

$storage = Drupal::getContainer()->get('entity_type.manager')->getStorage('node');
$individuals = $storage->getQuery()
  ->condition('type', 'individual')
  ->condition('field_planning_center_id', NULL, 'IS NULL')
  ->condition('field_pco_updated', NULL, 'IS NULL')
  ->accessCheck(FALSE)
  ->execute();

print_r('Count ' . count($individuals));
return;
foreach ($individuals as $individual) {
  print_r($individual . PHP_EOL);
  $individualNode = $storage->load($individual);
  $individualNode->delete();
}
