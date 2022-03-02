<?php

$storage = Drupal::getContainer()->get('entity_type.manager')->getStorage('node');
$individuals = $storage->getQuery()
  ->condition('type', 'individual')
  ->condition('title', '', '=')
  ->accessCheck(FALSE)
  ->execute();

print_r('Count ' . count($individuals));
return;
foreach ($individuals as $individual) {
  print_r($individual . PHP_EOL);
  $individualNode = $storage->load($individual);
  $individualNode->delete();
}
