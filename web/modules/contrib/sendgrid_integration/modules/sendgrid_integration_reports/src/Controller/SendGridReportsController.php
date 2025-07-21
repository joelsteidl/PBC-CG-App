<?php

namespace Drupal\sendgrid_integration_reports\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\sendgrid_integration_reports\Api;

/**
 * Class for rendering sendgrid reports page.
 *
 * @package Drupal\sendgrid_integration_reports\Controller
 */
class SendGridReportsController extends ControllerBase {

  /**
   * Include the messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * Api service.
   *
   * @var \Drupal\sendgrid_integration_reports\Api
   */
  protected $api;

  /**
   * SendGridReportsController constructor.
   *
   * @param \Drupal\sendgrid_integration_reports\Api $api
   *   The sendgrid api service.
   */
  public function __construct(Api $api) {
    $this->api = $api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('sendgrid_integration_reports.api')
    );
  }

  /**
   * Returns stats categories.
   *
   * @param array $categories
   *   Array of categories.
   * @param string|null $start_date
   *   Start date.
   * @param string|null $end_date
   *   End date.
   * @param bool $refresh
   *   Flag is cache should be refreshed.
   *
   * @return array|bool
   *   Array of stats data.
   */
  public function getStatsCategories(array $categories, $start_date = NULL, $end_date = NULL, $refresh = FALSE) {
    $cid = 'sendgrid_reports_categories';
    // Sanitize the categories array and generate the cache ID.
    if ($categories && is_array($categories)) {
      $categories = array_values($categories);
      $cid .= '_' . implode('_', $categories);
    }
    else {
      $categories = NULL;
    }

    return $this->api->getStats($cid, $categories, $start_date, $end_date, $refresh);
  }

  /**
   * Returns reports.
   */
  public function getReports() {
    $stats = $this->api->getStats('sendgrid_reports_global');
    $settings = [];
    $stats['global'] = isset($stats['global']) ? $stats['global'] : [];

    foreach ($stats['global'] as $items) {
      $settings['global'][] = [
        'date' => $items['date'],
        'opens' => $items['opens'],
        'clicks' => $items['clicks'],
        'delivered' => $items['delivered'],
        'spam_reports' => $items['spam_reports'],
        'spam_report_drops' => $items['spam_report_drops'],
      ];
    }

    $render = [
      '#attached' => [
        'library' => [
          'sendgrid_integration_reports/googlejsapi',
          'sendgrid_integration_reports/main',
        ],
        'drupalSettings' => [
          'sendgrid_integration_reports' => $settings,
        ],
      ],
      'message' => [
        '#markup' => $this->t('The following reports are the from the Global Statistics provided by SendGrid. For more comprehensive data, please visit your @dashboard. @cache to ensure the data is current. @settings to alter the time frame of this data.',
          [
            '@dashboard' => Link::fromTextAndUrl($this->t('SendGrid Dashboard'), Url::fromUri('//app.sendgrid.com/'))
              ->toString(),
            '@cache' => Link::createFromRoute($this->t('Clear your cache'), 'system.performance_settings')
              ->toString(),
            '@settings' => Link::createFromRoute($this->t('Change your settings'), 'sendgrid_integration_reports.settings_form')
              ->toString(),
          ]
        ),
      ],
      'volume' => [
        '#prefix' => '<h2>' . $this->t('Sending Volume') . '</h2>',
        '#markup' => '<div id="sendgrid-global-volume-chart"></div>',
      ],
      'spam' => [
        '#prefix' => '<h2>' . $this->t('Spam Reports') . '</h2>',
        '#markup' => '<div id="sendgrid-global-spam-chart"></div>',
      ],
    ];
    $browserstats = $this->api->getStatsBrowser();

    $rows = [];
    foreach ($browserstats as $key => $value) {
      $rows[] = [$key, $value];
    }
    $headerbrowser = [
      $this->t('Browser'),
      $this->t('Click Count'),
    ];
    $render['browsers'] = [
      '#prefix' => '<h2>' . $this->t('Browser Statistics') . '</h2>',
      '#theme' => 'table',
      '#header' => $headerbrowser,
      '#rows' => $rows,
      'attributes' => ['width' => '75%'],
    ];

    $devicestats = $this->api->getStatsDevices();
    $rowsdevices = [];
    foreach ($devicestats as $key => $value) {
      $rowsdevices[] = [
        $key,
        $value,
      ];
    }
    $headerdevices = [
      $this->t('Device'),
      $this->t('Open Count'),
    ];
    $render['devices'] = [
      '#prefix' => '<h2>' . $this->t('Device Statistics') . '</h2>',
      '#theme' => 'table',
      '#header' => $headerdevices,
      '#rows' => $rowsdevices,
      'attributes' => ['width' => '75%'],
    ];

    return $render;
  }

}
