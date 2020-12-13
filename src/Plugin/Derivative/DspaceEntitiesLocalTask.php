<?php

namespace Drupal\drupal_dspace\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for dspace entities..
 */
class DspaceEntitiesLocalTask extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates an DspaceEntityTypeLocalTask object.
   *
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The translation manager.
   */
  public function __construct(RouteProviderInterface $route_provider, EntityTypeManagerInterface $entity_type_manager, TranslationInterface $string_translation) {
    $this->routeProvider = $route_provider;
    $this->entityTypeManager = $entity_type_manager;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('router.route_provider'),
      $container->get('entity_type.manager'),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    foreach ($this->getDspaceEntityTypes() as $entity_type_id => $entity_type) {
      // Dspace entity type edit tab.
//      $this->derivatives[$entity_type_id . '_settings_tab'] = [
//        'route_name' => 'entity.dspace_entity_type.' . $entity_type_id . '.edit_form',
//        'title' => $this->t('Edit'),
//        'base_route' => 'entity.dspace_entity_type.' . $entity_type_id . '.edit_form',
//      ];
//
//      // Dspace entity type delete tab.
//      $this->derivatives[$entity_type_id . '_delete_tab'] = [
//        'route_name' => 'entity.dspace_entity_type.' . $entity_type_id . '.delete_form',
//        'title' => $this->t('Delete'),
//        'base_route' => 'entity.dspace_entity_type.' . $entity_type_id . '.edit_form',
//        'weight' => 10,
//      ];

      // Dspace entity view tab.
      $this->derivatives['entity.' . $entity_type_id . '.canonical'] = [
        'route_name' => 'entity.' . $entity_type_id . '.canonical',
        'title' => $this->t('View'),
        'base_route' => 'entity.' . $entity_type_id . '.canonical',
      ];

      // Dspace entity edit tab.
//      if ($entity_type->getDerivedEntityType()->hasLinkTemplate('edit-form')) {
//        $this->derivatives['entity.' . $entity_type_id . '.edit_form'] = [
//          'route_name' => 'entity.' . $entity_type_id . '.edit_form',
//          'title' => $this->t('Edit'),
//          'base_route' => 'entity.' . $entity_type_id . '.canonical',
//        ];
//      }
//
//      // Dspace entity delete tab.
//      if ($entity_type->getDerivedEntityType()->hasLinkTemplate('delete-form')) {
//        $this->derivatives['entity.' . $entity_type_id . '.delete_form'] = [
//          'route_name' => 'entity.' . $entity_type_id . '.delete_form',
//          'title' => $this->t('Delete'),
//          'base_route' => 'entity.' . $entity_type_id . '.canonical',
//        ];
//      }
    }

    foreach ($this->derivatives as &$entry) {
      $entry += $base_plugin_definition;
    }

    return $this->derivatives;
  }

  /**
   * Gets all defined Dspace entity types.
   *
   * @return \Drupal\drupal_dspace\DspaceEntityTypeInterface[]
   *   All defined Dspace entity types.
   */
  protected function getDspaceEntityTypes() {
    return $this->entityTypeManager
      ->getStorage('dspace_entity_type')
      ->loadMultiple();
  }

}
