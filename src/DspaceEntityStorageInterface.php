<?php

namespace Drupal\drupal_dspace;

use Drupal\Core\Entity\ContentEntityStorageInterface;

/**
 * Defines an interface for Dspace entity entity storage classes.
 */
interface DspaceEntityStorageInterface extends ContentEntityStorageInterface {

  /**
   * Property indicating if a save to the Dsprace storage must be skipped.
   *
   * By default saving an Dspace entity will trigger the storage client
   * to save the entities raw data to the Dsprace storage. This will be skipped
   * if this property is set on the Dspace entity.
   *
   * This is used internally to trigger Drupal hooks relevant to Dsprace
   * entity saves, but without touching the storage.
   *
   * @code
   * $dspace_entity->BYPASS_STORAGE_CLIENT_SAVE_PROPERTY = TRUE;
   * // Save the Dspace entity without triggering the storage client.
   * $dspace_entity->save();
   * @endcode
   *
   * @see \namespace Drupal\drupal_dspace\DspaceEntityStorage::doSaveFieldItems()
   *
   * @internal
   *
   * @var string
   */
  const BYPASS_STORAGE_CLIENT_SAVE_PROPERTY = 'BYPASS_STORAGE_CLIENT_SAVE';

  /**
   * Get the storage client.
   *
   * @return \namespace Drupal\drupal_dspace\StorageClient\DspaceEntityStorageClientInterface
   *   The Dspace entity storage client.
   */
  public function getStorageClient();

  /**
   * Gets the Dspace entity type.
   *
   * @return \namespace Drupal\drupal_dspace\DspaceEntityTypeInterface
   *   The Dspace entity type.
   */
  public function getDspaceEntityType();

}
