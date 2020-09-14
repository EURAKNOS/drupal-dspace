<?php

namespace Drupal\drupal_dspace;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\drupal_dspace\Entity\DspaceEntityType;

/**
 * Defines a class containing permission callbacks.
 */
class DspaceEntityPermissions {

  use StringTranslationTrait;

  /**
   * Gets an array of Dspace entity type permissions.
   *
   * @return array
   *   The Dspace entity type permissions.
   */
  public function DspaceEntityTypePermissions() {
    $permissions = [];

    // Generate permissions for all Dspace entity types.
    foreach (DspaceEntityType::loadMultiple() as $dspace_entity_type) {
      $permissions += $this->buildPermissions($dspace_entity_type);
    }

    return $permissions;
  }

  /**
   * Builds a standard list of Dspace entity permissions for a given type.
   *
   * @param \namespace Drupal\drupal_dspace\DspaceEntityTypeInterface $dspace_entity_type
   *   The Dspace entity type.
   *
   * @return array
   *   An array of permission names and descriptions.
   */
  protected function buildPermissions(DspaceEntityTypeInterface $dspace_entity_type) {
    $id = $dspace_entity_type->id();
    $t_params = ['%type_name' => $dspace_entity_type->label()];

    return [
      "view {$id} Dspace entity" => [
        'title' => $this->t('%type_name: View any Dspace entity', $t_params),
      ],
      "view {$id} Dspace entity collection" => [
        'title' => $this->t('%type_name: View Dspace entity listing', $t_params),
      ],
      "create {$id} Dspace entity" => [
        'title' => $this->t('%type_name: Create new Dspace entity', $t_params),
      ],
      "update {$id} Dspace entity" => [
        'title' => $this->t('%type_name: Edit any Dspace entity', $t_params),
      ],
      "delete {$id} Dspace entity" => [
        'title' => $this->t('%type_name: Delete any Dspace entity', $t_params),
      ],
    ];
  }

}
