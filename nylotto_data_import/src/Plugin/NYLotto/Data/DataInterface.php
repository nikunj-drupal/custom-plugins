<?php

namespace Drupal\nylotto_data_import\Plugin\NYLotto\Data;

use Drupal\file\Entity\File;

/**
 * Defines the expected plugin details.
 */
interface DataInterface
{

  /**
   * Validates the file prior to processing it.
   *
   * @var \Drupal\file\Entity\FileInterface - takes a file interface.
   *
   * @return bool
   *   Returns true if this file is valid.
   */
    public function validFile(File $file);

    /**
     * Performs the import function on the file.
     */
    public function importFile(File $file, $pluginId);

    /**
     * This processes the row for the data import.
     */
    public function processRow($data);
}
