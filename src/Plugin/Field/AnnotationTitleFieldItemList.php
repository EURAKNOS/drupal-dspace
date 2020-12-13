<?php

namespace Drupal\drupal_dspace\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * A computed annotation title field item list.
 */
class AnnotationTitleFieldItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    $dspace_entity_type_id = \Drupal::entityQuery('dspace_entity_type')
      ->condition('annotation_entity_type_id', $entity->getEntityTypeId())
      ->condition('annotation_bundle_id', $entity->bundle())
      ->range(0, 1)
      ->execute();
    if (!empty($dspace_entity_type_id)) {
      /* @var \Drupal\drupal_dspace\DspaceEntityTypeInterface $dspace_entity_type */
      $dspace_entity_type = \Drupal::entityTypeManager()
        ->getStorage('dspace_entity_type')
        ->load(array_shift($dspace_entity_type_id));
      $annotation_field_name = $dspace_entity_type->getAnnotationFieldName();
      /* @var \Drupal\drupal_dspace\DspaceEntityInterface[] $drupal_dspace */
      $drupal_dspace = $entity->get($annotation_field_name)->referencedEntities();
      foreach ($drupal_dspace as $delta => $dspace_entity) {
        $this->list[$delta] = $this->createItem($delta, $dspace_entity->label());
      }
    }
  }

}
