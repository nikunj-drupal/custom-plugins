<?php

namespace Drupal\nylotto_data_import\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\nylotto_data_import\ImportData;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\file\Entity\File;
use FtpClient\FtpClient;
use Drupal\Core\File\FileSystemInterface;
use phpseclib\Crypt\RSA;
use phpseclib\Net\SFTP;

/**
 * {@inheritdoc}
 *
 * @QueueWorker(
 *   id = "ny_ftp_file_queue",
 *   title = @Translation("Cron File Importer"),
 *   cron = {"time" = 10}
 * )
 */
class NYFtpFileImport extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
    error_log("Processing Queue item for $data->filename");
    $ImportSource = entity_load('import_ftp_source', $data->import_source);
    if ($data->sftp) {
      $config = \Drupal::config('nylotto_custom_json.ftp.settings');
      // create new RSA key
      $privateKey = new RSA();
      $privateKey->loadKey($config->get('private_key'));

      $this->sftpDownload($ImportSource, $data, $privateKey);
    }
    else {
      $this->ftpDownload($ImportSource, $data);
    }
  }

  /**
   * Download the file via ftp.
   */
  protected function ftpDownload($ImportSource, $data) {
    $ftp = new \FtpClient\FtpClient();

    try {
      $ftp->connect($ImportSource->server, FALSE, $ImportSource->port);
      $ftp->login($ImportSource->user, $ImportSource->password);

      // 2. Download the files from the remote source... getContent
      $uri = "public://temp";
      $path = \Drupal::service('file_system')->realpath($uri);
      array_map( 'unlink', array_filter((array) glob("{$path}/*") ) );
      if (!file_exists($path)) {
        \Drupal::service('file_system')->prepareDirectory($uri, FILE_CREATE_DIRECTORY);
      }
      $importFolder = "public://import";

      $localName = str_replace($ImportSource->path, '', $data->filename);
      $paragraphs = $this->getParagraphs($localName);
      if (empty($paragraphs)) {
        $contents = $ftp->getContent($file);
        $localUri = "public://import/{$localName}";
        $fileUri = file_save_data($contents, $localUri, FileSystemInterface::EXISTS_REPLACE);

        if ($fileUri) {
          $fileUri->setPermanent();
          $fileUri->save();
          $this->importer->importFile($fileUri);
        }
      }
    } catch (\Exception $e) {
      watchdog_exception('nylotto_data_import', $e);
      return;
    }
  }

  /**
   * Download the file via sftp
   */
  protected function sftpDownload($ImportSource, $data, $privateKey) {

    $sftp = new SFTP($ImportSource->server, $ImportSource->port);

    if ($sftp->login($ImportSource->user, $privateKey)) {
      $uri = "public://temp";
      $path = \Drupal::service('file_system')->realpath($uri);

      $localName = str_replace($ImportSource->path, '', $data->filename);
      // Check for a drawing data paragraph for this node.
      $paragraphs = $this->getParagraphs($localName);
      if (empty($paragraphs)) {
        $contents = $sftp->get("{$ImportSource->path}/{$data->filename}", FALSE);
        $localUri = "public://import/{$localName}";
        $fileUri = file_save_data($contents, $localUri, FileSystemInterface::EXISTS_REPLACE);
        if ($fileUri) {
          $fileUri->setPermanent();
          $fileUri->save();
          $this->importer->importFile($fileUri);
        }
      }
    }
  }

  /**
   * Check if file is already imported or not
   */
  public function getParagraphs($fileName){
    $query = \Drupal::entityQuery('paragraph')
        ->condition('type', 'drawing_data')
        ->condition('field_file_name', $fileName)
        ->condition('parent_type', 'node');
    $pids = $query->execute();
    return $pids;
  }

}
