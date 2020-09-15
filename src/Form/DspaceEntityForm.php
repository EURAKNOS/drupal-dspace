<?php

namespace Drupal\drupal_dspace\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\drupal_dspace\DspaceEntityInterface;

/**
 * Form handler for the Dspace entity create/edit forms.
 *
 * @internal
 */
class DspaceEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\drupal_dspace\DspaceEntityInterface $dspace_entity */
    $dspace_entity = $this->entity;

    if ($this->operation == 'edit') {
      $form['#title'] = $this->t('<em>Edit @type</em> @title', [
        '@type' => $dspace_entity->getDspaceEntityType()->label(),
        '@title' => $dspace_entity->label(),
      ]);
    }

    if (!empty($form[DspaceEntityInterface::ANNOTATION_FIELD]['widget'][0]['inline_entity_form'])) {
      $form['#ief_element_submit'][] = [$this, 'markBypassAnnotatedDspaceEntitySave'];
      $original_annotation = $form_state->get('original_annotation');
      if (!$original_annotation) {
        $form_state->set('original_annotation', clone $dspace_entity->getAnnotation());
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\drupal_dspace\DspaceEntityInterface $dspace_entity */
    $dspace_entity = $this->entity;

    // When saving an Dspace entity with annotation through an inline entity
    // form, and because of how annotation fields are inherited, the original
    // Dspace entity object already contains the new annotation values, while
    // we expect it to be the previous ones. We manually set the correct
    // original values back again.
    // @see drupal_dspace_entity_insert()
    // @see drupal_dspace_entity_update()
    // @see _drupal_dspace_save_annotated_dspace_entity()
    if (!empty($form[DspaceEntityInterface::ANNOTATION_FIELD]['widget'][0]['inline_entity_form'])) {
      // The annotation has already been saved through the inline entity form.
      // Let's remap the annotation fields to make sure the most recent values
      // are mapped.
      $dspace_entity->mapAnnotationFields();

      // If an original Dspace entity exists, we remap the annotation fields
      // with the values of the original annotation.
      if (!empty($dspace_entity->original)) {
        $dspace_entity->original->mapAnnotationFields($form_state->get('original_annotation'));
      }
    }

    $insert = $dspace_entity->isNew();
    $dspace_entity->save();
    $dspace_entity_link = $dspace_entity->toLink($this->t('View'))->toString();
    $context = [
      '@type' => $dspace_entity->getEntityType()->getLabel(),
      '%title' => $dspace_entity->label(),
      'link' => $dspace_entity_link,
    ];
    $t_args = [
      '@type' => $dspace_entity->getEntityType()->getLabel(),
      '%title' => $dspace_entity->toLink($dspace_entity->label())->toString(),
    ];

    if ($insert) {
      $this->logger('content')->notice('@type: added %title.', $context);
      $this->messenger()->addStatus($this->t('@type %title has been created.', $t_args));
    }
    else {
      $this->logger('content')->notice('@type: updated %title.', $context);
      $this->messenger()->addStatus($this->t('@type %title has been updated.', $t_args));
    }

    if ($dspace_entity->id()) {
      if ($dspace_entity->access('view')) {
        $form_state->setRedirect(
          'entity.' . $dspace_entity->getEntityTypeId() . '.canonical',
          [$dspace_entity->getEntityTypeId() => $dspace_entity->id()]
        );
      }
      else {
        $form_state->setRedirect('<front>');
      }
    }
    else {
      // In the unlikely case something went wrong on save, the Dspace entity
      // will be rebuilt and Dspace entity form redisplayed.
      $this->messenger()->addError($this->t('The @type could not be saved.'), [
        '@type' => $dspace_entity->getEntityType()->getSingularLabel(),
      ]);
      $form_state->setRebuild();
    }
  }

  /**
   * Mark the annotations so that saving them won't save its Dspace entity.
   *
   * If the annotation entity form is embedded as an inline entity form, the
   * annotation will be saved before the Dspace entity is saved.
   * In _drupal_dspace_save_annotated_dspace_entity() the annotated
   * Dspace entity is automatically saved when its annotation is saved.
   * This means that that the Dspace entity will be saved twice:
   * - first it will be saved when the annotation is saved
   * - secondly it will be saved in the submit handler of the Dspace entity
   *   form
   * To prevent this from happening we mark the annotation so that the annotated
   * Dspace entity save doesn't happen on annotation save.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see drupal_dspace_entity_insert()
   * @see drupal_dspace_entity_update()
   * @see _drupal_dspace_save_annotated_dspace_entity()
   */
  public static function markBypassAnnotatedDspaceEntitySave(array $form, FormStateInterface $form_state) {
    foreach ($form_state->get('inline_entity_form') as &$widget_state) {
      if (empty($widget_state['instance'])) {
        continue;
      }

      /** @var \Drupal\Core\Field\BaseFieldDefinition $field */
      $field = $widget_state['instance'];
      if ($field->getName() !== 'annotation') {
        continue;
      }

      $widget_state += ['entities' => [], 'delete' => []];
      foreach ($widget_state['entities'] as $entity_item) {
        if (!empty($entity_item['entity'])) {
          $entity_item['entity']->{drupal_dspace_BYPASS_ANNOTATED_dspace_entity_SAVE_PROPERTY} = TRUE;
        }
      }

      foreach ($widget_state['delete'] as $entity) {
        $entity->{drupal_dspace_BYPASS_ANNOTATED_dspace_entity_SAVE_PROPERTY} = TRUE;
      };
    }
  }

}
