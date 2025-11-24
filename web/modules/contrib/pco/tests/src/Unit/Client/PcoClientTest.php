<?php

declare(strict_types=1);

namespace Drupal\Tests\pco\Unit\Client;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\key\Entity\Key;
use Drupal\key\KeyRepositoryInterface;
use Drupal\pco\Client\PcoClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Unit tests for the PcoClient class.
 *
 * @group pco
 * @coversDefaultClass \Drupal\pco\Client\PcoClient
 */
class PcoClientTest extends TestCase {

  /**
   * The mocked HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The mocked key repository.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * The mocked config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The mocked logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The mocked messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The PCO client being tested.
   *
   * @var \Drupal\pco\Client\PcoClient
   */
  protected $pcoClient;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a partial mock of the Guzzle Client that allows us to mock
    // the dynamic methods.
    $this->httpClient = $this->createPartialMock(
      Client::class,
      ['get', 'post', 'patch', 'delete', 'put']
    );
    $this->keyRepository = $this->createMock(KeyRepositoryInterface::class);
    $this->config = $this->createMock(ImmutableConfig::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $this->messenger = $this->createMock(MessengerInterface::class);

    // Set up basic config values.
    $this->config
      ->expects($this->any())
      ->method('get')
      ->willReturnMap([
        ['token', 'test-token'],
        ['secret', 'secret-key-name'],
        ['base_uri', 'https://api.planningcenteronline.com/'],
      ]);

    // Set up key repository to return a key.
    $mockKey = $this->createMock(Key::class);
    $mockKey
      ->expects($this->any())
      ->method('getKeyValue')
      ->willReturn('test-secret-value');
    $this->keyRepository
      ->expects($this->any())
      ->method('getKey')
      ->willReturn($mockKey);

    // Mock the translation service.
    $stringTranslation = $this->createMock(TranslationInterface::class);
    $stringTranslation
      ->expects($this->any())
      ->method('translate')
      ->willReturnArgument(0);
    $container = $this->createMock(ContainerInterface::class);
    $container
      ->expects($this->any())
      ->method('get')
      ->with('string_translation')
      ->willReturn($stringTranslation);
    \Drupal::setContainer($container);
  }

  /**
   * Tests that a GET request succeeds and returns the response body.
   *
   * @covers ::connect
   */
  public function testConnectGetRequest(): void {
    $response_body = json_encode(['data' => ['id' => '123', 'name' => 'Test']]);
    $response = new Response(200, ['X-PCO-API-Request-Rate-Count' => ['50']], $response_body);

    $this->httpClient->expects($this->once())
      ->method('get')
      ->with(
        'https://api.planningcenteronline.com/people/v2/people',
        new IsType('array')
      )
      ->willReturn($response);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory
      ->expects($this->any())
      ->method('get')
      ->willReturn($this->config);

    $client = new PcoClient(
      $this->httpClient,
      $this->keyRepository,
      $configFactory,
      $this->logger,
      $this->messenger
    );

    $result = $client->connect('get', 'people/v2/people');
    $this->assertEquals($response_body, $result);
  }

  /**
   * Tests that a GET request with query parameters is properly formatted.
   *
   * @covers ::connect
   */
  public function testConnectGetRequestWithQueryParameters(): void {
    $response_body = json_encode(['data' => []]);
    $response = new Response(200, ['X-PCO-API-Request-Rate-Count' => ['50']], $response_body);

    $this->httpClient->expects($this->once())
      ->method('get')
      ->willReturnCallback(function ($uri, $options) use ($response) {
        $this->assertEquals('https://api.planningcenteronline.com/people/v2/people', $uri);
        $this->assertArrayHasKey('query', $options);
        $this->assertEquals(['per_page' => 100], $options['query']);
        $this->assertArrayHasKey('auth', $options);
        return $response;
      });

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory
      ->expects($this->any())
      ->method('get')
      ->willReturn($this->config);

    $client = new PcoClient(
      $this->httpClient,
      $this->keyRepository,
      $configFactory,
      $this->logger,
      $this->messenger
    );

    $result = $client->connect('get', 'people/v2/people', ['per_page' => 100]);
    $this->assertEquals($response_body, $result);
  }

  /**
   * Tests that a POST request with body data is properly formatted.
   *
   * @covers ::connect
   */
  public function testConnectPostRequestWithBody(): void {
    $response_body = json_encode(['data' => ['id' => '456']]);
    $response = new Response(201, ['X-PCO-API-Request-Rate-Count' => ['50']], $response_body);

    $this->httpClient->expects($this->once())
      ->method('post')
      ->willReturnCallback(function ($uri, $options) use ($response) {
        $this->assertEquals('https://api.planningcenteronline.com/people/v2/people', $uri);
        $this->assertArrayHasKey('json', $options);
        $this->assertEquals(['name' => 'John Doe'], $options['json']);
        $this->assertArrayHasKey('auth', $options);
        return $response;
      });

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory
      ->expects($this->any())
      ->method('get')
      ->willReturn($this->config);

    $client = new PcoClient(
      $this->httpClient,
      $this->keyRepository,
      $configFactory,
      $this->logger,
      $this->messenger
    );

    $result = $client->connect('post', 'people/v2/people', [], ['name' => 'John Doe']);
    $this->assertEquals($response_body, $result);
  }

  /**
   * Tests that request exceptions are caught and return FALSE.
   *
   * @covers ::connect
   */
  public function testConnectHandlesException(): void {
    $exception = new RequestException(
      'Connection failed',
      $this->createMock(RequestInterface::class)
    );

    $this->httpClient->expects($this->once())
      ->method('get')
      ->willThrowException($exception);

    $this->messenger->expects($this->once())
      ->method('addError');
    $this->logger->expects($this->once())
      ->method('error');

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory
      ->expects($this->any())
      ->method('get')
      ->willReturn($this->config);

    $client = new PcoClient(
      $this->httpClient,
      $this->keyRepository,
      $configFactory,
      $this->logger,
      $this->messenger
    );

    $result = $client->connect('get', 'people/v2/people');
    $this->assertFalse($result);
  }

  /**
   * Tests that authentication credentials are properly passed to requests.
   *
   * @covers ::connect
   */
  public function testConnectUsesBasicAuth(): void {
    $response = new Response(200, ['X-PCO-API-Request-Rate-Count' => ['50']], '{}');

    $this->httpClient->expects($this->once())
      ->method('get')
      ->willReturnCallback(function ($uri, $options) use ($response) {
        $this->assertArrayHasKey('auth', $options);
        $this->assertEquals(['test-token', 'test-secret-value'], $options['auth']);
        return $response;
      });

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory
      ->expects($this->any())
      ->method('get')
      ->willReturn($this->config);

    $client = new PcoClient(
      $this->httpClient,
      $this->keyRepository,
      $configFactory,
      $this->logger,
      $this->messenger
    );

    $client->connect('get', 'people/v2/people');
  }

}
