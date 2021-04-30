<?php

namespace Drupal\drupal_dspace\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\drupal_dspace\DspaceEntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the dspace_entity_type entity.
 *
 * @ConfigEntityType(
 *   id = "dspace_entity_type",
 *   label = @Translation("Dspace entity type"),
 *   handlers = {
 *     "list_builder" = "Drupal\drupal_dspace\DspaceEntityTypeListBuilder",
 *     "storage" = "Drupal\drupal_dspace\Config\Entity\ConfigEntityStorage",
 *     "form" = {
 *       "delete" = "Drupal\drupal_dspace\Form\DspaceEntityTypeDeleteForm",
 *     }
 *   },
 *   config_prefix = "dspace_entity_type",
 *   admin_permission = "administer Dspace entity types",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *   }
 * )
 */
class DspaceEntityType extends ConfigEntityBase implements DspaceEntityTypeInterface {

  /**
   * Indicates that entities of this Dspace entity type should not be cached.
   */
  const CACHE_DISABLED = 0;

  /**
   * The Dspace entity type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the Dspace entity type.
   *
   * @var string
   */
  protected $label;

  /**
   * The plural human-readable name of the Dspace entity type.
   *
   * @var string
   */
  protected $label_plural;

  /**
   * The Dspace entity type description.
   *
   * @var string
   */
  protected $description;
  
  /**
   * The Dspace entity type prefix.
   *
   * @var string
   */
  protected $prefix;
  
  /**
   * The Dspace entity type namespace.
   *
   * @var string
   */
  protected $namespace;
  
 
  protected $langcode;
  
  /**
   * The Dspace entity type link.
   *
   * @var string
   */
  protected $link;
  
  /**
   * Whether or not entity types of this Dspace entity type are read only.
   *
   * @var bool
   */
  protected $read_only;

  /**
   * The field mappings for this Dspace entity type.
   *
   * @var array
   */
  protected $field_mappings;

  /**
   * The ID of the storage client plugin.
   *
   * @var string
   */
  protected $storage_client_id;

  /**
   * The storage client plugin configuration.
   *
   * @var array
   */
  protected $storage_client_config = [];

  /**
   * The storage client plugin instance.
   *
   * @var \Drupal\drupal_dspace\StorageClient\DspaceEntityStorageClientInterface
   */
  protected $storageClientPlugin;

  /**
   * Max age entities of this Dspace entity type may be persistently cached.
   *
   * @var int
   */
  protected $persistent_cache_max_age = self::CACHE_DISABLED;

  /**
   * The annotations entity type id.
   *
   * @var string
   */
  protected $annotation_entity_type_id;

  /**
   * The annotations bundle id.
   *
   * @var string
   */
  protected $annotation_bundle_id;

  /**
   * The field this Dspace entity is referenced from by the annotation entity.
   *
   * @var string
   */
  protected $annotation_field_name;

  /**
   * Local cache for the annotation field.
   *
   * @var array
   *
   * @see DspaceEntityType::getAnnotationField()
   */
  protected $annotationField;

  /**
   * Indicates if the Dspace entity inherits the annotation entity fields.
   *
   * @var bool
   */
  protected $inherits_annotation_fields = FALSE;

  
  
  
  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluralLabel() {
    return $this->label_plural;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
      
    return $this->description;
  }
  
  /**
   * {@inheritdoc}
   */
  public function getPrefix() {
    return $this->prefix;
  }

  /**
   * {@inheritdoc}
   */
  public function getNamespace() {
    return $this->namespace;
  }
  
  /**
   * {@inheritdoc}
   */
  public function getLink() {
    return $this->link;
  }
  
