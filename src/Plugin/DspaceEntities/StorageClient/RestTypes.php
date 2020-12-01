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
 *   description = @Translation("Retrieves dspace entity types from a REST API.")
 * )
 */
class RestTypes extends Rest  {
  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'endpoint' => "http://api.dspace.poc.euraknos.cf/server/api/core/metadataschemas?size=1000",
//      'endpoint' => "http://api.dspace.poc.euraknos.cf/server/api/core/collection",
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
        'headers' => array_merge($this->getHttpHeaders(), $this->authenticate()),
        'query' => $this->getSingleQueryParameters($id),
      ]
    );
    $body = $response->getBody();

    $decoded = json_decode($body,true);
    $data = $decoded['_embedded']['metadataschemas'];
    if(isset($decoded['page']) && isset($decoded['page']['totalPages'])) {
        for($i=1; $i<$decoded['page']['totalPages']; $i++) {
            $response = $this->httpClient->request(
                'GET',
                $this->configuration['endpoint'],
                [
//                  'headers' => array_merge($this->getHttpHeaders(), $this->authenticate()),
                  'headers' => array_merge($this->getHttpHeaders(), ['Authorization' => $this->authenticate()]),
                  'query' => $this->getSingleQueryParameters(['page' => $i]),
                ]
              );
              $body = $response->getBody();
            $data=$data+json_decode($body,true)['_embedded']['metadataschemas'];
        }
    }
    
    
//    if(is_array($ids)) {
//        array_filter(
//                $data, 
//                function($item) use ($ids) {
//                    return in_array(substr($item['id'],0,8),$ids)?true:false;
//                }
//            );
//    }
    return $data;
    
    return json_decode($body,true)['_embedded']['metadataschemas'];
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
  
  
//  public function authenticate() {
//      $response = $this->httpClient->request(
//      'POST',
//      'http://api.dspace.poc.euraknos.cf/server/api/authn/login',
//      [
//        'headers' => $this->getHttpHeaders(),
//        'form_params' => ['user'=>'admin@euraknos.cf','password'=>'euraknos4567'],
//      ]
//    );
//      return $response->getHeaders()['Authorization'][0];
//      //$this->setHttpHeaders(array_merge($this->getHttpHeaders(),$response->getHeaders()['Authorization']));
//      $bearer = $response->getHeaders()['Authorization'][0];
//      return ['Authorization' => $bearer];
//    $body = $response->getBody();
//    return json_decode($body,true)['_embedded']['collections'];
//    return $this
//      ->getResponseDecoderFactory()
//      ->getDecoder($this->configuration['response_format'])
//      ->decode($body);
//  }
}
