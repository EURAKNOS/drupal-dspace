<?php

namespace Drupal\drupal_dspace;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\drupal_dspace\Event\DspaceEntitiesEvents;
use Drupal\drupal_dspace\Event\DspaceEntityExtractRawDataEvent;
use Drupal\drupal_dspace\DspaceEntityInterface;
use Drupal\drupal_dspace\DspaceEntityTypeInterface;

/**
 * Defines the Dspace entity class.
 *
 * @see drupal_dspace_entity_type_build()
 * 
 * @ContentEntityType(
 *   handlers = {
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   translatable = TRUE,
 *   common_reference_target = TRUE
 * )
 * 
 */
