<?php

namespace Drupal\drupal_dspace\StorageClient;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginDependencyTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\drupal_dspace\DspaceEntityTypeInterface;
use Drupal\drupal_dspace\ResponseDecoder\ResponseDecoderFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Dspace entity storage clients.
 */
abstract class DspaceEntityStorageClientBase extends PluginBase implements DspaceEntityStorageClientInterface {

  // Normally, we'd just need \Drupal\Core\Entity\DependencyTrait here for
  // plugins. However, in a few cases, plugins use plugins themselves, and then
  // the additional calculatePluginDependencies() method from this trait is
  // useful. Since PHP 5 complains when adding this trait along with its
  // "parent" trait to the same class, we just add it here in case a child class
  // does need it.
  use PluginDependencyTrait;

  /**
   * The Dspace entity type this storage client is configured for.
   *
   * @var \Drupal\drupal_dspace\DspaceEntityTypeInterface
   */
  protected $DspaceEntityType;

  /**
   * The response decoder factory.
   *
   * @var \Drupal\drupal_dspace\ResponseDecoder\ResponseDecoderFactoryInterface
   */
  protected $responseDecoderFactory;

  /**
   * Constructs a DspaceEntityStorageClientBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\drupal_dspace\ResponseDecoder\ResponseDecoderFactoryInterface $response_decoder_factory
   *   The response decoder factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TranslationInterface $string_translation, ResponseDecoderFactoryInterface $response_decoder_factory) {
      $configuration += $this->defaultConfiguration();
    if (!empty($configuration['_dspace_entity_type']) && $configuration['_dspace_entity_type'] instanceof DspaceEntityTypeInterface) {
      $this->DspaceEntityType = $configuration['_dspace_entity_type'];
      unset($configuration['_dspace_entity_type']);
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setStringTranslation($string_translation);
    $this->responseDecoderFactory = $response_decoder_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('drupal_dspace.response_decoder_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $plugin_definition = $this->getPluginDefinition();
    return $plugin_definition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $plugin_definition = $this->getPluginDefinition();
    return isset($plugin_definition['description']) ? $plugin_definition['description'] : '';
  }

  /**
   * Returns the response decoder factory.
   *
   * @return \Drupal\drupal_dspace\ResponseDecoder\ResponseDecoderFactoryInterface
   *   The response decoder factory.
   */
  public function getResponseDecoderFactory() {
    return $this->responseDecoderFactory;
  }

  /**
   * Sets the response decoder factory.
   *
   * @param \Drupal\drupal_dspace\ResponseDecoder\ResponseDecoderFactoryInterface $response_decoder_factory
   *   A response decoder factory.
   *
   * @return $this
   */
  public function setResponseDecoderFactory(ResponseDecoderFactoryInterface $response_decoder_factory) {
    $this->responseDecoderFactory = $response_decoder_factory;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($configuration, $this->defaultConfiguration());
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function countQuery(array $parameters = []) {
    return count($this->query($parameters));
  }

}
