<?php

namespace Drupal\drupal_dspace\Event;

use Drupal\drupal_dspace\DspaceEntityInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Defines a, Dspace entity raw data extraction event.
 */
class DspaceEntityExtractRawDataEvent extends Event {

  /**
   * The Dspace entity.
   *
   * @var \namespace Drupal\drupal_dspace\DspaceEntityInterface
   */
  protected $entity;

  /**
   * The raw data.
   *
   * @var array
   */
  protected $rawData;

  /**
   * Constructs a map raw data event object.
   *
   * @param \namespace Drupal\drupal_dspace\DspaceEntityInterface $entity
   *   The Dspace entity.
   * @param array $raw_data
   *   The raw data being mapped.
   */
  public function __construct(DspaceEntityInterface $entity, array $raw_data) {
    $this->entity = $entity;
    $this->rawData = $raw_data;
  }

  /**
   * Gets the Dspace entity.
   *
   * @return \namespace Drupal\drupal_dspace\DspaceEntityInterface
   *   The Dspace entity.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Gets the raw data that was extracted.
   *
   * @return array
   *   The raw data.
   */
  public function getRawData() {
    return $this->rawData;
  }

  /**
   * Sets the raw data.
   *
   * @param array $raw_data
   *   The raw data.
   */
  public function setRawData(array $raw_data) {
    $this->rawData = $raw_data;
  }

}
