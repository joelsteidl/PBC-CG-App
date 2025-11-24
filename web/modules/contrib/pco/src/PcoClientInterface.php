<?php

declare(strict_types=1);

namespace Drupal\pco;

/**
 * Interface for PCO API client to connect to Planning Center Online.
 */
interface PcoClientInterface {

  /**
   * Utilizes Drupal's httpClient to connect to Planning Center Online CRM.
   *
   * Info: https://planning.center/.
   * API Docs: http://planningcenter.github.io/api-docs/.
   *
   * @param string $method
   *   get, post, patch, delete, etc. See Guzzle documentation.
   * @param string $endpoint
   *   The PCO API endpoint (ex. people/v2/people)
   * @param array $query
   *   Query string parameters the endpoint allows (ex. ['per_page' => 50].
   * @param array $body
   *   Gets converted to JSON. Utilized for some endpoints.
   *
   * @return string|false
   *   \GuzzleHttp\Psr7\Response body or false.
   */
  public function connect(string $method, string $endpoint, array $query, array $body): string|false;

}
