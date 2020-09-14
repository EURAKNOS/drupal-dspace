<?php

namespace Drupal\drupal_dspace\StorageClient;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\drupal_dspace\DspaceEntityInterface;

/**
 * Defines an interface for Dspace entity storage client plugins.
 */
interface DspaceEntityStorageClientInterface extends PluginInspectionInterface, ConfigurableInterface, ContainerFactoryPluginInterface {

  /**
   * Returns the administrative label for this storage client plugin.
   *
   * @return string
   *   The storage clients administrative label.
   */
  public function getLabel();

  /**
   * Returns the administrative description for this storage client plugin.
   *
   * @return string
   *   The storage clients administrative description.
   */
  public function getDescription();

  /**
   * Loads raw data for one or more entities.
   *
   * @param array|null $ids
   *   An array of IDs, or NULL to load all entities.
   *
   * @return array
   *   An array of raw data arrays indexed by their IDs.
   */
  public function loadMultiple(array $ids = NULL);

  /**
   * Saves the entity permanently.
   *
   * @param \namespace Drupal\drupal_dspace\DspaceEntityInterface $entity
   *   The entity to save.
   *
   * @return int
   *   SAVED_NEW or SAVED_UPDATED is returned depending on the operation
   *   performed.
   */
  public function save(DspaceEntityInterface $entity);

  /**
   * Deletes permanently saved entities.
   *
   * @param \namespace Drupal\drupal_dspace\DspaceEntityInterface $entity
   *   The Dspace entity object to delete.
   */
  public function delete(DspaceEntityInterface $entity);

  /**
   * Query the dspace entities.
   *
   * @param array $parameters
   *   (optional) Array of parameters, each value is an array with the following
   *   key-value pairs:
   *     - field: the field name the parameter applies to
   *     - value: the value of the parameter
   *     - operator: the operator of how the parameter should be applied.
   * @param array $sorts
   *   (optional) Array of sorts, each value is an array with the following
   *   key-value pairs:
   *     - field: the field to sort by
   *     - direction: the direction to sort on.
   * @param int|null $start
   *   (optional) The first item to return.
   * @param int|null $length
   *   (optional) The number of items to return.
   */
  public function query(array $parameters = [], array $sorts = [], $start = NULL, $length = NULL);

  /**
   * Query the dspace entities and return the match count.
   *
   * @param array $parameters
   *   (optional) Key-value pairs of fields to query.
   *
   * @return int
   *   A count of matched dspace entities.
   */
  public function countQuery(array $parameters = []);

}
