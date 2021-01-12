<?php

namespace Drupal\nylotto_data_import\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\nylotto_data_import\ImportData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {@inheritdoc}
 *
 * @QueueWorker(
 *   id = "ny_data_queue",
 *   title = @Translation("Cron Data Importer"),
 *   cron = {"time" = 10}
 * )
 */
class NYDataImport extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $importer;

  /**
   * Creates a new NodePublishBase object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   The node storage.
   */

  /**
   * {@inheritdoc}
   *
   * @var Drupal\nylotto_data_import\ImportData
   */
  public function __construct(ImportData $import_data) {
    $this->importer = $import_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('nylotto.data')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {

    $pluginId = isset($data->pluginId) ? $data->pluginId : $data->plugin_id;
    if ($pluginId) {
        $this->importer->processRow($pluginId, $data);
    }
    else {
      error_log("No Plugin id set. " . print_r($data, true));
    }
  }

}
