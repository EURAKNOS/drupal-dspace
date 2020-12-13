<?php

namespace Drupal\drupal_dspace;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the serialization services.
 */
class DspaceEntitiesServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $container->getDefinition('serialization.json')->addTag('dspace_entity_response_decoder');
    $container->getDefinition('serialization.phpserialize')->addTag('dspace_entity_response_decoder');
    $container->getDefinition('serialization.yaml')->addTag('dspace_entity_response_decoder');
  }

}