  /**
   * {@inheritdoc}
   */
  public function isReadOnly() {
    return $this->read_only;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMappings() {
    return $this->field_mappings;
  }
  
  public static function baseFieldDefinitions($entity_type_id) {
      $fields = [];
      
      $entity_type = \Drupal::entityTypeManager()
              ->getStorage('dspace_entity_type')
              ->load($entity_type_id);
//        ->getDefinition($entity_type_id);
     
      \EasyRdf\RdfNamespace::set($entity_type->getPrefix(), $entity_type->getNamespace());
      
      $namespace = $entity_type->getNamespace();
      $foaf = new \EasyRdf\Graph($namespace);
      $foaf->load();
//      $me = $foaf->primaryTopic();

      
      foreach($foaf->resources() as $namespace => $resource) {
          
          if($resource->isA('rdfs:Property')) {
//              $resource->load();
              $fields[$resource->shorten()] = BaseFieldDefinition::create("string")
                ->setLabel(t($resource->label()))
                ->setDescription(t($resource->getLiteral('rdfs:comment')->getValue()))
//                ->setSettings(["max_length" => 255, "text_processing" => 0])
//                ->setDefaultValue("")
//                ->setDisplayOptions("view", ["label" => "above", "type" => "string", "wegith" => -3])
//                ->setDisplayOptions("form", ["type" => "string_textfield", "wegith" => -3])
                      ;

          }
          
//          print($resource->localName() . ', ' . print_r($resource->types(), true)."\n");
      }
//   $fields['test'] = BaseFieldDefinition::create("string")
//                ->setLabel(t('test'))
//                ->setDescription(t('test'))
////                ->setSettings(["max_length" => 255, "text_processing" => 0])
////                ->setDefaultValue("")
////                ->setDisplayOptions("view", ["label" => "above", "type" => "string", "wegith" => -3])
////                ->setDisplayOptions("form", ["type" => "string_textfield", "wegith" => -3])
//                      ;
      return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMapping($field_name, $property_name = NULL) {
    if (!empty($this->field_mappings[$field_name])) {
      if ($property_name && !empty($this->field_mappings[$field_name][$property_name])) {
        return $this->field_mappings[$field_name][$property_name];
      }
      elseif (!$property_name) {
        return $this->field_mappings[$field_name];
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function hasValidStorageClient() {
    $storage_client_plugin_definition = \Drupal::service('plugin.manager.drupal_dspace.storage_client')->getDefinition($this->getStorageClientId(), FALSE);
    return !empty($storage_client_plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getStorageClientId() {
      return 'dspace_rest_storage_client';
    return $this->storage_client_id;
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
  public function setStorageClientConfig(array $storage_client_config) {
    $this->storage_client_config = $storage_client_config;
    $this->getStorageClient()->setConfiguration($storage_client_config);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPersistentCacheMaxAge() {
    return $this->persistent_cache_max_age;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Clear the entity type definitions cache so changes flow through to the
    // related entity types.
    $this->entityTypeManager()->clearCachedDefinitions();

    // Clear the router cache to prevent RouteNotFoundException errors caused
    // by the Field UI module.
    \Drupal::service('router.builder')->rebuild();

    // Rebuild local actions so that the 'Add field' action on the 'Manage
    // fields' tab appears.
    \Drupal::service('plugin.manager.menu.local_action')->clearCachedDefinitions();

    // Clear the static and persistent cache.
    $storage->resetCache();
    $edit_link = $this->toLink(t('Edit entity type'), 'edit-form')->toString();

    if ($update) {
      $this->logger($this->id())->notice(
        'Entity type %label has been updated.',
        ['%label' => $this->label(), 'link' => $edit_link]
      );
    }
    else {
      // Notify storage to create the database schema.
      $entity_type = $this->entityTypeManager()->getDefinition($this->id());
      \Drupal::service('entity_type.listener')
        ->onEntityTypeCreate($entity_type);

      $this->logger($this->id())->notice(
        'Entity type %label has been added.',
        ['%label' => $this->label(), 'link' => $edit_link]
      );
    }

  }

  /**
   * {@inheritdoc}
   */
  public function getDerivedEntityTypeId() {
    return $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivedEntityType() {
    return $this->entityTypeManager()->getDefinition($this->getDerivedEntityTypeId(), FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function isAnnotatable() {
    return $this->getAnnotationEntityTypeId()
      && $this->getAnnotationBundleId()
      && $this->getAnnotationFieldName();
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotationEntityTypeId() {
    return $this->annotation_entity_type_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotationBundleId() {
    return $this->annotation_bundle_id ?: $this->getAnnotationEntityTypeId();
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotationFieldName() {
    return $this->annotation_field_name;
  }

  /**
   * Returns the entity field manager.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   The entity field manager.
   */
  protected function entityFieldManager() {
    return \Drupal::service('entity_field.manager');
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotationField() {
    if (!isset($this->annotationField) && $this->isAnnotatable()) {
      $field_definitions = $this->entityFieldManager()->getFieldDefinitions($this->getAnnotationEntityTypeId(), $this->getAnnotationBundleId());
      $annotation_field_name = $this->getAnnotationFieldName();
      if (!empty($field_definitions[$annotation_field_name])) {
        $this->annotationField = $field_definitions[$annotation_field_name];
      }
    }

    return $this->annotationField;
  }

  /**
   * {@inheritdoc}
   */
  public function inheritsAnnotationFields() {
    return (bool) $this->inherits_annotation_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getBasePath() {
    return str_replace('_', '-', strtolower($this->id));
  }

  /**
   * Gets the logger for a specific channel.
   *
   * @param string $channel
   *   The name of the channel.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger for this channel.
   */
  protected function logger($channel) {
    return \Drupal::getContainer()->get('logger.factory')->get($channel);
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    // Prevent some properties from being serialized.
    return array_diff(parent::__sleep(), [
      'storageClientPlugin',
      'annotationField',
    ]);
  }

}
