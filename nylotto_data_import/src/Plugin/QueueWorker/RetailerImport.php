<?php

namespace Drupal\nylotto_data_import\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Save queue item in a node.
 *
 * To process the queue items whenever Cron is run,
 * we need a QueueWorker plugin with an annotation witch defines
 * to witch queue it applied.
 *
 * @QueueWorker(
 *   id = "exqueue_import",
 *   title = @Translation("Import Retailer content"),
 *   cron = {"time" = 10}
 * )
 */
class RetailerImport extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */

  private $entityTypeManager;
  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */

  private $loggerChannelFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration,
                              $pluginId,
                              $plugin_definition,
                              EntityTypeManagerInterface $entityTypeManager,
                              LoggerChannelFactoryInterface $loggerChannelFactory) {
    parent::__construct($configuration, $pluginId, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerChannelFactory = $loggerChannelFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $plugin_definition) {
    return new static(
      $configuration,
      $pluginId,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {
    try {
      // Check if we have a title.
      if (!isset($item->name)) {
        return;
      }
      $ids = \Drupal::entityQuery('node')
        ->condition('type', 'retailer')
        ->condition('field_internal_id', $item->internalid)
        ->execute();
      if (!empty($ids)) {
        $node = entity_load('node', array_shift($ids));
      }

      if (empty($node)) {
        $storage = $this->entityTypeManager->getStorage('node');
        $node = $storage->create(
          [
            'type' => 'retailer',
            'title' => $item->name,
            'field_internal_id' => $item->internalid,
            'field_isqd' => ($item->isqd == '') ? 'n' : 'y',
            'field_street_address' => $item->street,
            'field_city' => $item->city,
            'field_state' => $item->state,
            'field_zipcode' => $item->zip,
          ]
        );
      }
      else {
        // Echo '<pre>'; print_r($node->id()); exit;.
        $node->id();
        $node->set('title', $item->name);
        $node->set('field_isqd', $item->isqd);
        $node->Set('field_street_address', $item->street);
        $node->set('field_city', $item->city);
        $node->set('field_state', $item->state);
        $node->set('field_zipcode', $item->zip);
      }

      $point = [
        'lat' => $item->longitude,
        'lon' => $item->latitude,
      ];
      $value = \Drupal::service('geofield.wkt_generator')->WktBuildPoint($point);
      $node->field_geofield->setValue([$value]);
      $changed = $node->getChangedTime();
      $node->setNewRevision(FALSE);
      $node->setChangedTime($changed);
      $node->setRevisionLogMessage('Data Feed Import for ' . $item->internalid);
      $node->setRevisionUserId(1);
      $node->save();
    }
    catch (\Exception $e) {
      watchdog_exception('RetailerImport', $e);
    }
  }

}
