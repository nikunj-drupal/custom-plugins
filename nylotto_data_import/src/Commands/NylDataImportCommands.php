<?php

namespace Drupal\nylotto_data_import\Commands;

use Drush\Commands\DrushCommands;

/**
 * @package Drupal\nylotto_data_import\Commands
 */
class NylDataImportCommands extends DrushCommands
{
    /**
     * @param string $game_name
     *   Game name to import
     * @command nyl_data_import:api-import
     * @aliases apii
     * @usage nyl_data_import:api-import quickdraw from_draw_id to_draw_id
     */
    public function api_import($game_name, $from_draw_id = null, $to_draw_id = null)
    {
        // TODO
        new ApiImportCommand($game_name, $from_draw_id, $to_draw_id);
    }

    /**
     * @param string $game_name
     *  Game name to import
     * @command nyl_data_import:ftp-download
     * @alias ftpd
     * @usage nyl_data_import:ftp-download megamillions
     */
    public function ftp_download($game_name)
    {
        new FtpDownloadCommand($game_name);
    }

    /**
     * @command nyl_data_import:retailer-import
     * @alias reil
     * @usage nyl_data_import:retailer-import
     */
    public function retailer_import()
    {
        new RetailerImportCommand();
    }
}
