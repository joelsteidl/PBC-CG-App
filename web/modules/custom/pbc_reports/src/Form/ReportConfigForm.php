<?php

namespace Drupal\pbc_reports\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\pbc_groups\GroupsUtility;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Component\Datetime\Time;

/**
 * Class ReportConfigForm.
 */
class ReportConfigForm extends FormBase {

  /**
   * Drupal\pbc_groups\GroupsUtility definition.
   *
   * @var \Drupal\pbc_groups\GroupsUtility
   */
  protected $pbcGroupsUtility;
  /**
   * Drupal\Core\Datetime\DateFormatter definition.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;
  /**
   * Drupal\Component\Datetime\Time definition.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $datetimeTime;
  /**
   * Constructs a new ReportConfigForm object.
   */
  public function __construct(
    GroupsUtility $pbc_groups_utility,
    DateFormatter $date_formatter,
    Time $datetime_time
  ) {
    $this->pbcGroupsUtility = $pbc_groups_utility;
    $this->dateFormatter = $date_formatter;
    $this->datetimeTime = $datetime_time;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('pbc_groups.utility'),
      $container->get('date.formatter'),
      $container->get('datetime.time')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'report_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $groups = $this->pbcGroupsUtility->getGroupNodes(NULL, 'object');

    $activeGroups = [];
    $allGroups = [];

    // Create Labels.
    foreach ($groups as $group) {
      $status = $group->field_group_status->value;
      $allGroups[$group->id()] = $group->getTitle() . ' (' . $status . ')';
      if ($status === 'Active') {
        $activeGroups[$group->id()] = $group->id();
      }
    }

    $form['groups'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Groups'),
      '#options' => $allGroups,
      '#default_value' => $activeGroups,
    ];
    $form['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Start Date'),
    ];
    $form['end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('End Date'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('See Report'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->disableRedirect();
  }

}
