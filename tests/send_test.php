<?php

$u_storage = Drupal::getContainer()->get('entity_type.manager')->getStorage('user');
$n_storage = Drupal::getContainer()->get('entity_type.manager')->getStorage('node');
$user = $u_storage->load(1);
$attendance = $n_storage->load(56011);

// Use Symfony Mailer
$emailFactory = \Drupal::service('email_factory');
$email = $emailFactory->newTypedEmail('pbc_automation', 'attendance_reminder', $user, $attendance);
$email->setTo($user->getEmail());

$mailer = \Drupal::service('symfony_mailer');
$mailer->send($email);
