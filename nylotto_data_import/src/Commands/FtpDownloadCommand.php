<?php

namespace Drupal\nylotto_data_import\Commands;

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\nylotto_data_import\Entity\ImportFtpSourceInterface;

use FtpClient\FtpClient;

use phpseclib\Crypt\RSA;
use phpseclib\Net\SFTP;

/**
 * @package Drupal\nylotto_data_import\Commands
 */
class FtpDownloadCommand
{
    /**
     * @var string
     */
    protected $game_name;

    /**
     * @var array
     */
    protected $imports;

    /**
     * @param string $game_name
     */
    public function __construct($game_name)
    {
        if ( !isset($this->filenamePrefixes()->$game_name) ) {
            \Drupal::logger('nylotto_importer')->error("$game_name is not defined.");

        } else {
            $this->game_name = $game_name;

            $sources = entity_load_multiple('import_ftp_source');

            foreach ($sources as $id => $source) {
                $this->downloadFTPFiles($source);
            }
            if ($this->imports) {
                $this->importFiles();
                $this->deleteTempFiles();
            }
        }
    }

    /**
     * @return void
     */
    function deleteTempFiles()
    {
        $fs = \Drupal::service('file_system');

        foreach ($this->imports as $import) {
            $fs->delete($import);
        }
    }

    /**
     * Connect to remote server and download files for parsing.
     *
     * @param \Drupal\nylotto_data_import\Entity\ImportFtpSourceInterface $ImportSource
     */
    protected function downloadFTPFiles(ImportFtpSourceInterface $ImportSource = null)
    {
        $config = \Drupal::config('nylotto_custom_json.ftp.settings');

        if ($ImportSource->port == 21) {
            error_log("Proceeding with ftp protocol");
            $this->ftpDownload($ImportSource);

        } else {
            error_log("Proceeding with sftp protocol");
            $this->sftpDownload($ImportSource, $config->get('private_key'));
        }
    }

    /**
     * Import using non-ssl method.
     *
     * @param \Drupal\nylotto_data_import\Entity\ImportFtpSourceInterface $ImportSource
     */
    protected function ftpDownload(ImportFtpSourceInterface $ImportSource)
    {
        // 1. Create the FTP Connection.
        $ftp = new \FtpClient\FtpClient();
        $fs = \Drupal::service('file_system');

        try {
            $ftp->connect($ImportSource->server, false, $ImportSource->port);
            $ftp->login($ImportSource->user, $ImportSource->password);

            // 2. Download the files from the remote source...
            $path = $fs->realpath($uri = "public://import");

            if (!file_exists($path)) $fs->prepareDirectory($uri, FILE_CREATE_DIRECTORY);

            foreach ($ftp->nlist($ImportSource->path) as $delta => $file) {
                $filename = basename($file);

                if ($this->isNewImportFile("$uri/$filename")) {
                    $ftp->get("$path/$filename", $file);
                    $this->imports[] = "$uri/$filename";
                }
            }

            $ftp->close();

        } catch (\Exception $e) {
            watchdog_exception('nylotto_data_import', $e);
            return;
        }
    }

    /**
     * @return string
     */
    protected function filenamePrefix()
    {
        return $this->filenamePrefixes()->{$this->game_name};
    }

    /**
     * @return array
     */
    protected function filenamePrefixes()
    {
        return (object) [
            'cash4life'      => 'Cash4Life_',
            'instant_games'  => 'Instant_Games_',
            'instant_levels' => 'Instant_Levels_',
            'lotto'          => 'Lotto_',
            'numbers'        => 'Numbers_',
            'mega_millions'  => 'Mega_',
            'pick10'         => 'Pick10_',
            'powerball'      => 'Power_',
            'quick_draw'     => 'QuickDraw_',
            'regionals'      => 'Regional_',
            'take5'          => 'Take5_',
            'win4'           => 'Win4_',
        ];
    }

    /**
     * @param  string $uri
     * @return array
     */
    protected function getFileByUri($uri)
    {
        return \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $uri]);
    }

    /**
     * @return void
     */
    protected function importFiles()
    {
        // 3.
        $files = [];

        foreach ($this->imports as $uri) {
            $files += $this->saveFile($uri);
        }

        // 4.
        $service = \Drupal::service('nylotto.data');

        foreach ($files as $id => $file) {
            $service->importFile($file);
        }
    }

    /**
     * @param  string $uri
     * @return bool
     */
    protected function isNewImportFile($uri)
    {
        if (($prefix = $this->filenamePrefix()) != substr(basename($uri), 0, strlen($prefix)))
            return false;

        if ($this->getFileByUri($uri)) return false;

        return true;
    }

    /**
     * @param array $uri
     */
    protected function saveFile($uri)
    {
        $file = File::create();

        $file->setFileUri($uri);
        $file->setOwnerId(0);
        $file->setMimeType('text/plain');
        $file->setFileName(basename($uri));
        $file->setPermanent();
        $file->save();

        return $this->getFileByUri($uri);
    }

    /**
     * Import using ssl method for sftp.
     *
     * @param \Drupal\nylotto_data_import\Entity\ImportFtpSourceInterface $ImportSource
     * @param string $private_key
     */
    protected function sftpDownload(ImportFtpSourceInterface $ImportSource, $private_key)
    {
        // 1. Create the FTP Connection.
        $sftp = new SFTP($ImportSource->server, $ImportSource->port);
        $fs = \Drupal::service('file_system');

        // create new RSA key
        $privateKey = new RSA();
        $privateKey->loadKey($private_key);

        error_log("connecting");

        try {
            if ($sftp->login($ImportSource->user, $privateKey)) {
              error_log("connection success");
                // 2. Download the files from the remote source... getContent
                $path = $fs->realpath($uri = "public://import");
                //array_map( 'unlink', array_filter((array) glob("{$path}/*") ) );

                if (!file_exists($path)) $fs->prepareDirectory($uri, FILE_CREATE_DIRECTORY);

                foreach ($sftp->nlist($ImportSource->path) as $delta => $file) {

                    if ($file !== '.' && $file !== '..') {
                        $filename = basename($file);

                        if ($this->isNewImportFile("$uri/$filename")) {
                            $sftp->get("{$ImportSource->path}/$file", "$path/$filename");
                            $this->imports[] = "$uri/$filename";
                        }
                    }
                }
            }
            else {
             error_log('Unable to Connect');
            }

            unset($sftp);

        } catch (\Exception $e) {
            \Drupal::logger('nylotto_data_import')->error($e);
            return;
        }
    }
}
