<?php

namespace Drupal\drupal_dspace\Entity\Query\External;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryBase;
use Drupal\Core\Entity\Query\QueryInterface;

/**
 * The dspace entities storage entity query class.
 */
class Query extends QueryBase implements QueryInterface {

  /**
   * The parameters to send to the Dspace entity storage client.
   *
   * @var array
   */
  protected $parameters = [];

  /**
   * Stores the entity type manager used by the query.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Storage client instance.
   *
   * @var \namespace Drupal\drupal_dspace\StorageClient\DspaceEntityStorageClientInterface
   */
  protected $storageClient;

  /**
   * Constructs a query object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param string $conjunction
   *   - AND: all of the conditions on the query need to match.
   * @param array $namespaces
   *   List of potential namespaces of the classes belonging to this query.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeInterface $entity_type, $conjunction, array $namespaces, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type, $conjunction, $namespaces);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Implements \Drupal\Core\Entity\Query\QueryInterface::execute().
   */
  public function execute() {
    return $this
      ->compile()
      ->finish()
      ->result();
  }

  /**
   * Compiles the conditions.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   Returns the called object.
   */
  protected function compile() {
    $this->condition->compile($this);
    return $this;
  }

  /**
   * Finish the query by adding fields, GROUP BY and range.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   Returns the called object.
   */
  protected function finish() {
    $this->initializePager();
    return $this;
  }

  /**
   * Executes the query and returns the result.
   *
   * @return int|array
   *   Returns the query result as entity IDs.
   */
  protected function result() {
    if ($this->count) {
      return $this->getStorageClient()->countQuery($this->parameters);
    }

    $start = $this->range['start'] ?? NULL;
    $length = $this->range['length'] ?? NULL;
    $query_results = $this->getStorageClient()->query($this->parameters, $this->sort, $start, $length);
    $result = [];
    foreach ($query_results as $query_result) {
      $id = $query_result[$this->getDspaceEntityType()->getFieldMapping('id', 'value')];
      $result[$id] = $id;
    }

    return $result;
  }

  /**
   * Get the storage client for a bundle.
   *
   * @return \namespace Drupal\drupal_dspace\StorageClient\DspaceEntityStorageClientInterface
   *   The Dspace entity storage client.
   */
  protected function getStorageClient() {
    if (!$this->storageClient) {
      $this->storageClient = $this->getDspaceEntityType()->getStorageClient();

    }
    return $this->storageClient;
  }

  /**
   * Set a parameter.
   *
   * @param string $key
   *   The parameter key.
   * @param mixed $value
   *   The parameter value.
   * @param string|null $operator
   *   (optional) The parameter operator.
   */
  public function setParameter($key, $value, $operator = NULL) {
    $this->parameters[] = [
      'field' => $key,
      'value' => $value,
      'operator' => $operator,
    ];
  }

  /**
   * Gets the Dspace entity type.
   *
   * @return \namespace Drupal\drupal_dspace\DspaceEntityTypeInterface
   *   The Dspace entity type.
   */
  public function getDspaceEntityType() {
    return $this
      ->entityTypeManager
      ->getStorage('dspace_entity_type')
      ->load($this->getEntityTypeId());
  }

}
