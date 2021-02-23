<?php

$task = Drupal::service('pbc_automation.pco_tasks');
$p = $task->getPcoPerson(731756);
print_r($p);
