<?php

namespace Drupal\drupal_dspace\Plugin\DspaceEntities\StorageClient;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\drupal_dspace\DspaceEntityInterface;
use Drupal\drupal_dspace\Plugin\PluginFormTrait;
use Drupal\drupal_dspace\ResponseDecoder\ResponseDecoderFactoryInterface;
use Drupal\drupal_dspace\StorageClient\DspaceEntityStorageClientBase;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * dspace entities storage client based on a REST API.
 *
 * @DspaceEntityStorageClient(
 *   id = "dspace_rest_type_storage_client",
 *   label = @Translation("REST"),
 *   description = @Translation("Retrieves dspace entities from a REST API.")
 * )
 */
class RestTypes extends Rest  {
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'endpoint' => "http://api.dspace.poc.euraknos.cf/server/api/core/collection",
      'response_format' => 'Json',
      'pager' => [
        'default_limit' => 20,
        'page_parameter' => NULL,
        'page_parameter_type' => NULL,
        'page_size_parameter' => NULL,
        'page_size_parameter_type' => NULL,
      ],
      'api_key' => [
        'header_name' => NULL,
        'key' => NULL,
      ],
      'parameters' => [
        'list' => NULL,
        'single' => NULL,
      ],
    ];
  }
    
  
  
   /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = NULL) {
    $data = [];

    $response = $this->httpClient->request(
      'GET',
      $this->configuration['endpoint'],
      [
        'headers' => $this->getHttpHeaders(),
        'query' => $this->getSingleQueryParameters($id),
      ]
    );

    $body = $response->getBody();
    return json_decode($body,true)['_embedded']['collections'];
    return $this
      ->getResponseDecoderFactory()
      ->getDecoder($this->configuration['response_format'])
      ->decode($body);
    
    
    
//    if (!empty($ids) && is_array($ids)) {
//      foreach ($ids as $id) {
//        $data[$id] = $this->load($id);
//      }
//    }
//
//    return $data;
  }
}
