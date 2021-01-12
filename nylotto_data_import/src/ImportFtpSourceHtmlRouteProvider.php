<?php

namespace Drupal\nylotto_data_import;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;

/**
 * Provides routes for FTP Source entities.
 *
 * @see Drupal\Core\Entity\Routing\AdminHtmlRouteProvider
 * @see Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider
 */
class ImportFtpSourceHtmlRouteProvider extends AdminHtmlRouteProvider
{

  /**
   * {@inheritdoc}
   */
    public function getRoutes(EntityTypeInterface $entity_type)
    {
        $collection = parent::getRoutes($entity_type);

        // Provide your custom entity routes here.
        return $collection;
    }
}
