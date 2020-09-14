<?php
/**
 * @file
 * Contains \Drupal\drupal_dspace\Controller\DefaultController.
 */
namespace Drupal\drupal_dspace\Controller;
class DefaultController {
  public function content() {
    // temporary hello world before we add the settings we need to link DSpace
    return array(
      '#type' => 'markup',
      '#markup' => t('Hello, World!'),
    );
  }
}
