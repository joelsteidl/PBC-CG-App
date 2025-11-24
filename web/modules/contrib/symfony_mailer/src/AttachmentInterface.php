<?php

namespace Drupal\symfony_mailer;

use Drupal\Core\Access\AccessResultInterface;

/**
 * Defines the interface for an Email attachment.
 */
interface AttachmentInterface {

  /**
   * Creates an attachment from a file or URL.
   *
   * @param string $path
   *   A path that specifies the attachment content.
   * @param string|null $name
   *   (optional) The file name.
   * @param string|null $mimeType
   *   (optional) The MIME type. If omitted, the type will be guessed.
   * @param bool $isUri
   *   Determines the behaviour when the path has no schema:
   *   - FALSE (default): path is a local file.
   *   - TRUE: path is a root-relative URI.
   *
   * @return self
   *   The attachment.
   */
  public static function fromPath(string $path, ?string $name = NULL, ?string $mimeType = NULL, bool $isUri = FALSE): self;

  /**
   * Creates an attachment from temporary content.
   *
   * If the content comes from a file or URI then use fromFilename(). This is
   * important to allow correct security checking.
   *
   * @param string $data
   *   The content of the attachment.
   * @param string|null $name
   *   (optional) The file name.
   * @param string|null $mimeType
   *   (optional) The MIME type. If omitted, the type will be guessed.
   *
   * @return self
   *   The attachment.
   */
  public static function fromData(string $data, ?string $name = NULL, ?string $mimeType = NULL): self;

  /**
   * Sets access to the attachment.
   *
   * The supplied value is combined with the existing access using orIf().
   *
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The access result.
   *
   * @return $this
   */
  public function setAccess(AccessResultInterface $access): self;

  /**
   * Checks if access is allowed to the attachment.
   *
   * @return bool
   *   Whether access is allowed.
   */
  public function hasAccess(): bool;

  /**
   * Gets the URI for this attachment.
   *
   * This function returns a full URI with a scheme where possible. This has 2
   * main uses.
   * - To search for the URI in the email body and replace with a CID.
   * - To check if the attachment is publicly accessible by call fopen() on
   *   this URI.
   *
   * If the path corresponds to a local file that has no URI then this function
   * returns the file path, without any scheme. This indicates a file that is
   * not publicly accessible and it might be a security risk to allow access.
   *
   * @return string
   *   The path, or NULL if the attachment wasn't generated from a path.
   */
  public function getUri(): ?string;

  /**
   * Sets the human-readable name of the attachment.
   *
   * @param string $name
   *   The attachment name.
   *
   * @return $this
   */
  public function setName(string $name): static;

  /**
   * Gets the human-readable name of the attachment.
   *
   * This is typically displayed by the mail client interface. Defaults to the
   * base name of the path, if set.
   *
   * @return string
   *   The attachment name, or NULL if no name has been set.
   */
  public function getName(): ?string;

  /**
   * Gets the contents of the attachment.
   *
   * @return string
   *   The contents.
   */
  public function getBody(): string;

  /**
   * Sets the content ID.
   *
   * @param string $cid
   *   The content ID.
   *
   * @return $this
   */
  public function setContentId(string $cid): static;

  /**
   * Gets the content ID.
   *
   * @return string
   *   The content ID.
   */
  public function getContentId(): string;

  /**
   * Gets the MIME type.
   *
   * @return string
   *   The MIME type.
   */
  public function getContentType(): string;

  /**
   * Gets the media type, which is the first part of the MIME type.
   *
   * @return string
   *   The media type.
   */
  public function getMediaType(): string;

}
