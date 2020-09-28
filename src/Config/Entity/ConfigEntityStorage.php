<?php

namespace Drupal\drupal_dspace\Config\Entity;

use Drupal\Core\Entity\EntityStorageBase;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
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
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\drupal_dspace\Entity\DspaceEntityType;


/**
 * Defines the storage handler class for dspace entity types.
 *
 * This extends the base storage class, adding required special handling for
 * entity types
 */
class ConfigEntityStorage 
//extends EntityStorageBase 
extends \Drupal\Core\Config\Entity\ConfigEntityStorage
implements DspaceConfigEntityStorageInterface 
{
  /**
   * Length limit of the configuration entity ID.
   *
   * Most file systems limit a file name's length to 255 characters, so
   * ConfigBase::MAX_NAME_LENGTH restricts the full configuration object name
   * to 250 characters (leaving 5 for the file extension). The config prefix
   * is limited by ConfigEntityType::PREFIX_LENGTH to 83 characters, so this
   * leaves 166 remaining characters for the configuration entity ID, with 1
   * additional character needed for the joining dot.
   *
   * @see \Drupal\Core\Config\ConfigBase::MAX_NAME_LENGTH
   * @see \Drupal\Core\Config\Entity\ConfigEntityType::PREFIX_LENGTH
   */
  const MAX_ID_LENGTH = 166;

  /**
   * {@inheritdoc}
   */
  protected $uuidKey = 'uuid';

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The config storage service.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

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
  
  protected $storage_client_id='dspace_rest_type_storage_client';
  
  /**
   * Static cache of entities, keyed first by entity ID, then by an extra key.
   *
   * The additional cache key is to maintain separate caches for different
   * states of config overrides.
   *
   * @var array
   * @see \Drupal\Core\Config\ConfigFactoryInterface::getCacheKeys().
   */
  protected $entities = array();

  /**
   * Determines if the underlying configuration is retrieved override free.
   *
   * @var bool
   */
  protected $overrideFree = FALSE;

  /**
   * Constructs a ConfigEntityStorage object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
   *   The UUID service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
   *   The memory cache backend.
   */

  public function __construct(EntityTypeInterface $entity_type, ConfigFactoryInterface $config_factory, UuidInterface $uuid_service, LanguageManagerInterface $language_manager, MemoryCacheInterface $memory_cache) {
    parent::__construct($entity_type, $config_factory, $uuid_service, $language_manager, $memory_cache);
  }

/**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('config.factory'),
      $container->get('uuid'),
      $container->get('language_manager'),
      $container->get('entity.memory_cache')
    );
  }

  
  /**
   * {@inheritdoc}
   */
  public function getStorageClient() {
    if (!$this->storageClientPlugin) {
      $storage_client_plugin_manager = \Drupal::service('plugin.manager.drupal_dspace.storage_client');
      
      $config = $this->getStorageClientConfig();
      $config['_dspace_entity_type'] = $this;
      if (!($this->storageClientPlugin = $storage_client_plugin_manager->createInstance($this->getStorageClientId(), $config))) {
        $storage_client_id = $this->getStorageClientId();
        $label = $this->label();
        throw new \Exception("The storage client with ID '$storage_client_id' could not be retrieved for server '$label'.");
      }
    }
    return $this->storageClientPlugin;
  }
  
  
  /**
   * {@inheritdoc}
   */
  public function getStorageClientConfig() {
    return $this->storage_client_config ?: [];
  }
  
  /**
   * {@inheritdoc}
   */
  public function getStorageClientId() {
    return $this->storage_client_id;
  }
  
//  /**
//   * {@inheritdoc}
//   */
//  public function getStorageClient() {
//    if (!$this->storageClient) {
//        
//      $this->storageClient = $this
//        ->getDspaceEntityType()
//        ->getStorageClient();
//    }
//    return $this->storageClient;
//  }
  
  /**
   * Gets entity types from the Dspace storage.
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
//      $ids = $this->cleanIds($ids);
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
   * Maps from storage data to entity objects, and attaches fields.
   *
   * @param array $data
   *   Associative array of storage results, keyed on the entity ID.
   *
   * @return \Drupal\drupal_dspace\DspaceEntityTypeInterface[]
   *   An array of entity objects implementing the DspaceEntityTypeInterface.
   */
  protected function mapFromRawStorageData(array $data) {
    if (!$data) {
      return [];
    }

//    $field_definitions = $this
//      ->entityFieldManager
//      ->getFieldDefinitions($this->getEntityTypeId(), $this->getEntityTypeId());
    $values = [];
    foreach ($data as $id => $raw_data) {
      $values[$id] = [
          'id' => substr($raw_data['id'],1,8),
          'label' => $raw_data['label'],
          'label_plural' => $raw_data['label'],
          'description' => $raw_data['label'],
          '$read_only' => true,
      ];

//      foreach ($this->getDspaceEntityType()->getFieldMappings() as $field_name => $properties) {
////        $field_definition = $field_definitions[$field_name];
//        $field_values = [];
//
//        foreach ($properties as $property_name => $mapped_key) {
//          // The plus (+) character at the beginning of a mapping key indicates
//          // the property doesn't have a mapping but a default value. We process
//          // default values after all the regular mappings have been processed.
//          if (strpos($mapped_key, '+') === 0) {
//            continue;
//          }
//
//          $exploded_mapped_key = explode('/', $mapped_key);
//          // The asterisk (*) character indicates that we are dealing with a
//          // multivalued field. We consider each individual field item to be in
//          // its separate array.
//          if (!empty($raw_data[$exploded_mapped_key[0]]) && isset($exploded_mapped_key[1]) && $exploded_mapped_key[1] === '*') {
//            $parents = array_slice($exploded_mapped_key, 2);
//            foreach (array_values($raw_data[$exploded_mapped_key[0]]) as $key => $value) {
//              $value = !is_array($value) ? [$value] : $value;
//              $field_values[$key][$property_name] = NestedArray::getValue($value, $parents);
//            }
//          }
//          else {
//            $property_values = NestedArray::getValue($raw_data, $exploded_mapped_key);
//            if (!is_array($property_values)) {
//              $property_values = [$property_values];
//            }
//
//            foreach (array_values($property_values) as $key => $property_value) {
//              $field_values[$key][$property_name] = $property_value;
//            }
//          }
//        }
//
//        // Process the default values.
//        foreach ($properties as $property_name => $mapped_key) {
//          if (strpos($mapped_key, '+') === 0) {
//            foreach (array_keys($field_values) as $key) {
//              $field_values[$key][$property_name] = substr($mapped_key, 1);
//            }
//          }
//        }
//
//        // Provide specific conversion for dates.
//        $date_fields = ['created', 'changed', 'datetime', 'timestamp'];
//        if (in_array($field_definition->getType(), $date_fields)) {
//          foreach ($field_values as $key => $item) {
//            if (!empty($item['value'])) {
//              $timestamp = !is_numeric($item['value'])
//                ? strtotime($item['value'])
//                : $item['value'];
//
//              if ($field_definition->getType() === 'datetime') {
//                switch ($field_definition->getSetting('datetime_type')) {
//                  case DateTimeItem::DATETIME_TYPE_DATE:
//                    $format = DateTimeItemInterface::DATE_STORAGE_FORMAT;
//                    break;
//
//                  default:
//                    $format = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
//                    break;
//                }
//                $item['value'] = $this
//                  ->dateFormatter
//                  ->format($timestamp, 'custom', $format, 'UTC');
//              }
//              else {
//                $item['value'] = $timestamp;
//              }
//
//              $field_values[$key] = $item;
//            }
//            else {
//              unset($field_values[$key]);
//            }
//          }
//        }
//
//        if (!empty($field_values)) {
//          $values[$id][$field_name][LanguageInterface::LANGCODE_DEFAULT] = $field_values;
//        }
//      }
    }

    $entities = [];
    foreach ($values as $id => $entity_values) {
      // Allow other modules to perform custom mapping logic.
//      $event = new DspaceEntityMapRawDataEvent($data[$id], $entity_values);
//      $this->eventDispatcher->dispatch(DspaceEntitiesEvents::MAP_RAW_DATA, $event);

//      $entities[$id] = new $this->entityClass($event->getEntityValues(), $this->entityTypeId);
//        $entity_values['storageClientPlugin']=$this->storageClient;
      $entities[$id] = new DspaceEntityType($entity_values, $this->entityTypeId);
      $entities[$id]->enforceIsNew(FALSE);
//      $entities[$id]->setStorageClientPlugin($this->getStorageClient());
    }

    return $entities;
  }
  
  
  
  
  
  /**
   * {@inheritdoc}
   */
  protected function doLoadMultiple(array $ids = NULL) {
    // Attempt to load entities from the persistent cache. This will remove IDs
    // that were loaded from $ids.
//    $entities_from_cache = $this->getFromPersistentCache($ids);

    // Load any remaining entities from the Dspace storage.
    if ($entities_from_storage = $this->getFromExternalStorage($ids)) {
//      $this->invokeStorageLoadHook($entities_from_storage);
//      $this->setPersistentCache($entities_from_storage);
    }

    $entities = 
//            $entities_from_cache + 
            $entities_from_storage;
    
    // Map annotation fields to annotatable dspace entities.
//    foreach ($entities as $dspace_entity) {
//      /* @var \Drupal\drupal_dspace\DspaceEntityInterface $dspace_entity */
//      if ($dspace_entity->getDspaceEntityType()->isAnnotatable()) {
//        $dspace_entity->mapAnnotationFields();
//      }
//    }
    return $entities;
  }
  
  
//  /**
//   * {@inheritdoc}
//   */
//  public function getStorageClient() {
//    if (!$this->storageClient) {
//      $this->storageClient = $this
//        ->getDspaceEntityType()
//        ->getStorageClient();
//    }
//    return $this->storageClient;
//  }
//
//
//  /**
//   * {@inheritdoc}
//   */
//  public function loadRevision($revision_id) {
//    return NULL;
//  }
//
//  /**
//   * {@inheritdoc}
//   */
//  public function deleteRevision($revision_id) {
//    return NULL;
//  }
//
//  /**
//   * Returns the prefix used to create the configuration name.
//   *
//   * The prefix consists of the config prefix from the entity type plus a dot
//   * for separating from the ID.
//   *
//   * @return string
//   *   The full configuration prefix, for example 'views.view.'.
//   */
//  protected function getPrefix() {
//    return $this->entityType
//      ->getConfigPrefix() . '.';
//  }
//
//  /**
//   * {@inheritdoc}
//   */
//  public static function getIDFromConfigName($config_name, $config_prefix) {
//    return substr($config_name, strlen($config_prefix . '.'));
//  }
//
  
  
  
  
  
  
  
  
  
  
  
  
  
//  /**
//   * {@inheritdoc}
//   */
//  protected function doLoadMultiple(array $ids = NULL) {
//    $prefix = $this
//      ->getPrefix();
//
//    // Get the names of the configuration entities we are going to load.
//    if ($ids === NULL) {
//      $names = $this->configFactory
//        ->listAll($prefix);
//    }
//    else {
//      $names = array();
//      foreach ($ids as $id) {
//
//        // Add the prefix to the ID to serve as the configuration object name.
//        $names[] = $prefix . $id;
//      }
//    }
//
//    // Load all of the configuration entities.
//
//    /** @var \Drupal\Core\Config\Config[] $configs */
//    $configs = [];
//    $records = [];
//    foreach ($this->configFactory
//      ->loadMultiple($names) as $config) {
//      $id = $config
//        ->get($this->idKey);
//      $records[$id] = $this->overrideFree ? $config
//        ->getOriginal(NULL, FALSE) : $config
//        ->get();
//      $configs[$id] = $config;
//    }
//    $entities = $this
//      ->mapFromStorageRecords($records, $configs);
//
//    // Config entities wrap config objects, and therefore they need to inherit
//    // the cacheability metadata of config objects (to ensure e.g. additional
//    // cacheability metadata added by config overrides is not lost).
//    foreach ($entities as $id => $entity) {
//
//      // But rather than simply inheriting all cacheability metadata of config
//      // objects, we need to make sure the self-referring cache tag that is
//      // present on Config objects is not added to the Config entity. It must be
//      // removed for 3 reasons:
//      // 1. When renaming/duplicating a Config entity, the cache tag of the
//      //    original config object would remain present, which would be wrong.
//      // 2. Some Config entities choose to not use the cache tag that the under-
//      //    lying Config object provides by default (For performance and
//      //    cacheability reasons it may not make sense to have a unique cache
//      //    tag for every Config entity. The DateFormat Config entity specifies
//      //    the 'rendered' cache tag for example, because A) date formats are
//      //    changed extremely rarely, so invalidating all render cache items is
//      //    fine, B) it means fewer cache tags per page.).
//      // 3. Fewer cache tags is better for performance.
//      $self_referring_cache_tag = [
//        'config:' . $configs[$id]
//          ->getName(),
//      ];
//      $config_cacheability = CacheableMetadata::createFromObject($configs[$id]);
//      $config_cacheability
//        ->setCacheTags(array_diff($config_cacheability
//        ->getCacheTags(), $self_referring_cache_tag));
//      $entity
//        ->addCacheableDependency($config_cacheability);
//    }
//    return $entities;
//  }

  
  
  
  
  
  
  
  
  
  
  
  
  
  
  /**
   * {@inheritdoc}
   */
  protected function doCreate(array $values) {

    // Set default language to current language if not provided.
    $values += array(
      $this->langcodeKey => $this->languageManager
        ->getCurrentLanguage()
        ->getId(),
    );
    $entity = new $this->entityClass($values, $this->entityTypeId);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function doDelete($entities) {
    foreach ($entities as $entity) {
      $this->configFactory
        ->getEditable($this
        ->getPrefix() . $entity
        ->id())
        ->delete();
    }
  }

  /**
   * Implements Drupal\Core\Entity\EntityStorageInterface::save().
   *
   * @throws EntityMalformedException
   *   When attempting to save a configuration entity that has no ID.
   */
  public function save(EntityInterface $entity) {

    // Configuration entity IDs are strings, and '0' is a valid ID.
    $id = $entity
      ->id();
    if ($id === NULL || $id === '') {
      throw new EntityMalformedException('The entity does not have an ID.');
    }

    // Check the configuration entity ID length.
    // @see \Drupal\Core\Config\Entity\ConfigEntityStorage::MAX_ID_LENGTH
    // @todo Consider moving this to a protected method on the parent class, and
    //   abstracting it for all entity types.
    if (strlen($entity
      ->get($this->idKey)) > self::MAX_ID_LENGTH) {
      throw new ConfigEntityIdLengthException("Configuration entity ID {$entity->get($this->idKey)} exceeds maximum allowed length of " . self::MAX_ID_LENGTH . " characters.");
    }
    return parent::save($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function doSave($id, EntityInterface $entity) {
    $is_new = $entity
      ->isNew();
    $prefix = $this
      ->getPrefix();
    $config_name = $prefix . $entity
      ->id();
    if ($id !== $entity
      ->id()) {

      // Renaming a config object needs to cater for:
      // - Storage needs to access the original object.
      // - The object needs to be renamed/copied in ConfigFactory and reloaded.
      // - All instances of the object need to be renamed.
      $this->configFactory
        ->rename($prefix . $id, $config_name);
    }
    $config = $this->configFactory
      ->getEditable($config_name);

    // Retrieve the desired properties and set them in config.
    $config
      ->setData($this
      ->mapToStorageRecord($entity));
    $config
      ->save($entity
      ->hasTrustedData());

    // Update the entity with the values stored in configuration. It is possible
    // that configuration schema has casted some of the values.
    if (!$entity
      ->hasTrustedData()) {
      $data = $this
        ->mapFromStorageRecords(array(
        $config
          ->get(),
      ));
      $updated_entity = current($data);
      foreach (array_keys($config
        ->get()) as $property) {
        $value = $updated_entity
          ->get($property);
        $entity
          ->set($property, $value);
      }
    }
    return $is_new ? SAVED_NEW : SAVED_UPDATED;
  }

  
  /**
   * {@inheritdoc}
   */
  protected function getQueryServiceName() {
    return 'entity.query.dspace.config';
  }

  
  
  
//  /**
//   * Maps from an entity object to the storage record.
//   *
//   * @param \Drupal\Core\Entity\EntityInterface $entity
//   *   The entity object.
//   *
//   * @return array
//   *   The record to store.
//   */
//  protected function mapToStorageRecord(EntityInterface $entity) {
//    return $entity
//      ->toArray();
//  }
//
//  /**
//   * {@inheritdoc}
//   */
//  protected function has($id, EntityInterface $entity) {
//    $prefix = $this
//      ->getPrefix();
//    $config = $this->configFactory
//      ->get($prefix . $id);
//    return !$config
//      ->isNew();
//  }
//
//  /**
//   * Gets entities from the static cache.
//   *
//   * @param array $ids
//   *   If not empty, return entities that match these IDs.
//   *
//   * @return \Drupal\Core\Entity\EntityInterface[]
//   *   Array of entities from the entity cache.
//   */
//  protected function getFromStaticCache(array $ids) {
//    $entities = array();
//
//    // Load any available entities from the internal cache.
//    if ($this->entityType
//      ->isStaticallyCacheable() && !empty($this->entities)) {
//      $config_overrides_key = $this->overrideFree ? '' : implode(':', $this->configFactory
//        ->getCacheKeys());
//      foreach ($ids as $id) {
//        if (!empty($this->entities[$id])) {
//          if (isset($this->entities[$id][$config_overrides_key])) {
//            $entities[$id] = $this->entities[$id][$config_overrides_key];
//          }
//        }
//      }
//    }
//    return $entities;
//  }
//
//  /**
//   * Stores entities in the static entity cache.
//   *
//   * @param \Drupal\Core\Entity\EntityInterface[] $entities
//   *   Entities to store in the cache.
//   */
//  protected function setStaticCache(array $entities) {
//    if ($this->entityType
//      ->isStaticallyCacheable()) {
//      $config_overrides_key = $this->overrideFree ? '' : implode(':', $this->configFactory
//        ->getCacheKeys());
//      foreach ($entities as $id => $entity) {
//        $this->entities[$id][$config_overrides_key] = $entity;
//      }
//    }
//  }
//
//  /**
//   * Invokes a hook on behalf of the entity.
//   *
//   * @param $hook
//   *   One of 'presave', 'insert', 'update', 'predelete', or 'delete'.
//   * @param $entity
//   *   The entity object.
//   */
//  protected function invokeHook($hook, EntityInterface $entity) {
//
//    // Invoke the hook.
//    $this->moduleHandler
//      ->invokeAll($this->entityTypeId . '_' . $hook, array(
//      $entity,
//    ));
//
//    // Invoke the respective entity-level hook.
//    $this->moduleHandler
//      ->invokeAll('entity_' . $hook, array(
//      $entity,
//      $this->entityTypeId,
//    ));
//  }
//
//  /**
//   * {@inheritdoc}
//   */
//  protected function getQueryServiceName() {
//    return 'entity.query.config';
//  }
//
//  /**
//   * {@inheritdoc}
//   */
//  public function importCreate($name, Config $new_config, Config $old_config) {
//    $entity = $this
//      ->_doCreateFromStorageRecord($new_config
//      ->get(), TRUE);
//    $entity
//      ->save();
//    return TRUE;
//  }
//
//  /**
//   * {@inheritdoc}
//   */
//  public function importUpdate($name, Config $new_config, Config $old_config) {
//    $id = static::getIDFromConfigName($name, $this->entityType
//      ->getConfigPrefix());
//    $entity = $this
//      ->load($id);
//    if (!$entity) {
//      throw new ConfigImporterException("Attempt to update non-existing entity '{$id}'.");
//    }
//    $entity
//      ->setSyncing(TRUE);
//    $entity = $this
//      ->updateFromStorageRecord($entity, $new_config
//      ->get());
//    $entity
//      ->save();
//    return TRUE;
//  }
//
//  /**
//   * {@inheritdoc}
//   */
//  public function importDelete($name, Config $new_config, Config $old_config) {
//    $id = static::getIDFromConfigName($name, $this->entityType
//      ->getConfigPrefix());
//    $entity = $this
//      ->load($id);
//    $entity
//      ->setSyncing(TRUE);
//    $entity
//      ->delete();
//    return TRUE;
//  }
//
//  /**
//   * {@inheritdoc}
//   */
//  public function importRename($old_name, Config $new_config, Config $old_config) {
//    return $this
//      ->importUpdate($old_name, $new_config, $old_config);
//  }
//
//  /**
//   * {@inheritdoc}
//   */
//  public function createFromStorageRecord(array $values) {
//    return $this
//      ->_doCreateFromStorageRecord($values);
//  }

  /**
   * Helps create a configuration entity from storage values.
   *
   * Allows the configuration entity storage to massage storage values before
   * creating an entity.
   *
   * @param array $values
   *   The array of values from the configuration storage.
   * @param bool $is_syncing
   *   Is the configuration entity being created as part of a config sync.
   *
   * @return ConfigEntityInterface
   *   The configuration entity.
   *
   * @see \Drupal\Core\Config\Entity\ConfigEntityStorageInterface::createFromStorageRecord()
   * @see \Drupal\Core\Config\Entity\ImportableEntityStorageInterface::importCreate()
   */
  protected function _doCreateFromStorageRecord(array $values, $is_syncing = FALSE) {
    // Assign a new UUID if there is none yet.
    if ($this->uuidKey && $this->uuidService && !isset($values[$this->uuidKey])) {
      $values[$this->uuidKey] = $this->uuidService
        ->generate();
    }
    $data = $this
      ->mapFromStorageRecords(array(
      $values,
    ));
    $entity = current($data);
    $entity->original = clone $entity;
    $entity
      ->setSyncing($is_syncing);
    $entity
      ->enforceIsNew();
    $entity
      ->postCreate($this);

    // Modules might need to add or change the data initially held by the new
    // entity object, for instance to fill-in default values.
    $this
      ->invokeHook('create', $entity);
    return $entity;
  }

//  /**
//   * {@inheritdoc}
//   */
//  public function updateFromStorageRecord(ConfigEntityInterface $entity, array $values) {
//    $entity->original = clone $entity;
//    $data = $this
//      ->mapFromStorageRecords(array(
//      $values,
//    ));
//    $updated_entity = current($data);
//    foreach (array_keys($values) as $property) {
//      $value = $updated_entity
//        ->get($property);
//      $entity
//        ->set($property, $value);
//    }
//    return $entity;
//  }
//
//  /**
//   * {@inheritdoc}
//   */
//  public function loadOverrideFree($id) {
//    $entities = $this
//      ->loadMultipleOverrideFree([
//      $id,
//    ]);
//    return isset($entities[$id]) ? $entities[$id] : NULL;
//  }
//
//  /**
//   * {@inheritdoc}
//   */
//  public function loadMultipleOverrideFree(array $ids = NULL) {
//    $this->overrideFree = TRUE;
//    $entities = $this
//      ->loadMultiple($ids);
//    $this->overrideFree = FALSE;
//    return $entities;
//  }
}
