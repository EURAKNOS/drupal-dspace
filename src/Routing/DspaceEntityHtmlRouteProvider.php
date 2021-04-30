<?php

namespace Drupal\drupal_dspace\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides HTML routes for dspace entities.
 *
 * This class provides the following routes for dspace entities, with title
 * and access callbacks:
 * - canonical
 * - add-page
 * - add-form
 * - edit-form
 * - delete-form
 * - collection.
 *
 * @see \Drupal\Core\Entity\Routing\AdminHtmlRouteProvider.
 */
class DspaceEntityHtmlRouteProvider extends DefaultHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    // DefaultHtmlRouteProvider::getCollection() specifies that the admin
    // permission is required for viewing collections. We implement a separate
    // permission for Dspace entity collection pages.
    if ($entity_type->hasLinkTemplate('collection') && $entity_type->hasListBuilderClass()) {
      /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $label */
      $label = $entity_type->getCollectionLabel();

      $route = new Route($entity_type->getLinkTemplate('collection'));
      $route
        ->addDefaults([
          '_entity_list' => $entity_type->id(),
          '_title' => $label->getUntranslatedString(),
          '_title_arguments' => $label->getArguments(),
          '_title_context' => $label->getOption('context'),
          '_entity' => $entity_type->id(),
        ])
        ->setRequirement('_permission', "view {$entity_type->id()} Dspace entity collection");
        
      return $route;
    }

    return NULL;
  }
  
//  protected function getCanonicalRoute(EntityTypeInterface $entity_type) {
//      $result = parent::getCanonicalRoute($entity_type);
//      $options = $result->getOptions();
//      $options['parameters'][$entity_type->id()]['entity']=$entity_type->id();
//      $result->setOptions($options);
//      $result->entity=$entity_type;
//      return $result;
//  }

}
