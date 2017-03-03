<?php

namespace Drupal\pco_api;

interface PcoClientInterface {

  /**
   * Simple get request
   * https://github.com/findbrok/php-watson-api-bridge
   *
   * @param $uri
   *   A URI from the Watson Explorer https://watson-api-explorer.mybluemix.net/
   * @param $query_params
   *   Array of options applicable to the Watson API URI being called.
   * @return object
   *   \GuzzleHttp\Psr7\Response
   */
   public function connect($method, $endpoint, $query, $body);
}
