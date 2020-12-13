<?php

namespace Drupal\drupal_dspace\Event;

/**
 * Defines events for the dspace entities module.
 *
 * @see \Drupal\Core\Config\ConfigCrudEvent
 */
final class DspaceEntitiesEvents {

  /**
   * Name of the event fired when extracting raw data from an Dspace entity.
   *
   * This event allows you to perform alterations on the raw data after
   * extraction.
   *
   * @Event
   */
  const EXTRACT_RAW_DATA = 'dspace_entity.extract_raw_data';

  /**
   * Name of the event fired when mapping raw data to an Dspace entity.
   *
   * This event allows you to perform alterations on the Dspace entity after
   * mapping.
   *
   * @Event
   */
  const MAP_RAW_DATA = 'dspace_entity.map_raw_data';

}
