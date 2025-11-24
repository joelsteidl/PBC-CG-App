<?php

namespace Drupal\symfony_mailer;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\File\Exception\InvalidStreamWrapperException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

/**
 * Defines the class for an Email attachment.
 */
class Attachment extends DataPart implements AttachmentInterface {

  /**
   * The access result.
   */
  protected AccessResultInterface $access;

  /**
   * The path, converted to a full URL with a scheme where possible.
   */
  protected ?string $path = NULL;

  /**
   * For __sleep, only used in tests.
   */
  private array $dataPart;

  /**
   * {@inheritdoc}
   */
  protected function __construct($body, ?string $name = NULL, ?string $mimeType = NULL) {
    $this->access = AccessResult::neutral();
    parent::__construct($body, $name, $mimeType);
  }

  /**
   * {@inheritdoc}
   */
  public static function fromPath(string $path, ?string $name = NULL, ?string $mimeType = NULL, bool $isUri = FALSE): self {
    if (!parse_url($path, PHP_URL_SCHEME)) {
      if ($isUri) {
        // Convert a site-relative URL to absolute so that we can call fopen().
        $path = \Drupal::request()->getSchemeAndHttpHost() . $path;
      }
      else {
        // Try to find a URI for a local file.
        try {
          $url_generator = \Drupal::service('file_url_generator');
          $uri = $url_generator->generateAbsoluteString($path);
        }
        catch (InvalidStreamWrapperException $e) {
        }
      }
    }

    $attachment = new static(new File($path), $name, $mimeType);
    $attachment->path = $uri ?? $path;
    return $attachment;
  }

  /**
   * {@inheritdoc}
   */
  public static function fromData(string $data, ?string $name = NULL, ?string $mimeType = NULL): self {
    return new static($data, $name, $mimeType);
  }

  /**
   * {@inheritdoc}
   */
  public function setAccess(AccessResultInterface $access): self {
    $this->access = $this->access->orIf($access);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAccess(): bool {
    return $this->access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getUri(): ?string {
    return $this->path;
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep(): array {
    $sleep = ['path', 'dataPart'];
    $p_sleep = parent::__sleep();
    foreach ($p_sleep as $name) {
      if ($name[0] == '_') {
        $sleep[] = $name;
      }
      else {
        $r = new \ReflectionProperty(DataPart::class, $name);
        $this->dataPart[$name] = $r->getValue($this);
      }
    }

    return $sleep;
  }

  /**
   * {@inheritdoc}
   */
  public function __wakeup(): void {
    foreach (array_keys($this->dataPart) as $name) {
      $r = new \ReflectionProperty(DataPart::class, $name);
      $r->setValue($this, $this->dataPart[$name]);
    }
    unset($this->dataPart);

    parent::__wakeup();
  }

}
