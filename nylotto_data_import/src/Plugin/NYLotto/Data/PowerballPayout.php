<?php

namespace Drupal\nylotto_data_import\Plugin\NYLotto\Data;

use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\Yaml\Yaml;

/**
 * Creates the import plugin for powerball.
 *
 * @NyDataType(
 *   id = "powerball_payout"
 * )
 */
class PowerballPayout extends BaseData
{

  /**
   * {@inheritdoc}
   */
    public $schemaFile = '/yamls/powerball.yml';

    /**
     * {@inheritdoc}
     */
    public $filenamePrefix = 'Power_';

    /**
     * {@inheritdoc}
     */
    public $pluginId = 'powerball_payout';

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->pluginId = 'powerball_payout';
    }

    /**
     * {@inheritdoc}
     */
    public function importFile(File $file)
    {
        $this->pluginId = 'powerball_payout';
        $drawDate = '';
        $schemaFileContents = file_get_contents(drupal_get_path('module', 'nylotto_data_import') . $this->schemaFile);
        $queue_factory = \Drupal::service('queue');
        /** @var QueueInterface $queue */
        $queue = $queue_factory->get('ny_data_queue');

        if ($schemaFileContents) {
            $schema = Yaml::parse($schemaFileContents, true, true);
            // Next get the contents of the file we are importing.
            $contents = explode("\n", file_get_contents($file->getFileURI()));
            $fileName = '';
            $fileNames = $file->getFilename();
            if (isset($fileNames)) {
                $fileNameArray = explode('_', $fileNames);
                $fileName = $fileNameArray[1];
            }

            // Now we can loop through the file.
            if ($contents !== '') {
                $importItem = new \stdClass();
                ;
                $importItem->pluginId = 'powerball_payout';
                foreach ($contents as $row) {
                    if (!empty($row)) {
                        $object = $this->parseRow($schema, $row);
                        // Adding this allows us to associate the location data to the draw date...
                        if (isset($object->draw_date)) {
                            $drawDate = $object->draw_date;
                        } elseif ($object) {
                            $object->draw_date = $drawDate;
                        }
                        $object->file_name = $fileNames;
                        if ($object) {
                            $object->pluginId = $this->pluginId;
                            // if ($fileName == 'mid' || $fileName == 'eve') {
                            //   $object->field_draw_time = $fileName;
                            // }
                            if (strpos($fileNames, 'eve') > 0) {
                                $time = 'Evening';
                                $object->draw_time = $time;
                            } else {
                                $time = 'Daytime';
                                $object->draw_time = $time;
                            }
                            switch ($object->record_indicator) {
                                case 0:
                                    $importItem->drawing = $object;
                                    break;
                                case 1:
                                    $importItem->powerPlayDrawing = $object;
                                    break;
                                case 2:
                                    if (!isset($importItem->jackpotWinnerData)) {
                                        $importItem->jackpotWinnerData = [];
                                    }
                                    $importItem->jackpotWinnerData[] = $object;
                                    break;
                                case 3:
                                    if (!isset($importItem->secondWinnerData)) {
                                        $importItem->secondWinnerData = [];
                                    }
                                    $importItem->secondWinnerData[] = $object;
                                    break;
                                case 4:
                                    if (!isset($importItem->powerplayWinnerData)) {
                                        $importItem->powerplayWinnerData = [];
                                    }
                                    $importItem->powerplayWinnerData[] = $object;
                                    break;
                            }
                        }
                    }
                }
                $this->processRow($importItem);
            } else {
                \Drupal::logger('nylotto_importer')->error("File is empty, could not parse");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function processRow($data)
    {
        \Drupal::logger('nylotto_importer')->error("Processing row for PowerballPayout");
        $ids = \Drupal::entityQuery('node')
          ->condition('type', 'game')
          ->condition('field_game_id', 'powerball')
          ->execute();
        $node = entity_load('node', array_values($ids)[0]);
        if ($node) {
            $drawingDataParagraph = $this->drawing($node, $data->drawing);
            $this->powerPlayDrawing($node, $drawingDataParagraph, $data->powerPlayDrawing);
            if ($data->jackpotWinnerData) {
                $identify = 0;
                foreach ($data->jackpotWinnerData as $delta => $winnerData) {
                    $identify++;
                    $locationData->winning_loc_identify = $identify.'_'.$locationData->pluginId;
                    $this->locationData($drawingDataParagraph, $winnerData, 'Jackpot');
                }
            }

            if ($data->secondWinnerData) {
                $identify = 0;
                foreach ($data->secondWinnerData as $delta => $winnerData) {
                    $identify++;
                    $winnerData->winning_loc_identify = $identify.'_'.$winnerData->pluginId;
                    $this->locationData($drawingDataParagraph, $winnerData, 'Second');
                }
            }

            if ($data->powerplayWinnerData) {
                $identify = 0;
                foreach ($data->powerplayWinnerData as $delta => $winnerData) {
                    $identify++;
                    $winnerData->winning_loc_identify = $identify.'_'.$winnerData->pluginId;
                    $this->locationData($drawingDataParagraph, $winnerData, 'Second - Powerplay');
                }
            }
        } else {
            // We could not find the power ball node!!!!
            \Drupal::logger('nylotto_importer')->error("No node found");
        }
    }

    /**
     * Imports the data for the drawing row.
     */
    protected function drawing($node, $record)
    {
        $drawDate = new \DateTime();
        $drawDate->setTimeStamp(strtotime($record->draw_date));
        // Check for a drawing data paragraph for this node.
        $query = \Drupal::entityQuery('drawing')
          ->condition('game', $node->id())
          ->condition('field_draw_date', $drawDate->format('Y-m-d'));
        // if (!empty($record->draw_time)) {
        //   $query->condition('field_draw_time', $record->draw_time);
        // }
        $pid = '';
        $pids = $query->execute();
        if (!empty($pids)) {
            $pid = array_shift($pids);
        }

        $this->sanitizeData($record);
        $entity = $this->addDrawingData($node, [
          'draw_date' => $record->draw_date,
          'winning_numbers' => $record->winning_numbers,
          'bonus_ball' => $record->powerball,
          'multiplier' => $record->power_play,
          'jackpot' => $record->jackpot,
          'jackpot_winners' => $record->jackpot_winners,
          'file_name' => $record->file_name,
        ], $pid);

        /**
         * There are 9 levels here. All local, and none have locations.
         */
        $this->addWinnersData($entity, [
           'prize_label' => 'Jackpot',
           'winners' => $record->first_prize_winners,
           'amount' => $record->first_prize_amount,
           'wager_type' => ''
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Second',
          'winners' => $record->second_prize_winners,
          'amount' => $record->second_prize_amount,
          'wager_type' => ''
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Third',
          'winners' => $record->third_prize_winners,
          'amount' => $record->third_prize_amount,
          'wager_type' => ''
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Fourth',
          'winners' => $record->fourth_prize_winners,
          'amount' => $record->fourth_prize_amount,
          'wager_type' => ''
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Fifth',
          'winners' => $record->fifth_prize_winners,
          'amount' => $record->fifth_prize_amount,
          'wager_type' => ''
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Sixth',
          'winners' => $record->sixth_prize_winners,
          'amount' => $record->sixth_prize_amount,
          'wager_type' => ''
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Seventh',
          'winners' => $record->seventh_prize_winners,
          'amount' => $record->seventh_prize_amount,
          'wager_type' => ''
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Eighth',
          'winners' => $record->eighth_prize_winners,
          'amount' => $record->eighth_prize_amount,
          'wager_type' => ''
        ], false, false, $pid, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Ninth',
          'winners' => $record->ninth_prize_winners,
          'amount' => $record->ninth_prize_amount,
          'wager_type' => ''
        ], false, false, $pid);

        return $entity;
    }

    /**
     * Handles importing Power Play draw data.
     */
    protected function powerPlayDrawing($node, $drawingDataParagraph, $record)
    {
        $drawDate = new \DateTime();
        $drawDate->setTimeStamp(strtotime($record->draw_date));
        // Check for a drawing data paragraph for this node.
        $query = \Drupal::entityQuery('drawing')
          ->condition('game', $node->id())
          ->condition('field_draw_date', $drawDate->format('Y-m-d'));
        // if (!empty($record->draw_time)) {
        //   $query->condition('field_draw_time', $record->draw_time);
        // }
        $pids = $query->execute();
        $pid = array_values($pids)[0];

        /**
         * There are 9 levels here. All local, and none have locations.
         */
        // $this->addWinnersData($drawingDataParagraph, [
        //   'prize_label' => 'First - Powerplay',
        //   'winners' => (string)((int)$record->first_prize_winners),
        //   'amount' => (string)((int)$record->first_prize_amount),
        //   'wager_type' => ''
        // ], false, true, $pid);

        $this->addWinnersData($drawingDataParagraph, [
          'prize_label' => 'Second - Powerplay',
          'winners' => (string)((int)$record->second_prize_winners),
          'amount' => (string)((int)$record->second_prize_amount),
          'wager_type' => ''
        ], false, true, $pid);

        $this->addWinnersData($drawingDataParagraph, [
          'prize_label' => 'Third - Powerplay',
          'winners' => (string)((int)$record->third_prize_winners),
          'amount' => (string)((int)$record->third_prize_amount),
          'wager_type' => ''
        ], false, true, $pid);

        $this->addWinnersData($drawingDataParagraph, [
          'prize_label' => 'Fourth - Powerplay',
          'winners' => (string)((int)$record->fourth_prize_winners),
          'amount' => (string)((int)$record->fourth_prize_amount),
          'wager_type' => ''
        ], false, true, $pid);

        $this->addWinnersData($drawingDataParagraph, [
          'prize_label' => 'Fifth - Powerplay',
          'winners' => (string)((int)$record->fifth_prize_winners),
          'amount' => (string)((int)$record->fifth_prize_amount),
          'wager_type' => ''
        ], false, true, $pid);

        $this->addWinnersData($drawingDataParagraph, [
          'prize_label' => 'Sixth - Powerplay',
          'winners' => (string)((int)$record->sixth_prize_winners),
          'amount' => (string)((int)$record->sixth_prize_amount),
          'wager_type' => ''
        ], false, true, $pid);

        $this->addWinnersData($drawingDataParagraph, [
          'prize_label' => 'Seventh - Powerplay',
          'winners' => (string)((int)$record->seventh_prize_winners),
          'amount' => (string)((int)$record->seventh_prize_amount),
          'wager_type' => ''
        ], false, true, $pid, $pid);

        $this->addWinnersData($drawingDataParagraph, [
          'prize_label' => 'Eighth - Powerplay',
          'winners' => (string)((int)$record->eighth_prize_winners),
          'amount' => (string)((int)$record->eighth_prize_amount),
          'wager_type' => ''
        ], false, true, $pid);

        $this->addWinnersData($drawingDataParagraph, [
          'prize_label' => 'Ninth - Powerplay',
          'winners' => (string)((int)$record->ninth_prize_winners),
          'amount' => (string)((int)$record->ninth_prize_amount),
          'wager_type' => ''
        ], false, true, $pid);
    }

    /**
     * {@inheritdoc}
     */
    protected function locationData($drawingDataParagraph, $record, $level)
    {
        error_log("Location data: " . print_r($record, true));
        // next we need the winner data ...
        $winnerDataParagraph = $this->getWinnerDataParagraph($drawingDataParagraph->id(), $level);
        if (!($winnerDataParagraph)) {
            error_log("Could not find winner data for level {$level}");
            return false;
        }
        // now we can add our location data
        $this->addLocationData($winnerDataParagraph, [
          'ticket_type' => $record->ticket_type,
          'address' => $record->retailer_address,
          'name' => $record->retailer_name,
          'city' => $record->retailer_city,
          'winning_county' => $record->winning_county,
          'winning_identify' => $record->winning_loc_identify
        ]);
    }
}
