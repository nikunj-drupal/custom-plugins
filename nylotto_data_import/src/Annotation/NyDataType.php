<?php

namespace Drupal\nylotto_data_import\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines the data import plugin used for importing lotto information.
 *
 * @see plugin_api
 *
 * @Annotation
 */
class NyDataType extends Plugin
{

  /**
   * The plugin id.
   *
   * @var stringprovidestheidoftheplugin
   */
    public $id;
}
