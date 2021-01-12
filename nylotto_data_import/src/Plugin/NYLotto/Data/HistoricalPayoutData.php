<?php

namespace Drupal\nylotto_data_import\Plugin\NYLotto\Data;

use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Lotto data import plugin.
 *
 * @NyDataType(
 *   id = "historical_payout_data"
 * )
 */
class HistoricalPayoutData extends BaseData implements DataInterface
{

  /**
   * Validates the file prior to processing it.
   *
   * @var \Drupal\file\Entity\FileInterface - takes a file interface.
   *
   * @return bool
   *   Returns true if this file is valid.
   */
    public function validFile(File $file)
    {
        $filename = $file->getFileUri();
        error_log("Filename: " . $filename);
        $match = ((strpos($filename, 'payout_data') > -1));
        if ($match && !(strpos($filename, 'quick'))) {
            drupal_set_message('Matched historical file');
            error_log("Matched historical file");
        }
        return $match;
    }

    protected $winnerTypes = [
    'numbers' => [
      'box_play_winners_table' => '',
      'close_enough_winners_table' => '',
    ],

    ];


    /**
     * Import File
     */
    public function importFile(File $file, $pluginId = '')
    {
        $queue_factory = \Drupal::service('queue');
        /** @var QueueInterface $queue */
        $queue = $queue_factory->get('ny_data_queue');
        $filename = $file->getFileUri();
        $content = array_map('str_getcsv', file($file->getFileURI()));
        $headers = [];

        /**
         * Unlike the other files we need to build up an array of
         * import rows so we can compile a single import record.
         */
        $lastDrawDate = new \DateTime();
        $lastDrawTime = '';
        $record = [];
        foreach ($content as $delta => $row) {
            if ($delta == 0) {
                $headers = $row;
            }
            $thisDrawDate = new \DateTime();
            $thisDrawDate->setTimestamp(strtotime($row[1]));
            $thisDrawTime = $row[2];
            if ($thisDrawDate->format('Y-m-d') !== $lastDrawDate->format('Y-m-d') || ($thisDrawDate->format('Y-m-d') == $lastDrawDate->format('Y-m-d') && $thisDrawTime !== $lastDrawTime)) {
                $record['pluginId'] = 'historical_payout_data';
                $queue->createItem((object) $record);
                $lastDrawDate = $thisDrawDate;
                $lastDrawTime = $thisDrawTime;
                $record = [];
            }
            $data = [];
            foreach ($row as $c => $column) {
                $data[$headers[$c]] = $column;
            }
            $record['data'][] = (object) $data;
        }
    }

    /**
     * Get the game node based on the game id.
     */
    protected function getGameNode($gameId)
    {
        $nids = \Drupal::entityQuery('node')
         ->condition('field_game_key', $gameId)
         ->execute();
        $nid = array_shift($nids);
        return $node = entity_load('node', $nid);
    }

    protected $prizeLevelLabels = [
      1 => 'First',
      2 => 'Second',
      2 => 'Third',
      4 => 'Fourth',
      5 => 'Fifth',
      6 => 'Sixth',
      7 => 'Seventh',
      8 => 'Eighth',
      9 => 'Nineth'
    ];

    /**
     * Performs the import function on the file.
     */
    public function processRow($data)
    {
        $node = $this->getGameNode($data->data[0]->game_type);

        if ($node) {
            error_log("Adding data for {$node->label()}");
            // First create us a drawing data paragraph.....
            $drawingDataParagraph = $this->addDrawingData(
                $data->data[0]->game_type,
                [
                 'draw_date' => $data->data[0]->draw_date,
                 'winning_numbers' => $data->data[0]->winning_numbers,
                 'bonus_ball' => $data->data[0]->cashball,
                 'multiplier' => $data->data[0]->multiplier,
                 'draw_time' => $data->data[0]->draw_time,
                 'collect_time' => '',
                 'jackpot' => $data->data[0]->top_prize,
                 'next_draw_date' => '',
                 'next_jackpot' => '',
                 'jackpot_winners' => $data->data[0]->total_winners,
                ]
            );

            // Next do the game levels.
            error_log("Adding prize levels for {$node->label()} {$data->data[0]->draw_date}");
            foreach ($data->data as $delta => $record) {
                $this->sanitizeData($record);
                $this->addWinnersData($drawingDataParagraph, [
                 'prize_label' => $this->prizeLevelLabels[$record->prize_level],
                 'winners' => $record->winners,
                 'amount' => $record->prize,
                 'wager_type' => ''
                ], ($record->winner_type == 'national_winners_table'), false);
            }

            // Next we need to add in addres information if there is any.
            error_log("Adding retailer data for {$node->label()} {$data->data[0]->draw_date}");
            foreach ($data->data as $delta => $record) {
                $this->sanitizeData($record);
                if ($record->retailer_name !== '' && $record->retailer_name !== 'NULL') {
                    $winnerDataParagraph = $this->getWinnerDataParagraph($drawingDataParagraph->id(), $this->prizeLevelLabels[$record->prize_level]);
                }
                $this->addLocationData($winnerDataParagraph, [
                 'ticket_type' => '',
                 'address' => $record->retailer_street,
                 'name' => $record->retailer_name,
                 'city' => $record->city,
                 'winning_county' => $record->county,
                 'state' => $record->state,
                ]);
            }
        } else {
            drupal_set_message("Error data: " . print_r($data, true));
            throw new \Exception('Could not find game.');
        }
    }
}
