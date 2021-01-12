<?php

namespace Drupal\nylotto_data_import\Plugin\NYLotto\Data;

use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Lotto data import plugin.
 *
 * @NyDataType(
 *   id = "historical_quickdraw_data"
 * )
 */
class HistoricalPayoutQuickdrawData extends BaseData implements DataInterface
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
        $match = ((strpos($filename, 'quick_draw_payout_data') > -1));
        if ($match) {
            error_log("Matched historical file for quickdraw");
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
        foreach ($content as $delta => $row) {
            if ($delta == 0) {
                $headers = $row;
                continue;
            }
            $data = [];
            foreach ($row as $i => $value) {
                $data[$headers[$i]] = $value;
            }
            $data['plugin_id'] = 'historical_quickdraw_data';
            $queue->createItem((object) $data);
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

    /**
     * This processes the row for the data import.
     */
    public function processRow($data)
    {
        $data->game_type = 'quickdraw';
        $nids = \Drupal::entityQuery('node')
         ->condition('field_game_key', $data->game_type)
         ->execute();
        $nid = array_shift($nids);

        error_log("Quickdraw data: " . print_r($data, true));

        if ($node = entity_load('node', $nid)) {
            $drawDate = new \DateTime();
            $drawDate->setTimestamp(strtotime($data->draw_date));
            $query = \Drupal::entityQuery('paragraph')
             ->condition('type', 'drawing_data')
             ->condition('parent_id', $nid)
             ->condition('parent_type', 'node')
             ->condition('field_draw_date', $drawDate->format('Y-m-d'));
            if ($data->game_type == 'quickdraw') {
                error_log("called quick draw");
                $query->condition('field_draw_time', $drawDate->format('H:i'));
            }
            $paragraphId = $query->execute();
            if ($paragraph = entity_load('paragraph', array_shift($paragraphId))) {
                // existing paragraph
                error_log("called quick draw update");
                $this->quickDrawUpdateParagraph($data, $drawDate, $node, $paragraph);
            } else {
                // new paragraph
                error_log("called quick draw create");
                $this->quickDrawCreateParagraph($data, $drawDate, $node);
            }
        }
    }

    /**
     * Adds the drawing data to the quickdraw paragraph
     */
    protected function quickDrawUpdateParagraph($data, $drawDate, $node, $paragraph)
    {
        error_log('Updating quickdraw drawing paragraph');
        $updated = new \DateTime();
        $paragraph->set('field_total_prizes', $data->total_prizes);
        $paragraph->set('field_jackpot_winners', $data->total_winners);
        $paragraph->set('field_last_update', $updated->format('Y-m-d\TH:i:00'));
        $paragraph->set('field_winning_numbers_txt', $data->winning_numbers);
        $paragraph->set('field_multiplier', $data->extra);
        $paragraph->save();
    }

    // Create a new paragraph for the quick draw game.
    protected function quickDrawCreateParagraph($data, $drawDate, $node)
    {
        $updated = new \DateTime();
        $paragraphdata = [
         'type' => 'drawing_data',
         'parent_id' => $node->id(),
         'parent_type' => 'node',
         'field_draw_date' => $drawDate->format('Y-m-d'),
         'field_draw_time' => $drawDate->format('H:i'),
         'field_total_prizes' => $data->total_prizes,
         'field_jackpot_winners' => $data->total_winners,
         'field_last_update' => $updated->format('Y-m-d\TH:i:00'),
         'field_winning_numbers_txt' => $data->winning_numbers,
         'field_multiplier' => $data->extra,
        ];
        $paragraph = Paragraph::create($paragraphdata);
        $paragraph->save();
        $node->field_drawing_data[] = ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
        $node->save();
    }
}
