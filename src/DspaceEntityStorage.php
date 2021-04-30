<?php

namespace Drupal\drupal_dspace;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\drupal_dspace\Event\DspaceEntitiesEvents;
use Drupal\drupal_dspace\Event\DspaceEntityMapRawDataEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Defines the storage handler class for dspace entities.
 *
 * This extends the base storage class, adding required special handling for
 * e entities.
 */
class DspaceEntityStorage extends ContentEntityStorageBase implements DspaceEntityStorageInterface {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Dspace storage client manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $storageClientManager;

  /**
   * Storage client instance.
   *
   * @var \Drupal\drupal_dspace\StorageClient\DspaceEntityStorageClientInterface
   */
  protected $storageClient;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_field.manager'),
      $container->get('cache.entity'),
      $container->get('entity.memory_cache'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.drupal_dspace.storage_client'),
      $container->get('datetime.time'),
      $container->get('event_dispatcher'),
      $container->get('date.formatter')
    );
  }

  /**
   * Constructs a new DspaceEntityStorage object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend to be used.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface|null $memory_cache
   *   The memory cache backend.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $storage_client_manager
   *   The storage client manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityFieldManagerInterface $entity_field_manager,
    CacheBackendInterface $cache,
    MemoryCacheInterface $memory_cache,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    EntityTypeManagerInterface $entity_type_manager,
    PluginManagerInterface $storage_client_manager,
    TimeInterface $time,
    EventDispatcherInterface $event_dispatcher,
    DateFormatterInterface $date_formatter
  ) {
    parent::__construct($entity_type, $entity_field_manager, $cache, $memory_cache, $entity_type_bundle_info);
    $this->entityTypeManager = $entity_type_manager;
    $this->storageClientManager = $storage_client_manager;
    $this->time = $time;
    $this->entityFieldManager = $entity_field_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->dateFormatter = $date_formatter;
  }
                  
  /**
   * {@inheritdoc}
   */
  public function getStorageClient() {
    if (!$this->storageClient) {
      $this->storageClient = $this
        ->getDspaceEntityType()
        ->getStorageClient();
    }
    return $this->storageClient;
  }

  /**
   * Acts on entities before they are deleted and before hooks are invoked.
   *
   * Used before the entities are deleted and before invoking the delete hook.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   An array of entities.
   *
   * @throws EntityStorageException
   */
  public function preDelete(array $entities) {
    if ($this->getDspaceEntityType()->isReadOnly()) {
      throw new EntityStorageException($this->t('Can not delete read-only dspace entities.'));
    }
  }

  /**
   * Gets the entity type definition.
   *
   * @return \Drupal\drupal_dspace\DspaceEntityTypeInterface
   *   Entity type definition.
   */
  public function getEntityType() {
    /* @var \Drupal\drupal_dspace\DspaceEntityTypeInterface $entity_type */
    $entity_type = $this->entityType;
    return $entity_type;
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities) {
    // Do the actual delete.
    foreach ($entities as $entity) {
      $this->getStorageClient()->delete($entity);
    }
  }
  
    public function loadMultiple(array $ids = NULL) {
    $entities = [];

    // Create a new variable which is either a prepared version of the $ids
    // array for later comparison with the entity cache, or FALSE if no $ids
    // were passed. The $ids array is reduced as items are loaded from cache,
    // and we need to know if it's empty for this reason to avoid querying the
    // database when all requested entities are loaded from cache.
    $passed_ids = !empty($ids) ? array_flip($ids) : FALSE;

    // Try to load entities from the static cache, if the entity type supports
    // static caching.
    if ($this->entityType
      ->isStaticallyCacheable() && $ids) {
      $entities += $this
        ->getFromStaticCache($ids);

      // If any entities were loaded, remove them from the ids still to load.
      if ($passed_ids) {
        $ids = array_keys(array_diff_key($passed_ids, $entities));
      }
    }


    // Load any remaining entities from the database. This is the case if $ids
    // is set to NULL (so we load all entities) or if there are any ids left to
    // load.
    if ($ids === NULL || $ids) {
      $queried_entities = $this
        ->doLoadMultiple($ids);
    }

    // Pass all entities loaded from the database through $this->postLoad(),
    // which attaches fields (if supported by the entity type) and calls the
    // entity type specific load callback, for example hook_node_load().
    if (!empty($queried_entities)) {
      $this
        ->postLoad($queried_entities);
      $entities += $queried_entities;
    }
    if ($this->entityType
      ->isStaticallyCacheable()) {

      // Add entities to the cache.
      if (!empty($queried_entities)) {
        $this
          ->setStaticCache($queried_entities);
      }
    }

    // Ensure that the returned array is ordered the same as the original
    // $ids array if this was passed in and remove any invalid ids.
    if ($passed_ids) {

      // Remove any invalid ids from the array.
      $passed_ids = array_intersect_key($passed_ids, $entities);
      foreach ($entities as $entity) {
        $passed_ids[$entity
          ->id()] = $entity;
      }
      $entities = $passed_ids;
    }
    return $entities;
  }
  
  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $ids = NULL) {
    // Attempt to load entities from the persistent cache. This will remove IDs
    // that were loaded from $ids.
    $entities_from_cache = $this->getFromPersistentCache($ids);

    // Load any remaining entities from the Dspace storage.
    if ($entities_from_storage = $this->getFromExternalStorage($ids)) {
      $this->invokeStorageLoadHook($entities_from_storage);
      $this->setPersistentCache($entities_from_storage);
    }
    
    $entities = $entities_from_cache + $entities_from_storage;
     
    // Map annotation fields to annotatable dspace entities.
    foreach ($entities as $dspace_entity) {
      /* @var \Drupal\drupal_dspace\DspaceEntityInterface $dspace_entity */
      if ($dspace_entity->getDspaceEntityType()->isAnnotatable()) {
        $dspace_entity->mapAnnotationFields();
      }
    }

    return $entities;
  }

  /**
   * Gets entities from the Dspace storage.
   *
   * @param array|null $ids
   *   If not empty, return entities that match these IDs. Return no entities
   *   when NULL.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   Array of entities from the storage.
   */
  protected function getFromExternalStorage(array $ids = NULL) {
    $entities = [];

    if (!empty($ids)) {
      // Sanitize IDs. Before feeding ID array into buildQuery, check whether
      // it is empty as this would load all entities.
      $ids = $this->cleanIds($ids);
    }

    if ($ids === NULL || $ids) {
      $data = $this
        ->getStorageClient()
        ->loadMultiple($ids);

      // Map the data into entity objects and according fields.
      if ($data) {
        $entities = $this->mapFromRawStorageData($data);
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function cleanIds(array $ids, $entity_key = 'id') {
    // getFieldStorageDefinitions() is used instead of
    // getActiveFieldStorageDefinitions() because the latter fails to return
    // all definitions in the event an Dspace entity is not cached locally.
    $definitions = $this->entityFieldManager->getFieldStorageDefinitions($this->entityTypeId);
    $field_name = $this->entityType->getKey($entity_key);
    if ($field_name && $definitions[$field_name]->getType() == 'integer') {
      $ids = array_filter($ids, function ($id) {
        return is_numeric($id) && $id == (int) $id;
      });
      $ids = array_map('intval', $ids);
    }
    return $ids;
  }

  /**
   * Maps from storage data to entity objects, and attaches fields.
   *
   * @param array $data
   *   Associative array of storage results, keyed on the entity ID.
   *
   * @return \Drupal\drupal_dspace\DspaceEntityInterface[]
   *   An array of entity objects implementing the DspaceEntityInterface.
   */
  protected function mapFromRawStorageData(array $data) {
    if (!$data) {
      return [];
    }

    $field_definitions = $this
      ->entityFieldManager
      ->getFieldDefinitions($this->getEntityTypeId(), $this->getEntityTypeId());
    $values = [];
    foreach ($data as $id => $raw_data) {
      $values[$id] = [];

      foreach ($this->getDspaceEntityType()->getFieldMappings() as $field_name => $properties) {
        $field_definition = $field_definitions[$field_name];
        $field_values = [];

        foreach ($properties as $property_name => $mapped_key) {
          // The plus (+) character at the beginning of a mapping key indicates
          // the property doesn't have a mapping but a default value. We process
          // default values after all the regular mappings have been processed.
          if (strpos($mapped_key, '+') === 0) {
            continue;
          }

          $exploded_mapped_key = explode('/', $mapped_key);
          // The asterisk (*) character indicates that we are dealing with a
          // multivalued field. We consider each individual field item to be in
          // its separate array.
          if (!empty($raw_data[$exploded_mapped_key[0]]) && isset($exploded_mapped_key[1]) && $exploded_mapped_key[1] === '*') {
            $parents = array_slice($exploded_mapped_key, 2);
            foreach (array_values($raw_data[$exploded_mapped_key[0]]) as $key => $value) {
              $value = !is_array($value) ? [$value] : $value;
              $field_values[$key][$property_name] = NestedArray::getValue($value, $parents);
            }
          }
          else {
            $property_values = NestedArray::getValue($raw_data, $exploded_mapped_key);
            if (!is_array($property_values)) {
              $property_values = [$property_values];
            }

            foreach (array_values($property_values) as $key => $property_value) {
              $field_values[$key][$property_name] = $property_value;
            }
          }
        }

        // Process the default values.
        foreach ($properties as $property_name => $mapped_key) {
          if (strpos($mapped_key, '+') === 0) {
            foreach (array_keys($field_values) as $key) {
              $field_values[$key][$property_name] = substr($mapped_key, 1);
            }
          }
        }

        // Provide specific conversion for dates.
        $date_fields = ['created', 'changed', 'datetime', 'timestamp'];
        if (in_array($field_definition->getType(), $date_fields)) {
          foreach ($field_values as $key => $item) {
            if (!empty($item['value'])) {
              $timestamp = !is_numeric($item['value'])
                ? strtotime($item['value'])
                : $item['value'];

              if ($field_definition->getType() === 'datetime') {
                switch ($field_definition->getSetting('datetime_type')) {
                  case DateTimeItem::DATETIME_TYPE_DATE:
                    $format = DateTimeItemInterface::DATE_STORAGE_FORMAT;
                    break;

                  default:
                    $format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
                    break;
                }
                $item['value'] = $this
                  ->dateFormatter
                  ->format($timestamp, 'custom', $format, 'UTC');
              }
              else {
                $item['value'] = $timestamp;
              }

              $field_values[$key] = $item;
            }
            else {
              unset($field_values[$key]);
            }
          }
        }

        if (!empty($field_values)) {
          $values[$id][$field_name][LanguageInterface::LANGCODE_DEFAULT] = $field_values;
        }
      }
    }

    $entities = [];
    foreach ($values as $id => $entity_values) {
        if(is_array($entities[$id])) {
      // Allow other modules to perform custom mapping logic.
      $event = new DspaceEntityMapRawDataEvent($data[$id], $entity_values);
      $this->eventDispatcher->dispatch(DspaceEntitiesEvents::MAP_RAW_DATA, $event);
      
        $entities[$id] = new $this->entityClass($event->getEntityValues(), $this->entityTypeId);
        $entities[$id]->enforceIsNew(FALSE);
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadMultipleRevisionsFieldItems($revision_ids) {
    return [];
  }
  
  /**
   * {@inheritdoc}
   */
  protected function setPersistentCache($entities) {
    if (!$this->entityType->isPersistentlyCacheable()) {
      return;
    }

    $cache_tags = [
      $this->entityTypeId . '_values',
      'entity_field_info',
    ];

    foreach ($entities as $id => $entity) {
      $max_age = $this->getDspaceEntityType()->getPersistentCacheMaxAge();
      $expire = $max_age === Cache::PERMANENT
        ? Cache::PERMANENT
        : $this->time->getRequestTime() + $max_age;
      $this->cacheBackend->set($this->buildCacheId($id), $entity, $expire, $cache_tags);
    }
  }

  /**
   * Acts on an entity before the presave hook is invoked.
   *
   * Used before the entity is saved and before invoking the presave hook.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @throws EntityStorageException
   */
  public function preSave(EntityInterface $entity) {
    $dspace_entity_type = $this->getDspaceEntityType();
    if ($dspace_entity_type->isReadOnly() && !$dspace_entity_type->isAnnotatable()) {
      throw new EntityStorageException($this->t('Can not save read-only dspace entities.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    /* @var \Drupal\drupal_dspace\DspaceEntityInterface $entity */
    $result = FALSE;

    $dspace_entity_type = $this->getDspaceEntityType();
    if (!$dspace_entity_type->isReadOnly()) {
      $result = parent::doSave($id, $entity);
    }

    if ($dspace_entity_type->isAnnotatable()) {
      $referenced_entities = $entity
        ->get(DspaceEntityInterface::ANNOTATION_FIELD)
        ->referencedEntities();
      if ($referenced_entities) {
        $annotation = array_shift($referenced_entities);

        $referenced_drupal_dspace = $annotation
          ->get($dspace_entity_type->getAnnotationFieldName())
          ->referencedEntities();
        $referenced_dspace_entity = array_shift($referenced_drupal_dspace);
        if (empty($referenced_dspace_entity)
          || $entity->getEntityTypeId() !== $referenced_dspace_entity->getEntityTypeId()
          || $entity->id() !== $referenced_dspace_entity->id()) {
          $annotation->set($dspace_entity_type->getAnnotationFieldName(), $entity->id());
          $annotation->{drupal_dspace_BYPASS_ANNOTATED_dspace_entity_SAVE_PROPERTY} = TRUE;
          $annotation->save();
        }
      }
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() {
    return 'entity.query.dspace';
  }

  /**
   * {@inheritdoc}
   */
  protected function has($id, EntityInterface $entity) {
    return !$entity->isNew();
  }

  /**
   * {@inheritdoc}
   */
  protected function doDeleteFieldItems($entities) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doDeleteRevisionFieldItems(ContentEntityInterface $revision) {
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultipleRevisions(array $revision_ids) {
    return $this->doLoadMultiple($revision_ids);
  }

  /**
   * {@inheritdoc}
   */
  protected function doLoadRevisionFieldItems($revision_id) {
  }

  /**
   * {@inheritdoc}
   */
  protected function doSaveFieldItems(ContentEntityInterface $entity, array $names = []) {
    if (!empty($entity->{DspaceEntityStorageInterface::BYPASS_STORAGE_CLIENT_SAVE_PROPERTY})) {
      return;
    }

    $id = $this->getStorageClient()->save($entity);
    if ($id && $entity->isNew()) {
      $entity->{$this->idKey} = $id;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function readFieldItemsToPurge(FieldDefinitionInterface $field_definition, $batch_size) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function purgeFieldItems(ContentEntityInterface $entity, FieldDefinitionInterface $field_definition) {
  }

  /**
   * {@inheritdoc}
   */
  public function countFieldData($storage_definition, $as_bool = FALSE) {
    return $as_bool ? 0 : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasData() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDspaceEntityType() {
    return $this->entityTypeManager
      ->getStorage('dspace_entity_type')
      ->load($this->getEntityTypeId());
  }

}
