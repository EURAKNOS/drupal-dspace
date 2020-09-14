<?php

namespace Drupal\drupal_dspace;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of Dspace entity types.
 *
 * @see \namespace Drupal\drupal_dspace\Entity\DspaceEntityType
 */
class DspaceEntityTypeListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function load() {
      die();
    $entity_query = $this->storage->getQuery();
    $entity_query->pager(50);
    $ids = $entity_query->execute();
    return $this->storage->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'label' => $this->t('Name'),
      'description' => $this->t('Description'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \namespace Drupal\drupal_dspace\DspaceEntityTypeInterface $entity */
    $row['label'] = $entity->label();
    $row['description'] = $entity->getDescription();
    $row['operations']['data'] = $this->buildOperations($entity);
    return $row + parent::buildRow($entity);
  }

}
