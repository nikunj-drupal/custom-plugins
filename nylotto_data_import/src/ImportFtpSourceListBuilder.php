<?php

namespace Drupal\nylotto_data_import;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of FTP Source entities.
 */
class ImportFtpSourceListBuilder extends ConfigEntityListBuilder
{

  /**
   * {@inheritdoc}
   */
    public function buildHeader()
    {
        $header['label'] = $this->t('FTP Source');
        $header['id'] = $this->t('Machine name');
        return $header + parent::buildHeader();
    }

    /**
     * {@inheritdoc}
     */
    public function buildRow(EntityInterface $entity)
    {
        $row['label'] = $entity->label();
        $row['id'] = $entity->id();
        // You probably want a few more properties here...
        return $row + parent::buildRow($entity);
    }
}
