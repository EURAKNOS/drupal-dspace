<?php

namespace Drupal\drupal_dspace\Entity;

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
 */
class DspaceEntity extends ContentEntityBase implements DspaceEntityInterface {

  /**
   * {@inheritdoc}
   */
  public function getDspaceEntityType() {
    return $this
      ->entityTypeManager()
      ->getStorage('dspace_entity_type')
      ->load($this->getEntityTypeId());
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    return self::defaultBaseFieldDefinitions();
  }

  /**
   * Provides the default base field definitions for dspace entities.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An array of base field definitions for the entity type, keyed by field
   *   name.
   */
  public static function defaultBaseFieldDefinitions() {
    $fields = [];

    $fields['id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('ID'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('UUID'))
      ->setReadOnly(TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Title'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'string',
        'label' => 'hidden',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $fields = parent::bundleFieldDefinitions($entity_type, $bundle, $base_field_definitions);

    /* @var \namespace Drupal\drupal_dspace\DspaceEntityTypeInterface $dspace_entity_type */
    $dspace_entity_type = \Drupal::entityTypeManager()
      ->getStorage('dspace_entity_type')
      ->load($entity_type->id());
    if ($dspace_entity_type && $dspace_entity_type->isAnnotatable()) {
      // Add the annotation reference field.
      $fields[DspaceEntityInterface::ANNOTATION_FIELD] = BaseFieldDefinition::create('entity_reference')
        ->setLabel(t('Annotation'))
        ->setDescription(t('The annotation entity.'))
        ->setSetting('target_type', $dspace_entity_type->getAnnotationEntityTypeId())
        ->setSetting('handler', 'default')
        ->setSetting('handler_settings', [
          'target_bundles' => [$dspace_entity_type->getAnnotationBundleId()],
        ])
        ->setDisplayOptions('form', [
          'type' => 'entity_reference_autocomplete',
          'weight' => 5,
          'settings' => [
            'match_operator' => 'CONTAINS',
            'size' => '60',
            'placeholder' => '',
          ],
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayOptions('view', [
          'label' => t('Annotation'),
          'type' => 'entity_reference_label',
          'weight' => 0,
        ])
        ->setDisplayConfigurable('view', TRUE);

      // Have the Dspace entity inherit its annotation fields.
      if ($dspace_entity_type->inheritsAnnotationFields()) {
        $inherited_fields = static::getInheritedAnnotationFields($dspace_entity_type);
        $field_prefix = DspaceEntityInterface::ANNOTATION_FIELD_PREFIX;
        foreach ($inherited_fields as $field) {
          $field_definition = BaseFieldDefinition::createFromFieldStorageDefinition($field->getFieldStorageDefinition())
            ->setName($field_prefix . $field->getName())
            ->setReadOnly(TRUE)
            ->setComputed(TRUE)
            ->setLabel($field->getLabel())
            ->setDisplayConfigurable('view', $field->isDisplayConfigurable('view'));
          $fields[$field_prefix . $field->getName()] = $field_definition;
        }
      }
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function extractRawData() {
    $raw_data = [];

    foreach ($this->getDspaceEntityType()->getFieldMappings() as $field_name => $properties) {
      $field_values = $this->get($field_name)->getValue();
      $field_cardinality = $this
        ->getFieldDefinition($field_name)
        ->getFieldStorageDefinition()
        ->getCardinality();

      if (empty($field_values)) {
        foreach ($properties as $property) {
          $empty_value = $field_cardinality > 1 ? [] : NULL;
          $exploded_property = explode('/', $property);
          NestedArray::setValue($raw_data, $exploded_property, $empty_value);
        }
      }
      else {
        foreach ($field_values as $key => $field_value) {
          foreach ($properties as $property_name => $mapped_key) {
            // The plus (+) character at the beginning of a mapping key
            // indicates the property doesn't have a mapping but a default
            // value, so we skip these.
            if (strpos($mapped_key, '+') === 0) {
              continue;
            }

            if (!empty($field_value[$property_name])) {
              $exploded_mapped_key = explode('/', $mapped_key);
              // If field cardinality is more than 1, we consider the field
              // value to be a separate array.
              if ($field_cardinality !== 1) {
                $exploded_mapped_key[1] = $key;
              }

              // TODO: What about dates and their original format?
              NestedArray::setValue($raw_data, $exploded_mapped_key, $field_value[$property_name]);
            }
          }
        }
      }
    }

    // Allow other modules to perform custom extraction logic.
    $event = new DspaceEntityExtractRawDataEvent($this, $raw_data);
    \Drupal::service('event_dispatcher')->dispatch(DspaceEntitiesEvents::EXTRACT_RAW_DATA, $event);

    return $event->getRawData();
  }

  /**
   * {@inheritdoc}
   */
  public function getAnnotation() {
    $dspace_entity_type = $this->getDspaceEntityType();
    if ($dspace_entity_type->isAnnotatable()) {
      $properties = [
        $dspace_entity_type->getAnnotationFieldName() => $this->id(),
      ];

      $bundle_key = $this
        ->entityTypeManager()
        ->getDefinition($dspace_entity_type->getAnnotationEntityTypeId())
        ->getKey('bundle');
      if ($bundle_key) {
        $properties[$bundle_key] = $dspace_entity_type->getAnnotationBundleId();
      }

      $annotation = $this->entityTypeManager()
        ->getStorage($dspace_entity_type->getAnnotationEntityTypeId())
        ->loadByProperties($properties);
      if (!empty($annotation)) {
        return array_shift($annotation);
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function mapAnnotationFields(ContentEntityInterface $annotation = NULL) {
    $dspace_entity_type = $this->getDspaceEntityType();
    if ($dspace_entity_type->isAnnotatable()) {
      if (!$annotation) {
        $annotation = $this->getAnnotation();
      }

      if ($annotation) {
        $this->set(DspaceEntityInterface::ANNOTATION_FIELD, $annotation->id());
        if ($dspace_entity_type->inheritsAnnotationFields()) {
          $inherited_fields = static::getInheritedAnnotationFields($dspace_entity_type);
          $field_prefix = DspaceEntityInterface::ANNOTATION_FIELD_PREFIX;
          foreach ($inherited_fields as $field_name => $inherited_field) {
            $value = $annotation->get($field_name)->getValue();
            $this->set($field_prefix . $field_name, $value);
          }
        }
      }
    }

    return $this;
  }

  /**
   * Gets the fields that can be inherited by the Dspace entity.
   *
   * @param \namespace Drupal\drupal_dspace\DspaceEntityTypeInterface $type
   *   The type of the Dspace entity.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An array of field definitions, keyed by field name.
   *
   * @see \Drupal\Core\Entity\EntityManagerInterface::getFieldDefinitions()
   */
  public static function getInheritedAnnotationFields(DspaceEntityTypeInterface $type) {
    $inherited_fields = [];

    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($type->getAnnotationEntityTypeId(), $type->getAnnotationBundleId());
    foreach ($field_definitions as $field_name => $field_definition) {
      if ($field_name !== $type->getAnnotationFieldName()) {
        $inherited_fields[$field_name] = $field_definition;
      }
    }

    return $inherited_fields;
  }

}
