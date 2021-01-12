<?php

namespace Drupal\nylotto_data_import;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides the Data Import plugin for ny lotto.
 *
 * @see plugin_api
 */
class DataImportManager extends DefaultPluginManager
{

  /**
   * {@inheritdoc}
   */
    public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler)
    {
        parent::__construct(
            'Plugin/NYLotto/Data',
            $namespaces,
            $module_handler,
            'Drupal\nylotto_data_import\Plugin\NYLotto\Data\DataInterface',
            'Drupal\nylotto_data_import\Annotation\NyDataType'
        );

        $this->alterInfo('nydata_type');
        $this->setCacheBackend($cache_backend, 'nydata_type_plugins');
        $this->factory = new DefaultFactory($this->getDiscovery());
    }
}
