INTRODUCTION
------------

PCO API module provides functionality for developers that would like to
interact the Planning Center Online API. This modules tries to make little
assumptions about how you would like to interact with the API and focuses and
ease of connection.

This module currently uses the Personal Access Token method for authentication.
You can create credentials by visiting
https://accounts.planningcenteronline.com/ You should end up with a token and
secret.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/pco

REQUIREMENTS
------------

 * Drupal 10.2 or 11.0+
 * PHP 8.1+

This module requires the following modules:

 * Key (https://www.drupal.org/project/key)

INSTALLATION
------------

 * It is recommended that you install with Composer.
   `composer require "drupal/pco ^2.1"`
 * Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.
 * Use ```composer update drupal/pco --with-dependencies```
   to update to a new release.

CONFIGURATION
-------------

 * If you haven't already, create a Personal Access Token at
   https://accounts.planningcenteronline.com/
 * After enabling the PCO module, set the necessary API credentials in Drupal at
   /admin/config/pco You will need to setup your API secret using
   the required Key module. Storing keys in files outside the webroot or as
   environment variables is recommended.

TROUBLESHOOTING
---------------

 * If API calls are failing, please check the logs.
 * Ensure that the module has been configured.
 * Report an issue https://www.drupal.org/project/issues/pco
 * If moving from the 8.x to 2.x, make sure you update service calls.

USAGE
---------------

A reminder, this module does nothing on its own. You must create your own
custom module to leverage the PCO module. This module provides a pco.client
service that can be used in Drupal hooks or in Classes using Dependency
Injection.

The `connect` method accepts the following parameters:

```
   * @param string $method
   *   get, post, patch, delete, etc. See Guzzle documentation.
   * @param string $endpoint
   *   The PCO API endpoint (ex. people/v2/people)
   * @param array $query
   *   Query string parameters the endpoint allows (ex. ['per_page' => 50]
   * @param array $body (converted to JSON)
   *   Utilized for some endpoints
```

### Drupal hook_ example:

```
hook_cron() {
// This would get 50 people from Planning Center on CRON.
$client = Drupal::service('pco.client');
$query = [
  'per_page' => 50,
  'include' => 'emails',
];
$request = $client->connect('get', 'people/v2/people', $query, []);
$results = json_decode($request);
}
```

### Controller using Dependency Injection example:

``` php
<?php

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\pco\Client\PcoClient;

/**
 * Class MyController.
 *
 * @package Drupal\my_custom_module\Controller
 */
class MyController extends ControllerBase {

  /**
   * Drupal\pco\Client\PcoClient definition.
   *
   * @var \Drupal\pco\Client\PcoClient
   */
  protected $pcoClient;

  /**
   * {@inheritdoc}
   */
  public function __construct(PcoClient $pco_client) {
    $this->pcoClient = $pco_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('pco.client')
    );
  }

  /**
   * Content.
   *
   * @return array
   *   Return array.
   */
  public function content() {
    // This would get 50 people from Planning Center on page load.
    $query = [
      'per_page' => 50,
      'include' => 'emails',
    ];

    $request = $this->pcoClient->connect('get', 'people/v2/people', $query, []);
    $results = json_decode($request);
    return [];
  }

}
```
