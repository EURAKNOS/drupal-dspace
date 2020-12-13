<?php

namespace Drupal\drupal_dspace\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an Dspace entity storage client annotation object.
 *
 * @see \Drupal\drupal_dspace\StorageClient\DspaceEntityStorageClientManager
 * @see plugin_api
 *
 * @Annotation
 */
class DspaceEntityStorageClient extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-friendly name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
