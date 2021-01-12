<?php

namespace Drupal\nylotto_data_import\Plugin\NYLotto\Data;

use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Yaml\Yaml;

/**
 * Megamillions data import plugin.
 *
 * @NyDataType(
 *   id = "mega_millions"
 * )
 */
class MegaMillions extends BaseData
{
    use StringTranslationTrait;

    /**
     * Megamillions yml file name.
     *
     * @var string
     */
    public $schemaFile = '/yamls/mega_millions.yml';

    /**
     * Megamillion file prefix for import.
     *
     * @var string
     */
    public $filenamePrefix = 'Mega_';

    /**
     * Megamillion plugin id name.
     *
     * @var string
     */
    public $pluginId = 'mega_millions';

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->pluginId = 'mega_millions';
    }

    /**
     * {@inheritdoc}
     */
    public function validFile(File $file)
    {
        return parent::validFile($file);
    }

    /**
     * {@inheritdoc}
     */
    public function importFile(File $file)
    {
        $this->pluginId = 'mega_millions';
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
                $importItem->pluginId = 'mega_millions';
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
                                    $importItem->jackpotWinnerData = $object;
                                    break;
                                case 2:
                                  // retailer locations.
                                    if (!isset($importItem->secondWinnerData)) {
                                        $importItem->secondWinnerData = [];
                                    }
                                    $importItem->secondWinnerData[] = $object;
                                    break;
                                case 3:
                                    $importItem->megaplierWinnerData = $object;
                                    break;
                                case 4:
                                    if (!(isset($importItem->megaplierWinnerLocation))) {
                                        $importItem->megaplierWinnerLocation = [];
                                    }
                                    $importItem->megaplierWinnerLocation[] = $object;
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
        \Drupal::logger('nylotto_importer')->error("Processing row for MegaMillions");
        $ids = \Drupal::entityQuery('node')
          ->condition('type', 'game')
          ->condition('field_game_id', 'megamillions')
          ->execute();
        $node = entity_load('node', array_values($ids)[0]);
        if ($node) {
            $drawingDataParagraph = $this->drawing($node, $data->drawing);
            if ($data->jackpotWinnerData) {
                $identify = 1;
                $jackpotWinnerData = $data->jackpotWinnerData;
                $jackpotWinnerData->winning_loc_identify = $identify.'_'.$jackpotWinnerData->pluginId;
                $this->locationData($drawingDataParagraph, $jackpotWinnerData, 'Jackpot');
            }
            if ($data->secondWinnerData) {
                $identify = 0;
                foreach ($data->secondWinnerData as $delta => $locationData) {
                    $identify++;
                    $locationData->winning_loc_identify = $identify.'_'.$locationData->pluginId;
                    $this->locationData($drawingDataParagraph, $locationData, 'Second');
                }
            }
            if ($data->megaplierWinnerData) {
                $this->megaplierWinnerData($node, $drawingDataParagraph, $data->megaplierWinnerData);
            }
            if ($data->megaplierWinnerLocation) {
                $identify = 0;
                foreach ($data->megaplierWinnerLocation as $delta => $locationData) {
                    $identify++;
                    $locationData->winning_loc_identify = $identify.'_'.$locationData->pluginId;
                    $this->megaplierWinnerLocation($drawingDataParagraph, $locationData, 'Second - Megaplier');
                }
            }
        } else {
            // We could not find the Mega Millions node!!!!
            \Drupal::logger('nylotto_importer')->error("No node found");
        }
    }

    /**
     * Imports the data for the drawing row.
     */
    protected function drawing($node, $record)
    {
        $this->sanitizeData($record);
        $drawDate = new \DateTime();
        $drawDate->setTimeStamp(strtotime($record->draw_date));
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

        $entity = $this->addDrawingData(
            $node,
            [
              'draw_date' => $record->draw_date,
              'winning_numbers' => $record->winning_numbers,
              'bonus_ball' => $record->megaball,
              'multiplier' => $record->megaplier,
              'jackpot' => $record->jackpot_amount,
              'jackpot_winners' => $record->jackpot_winners,
              'file_name' => $record->file_name,
            ],
            $pid
        );

        /**
         * there are 9 levels.
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
        ], false, false, $pid, $pid);

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
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Ninth',
          'winners' => $record->ninth_prize_winners,
          'amount' => $record->ninth_prize_amount,
          'wager_type' => ''
        ], false, false, $pid);

        return $entity;
    }


    /**
     * {@inheritdoc}
     */
    protected function locationData($drawingDataParagraph, $record, $level)
    {
        // next we need the winner data ...
        $winnerDataParagraph = $this->getWinnerDataParagraph($drawingDataParagraph->id(), $level);

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


    /**
     * Megaplier winners.
     */
    protected function megaplierWinnerData($node, $drawingDataParagraph, $record)
    {
        if (($drawingDataParagraph)) {
            // We want to add in a Winner level onto the national megaplier.

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

            if (ltrim($record->second_prize_winners, '0') == '') {
                $second_prize_winners = 0;
            } else {
                $second_prize_winners = ltrim($record->second_prize_winners, '0');
            }

            if (ltrim($record->third_prize_winners, '0') == '') {
                $third_prize_winners = 0;
            } else {
                $third_prize_winners = ltrim($record->third_prize_winners, '0');
            }

            $this->addWinnersData($drawingDataParagraph, [
             'prize_label' => 'Second - Megaplier',
             'winners' => $second_prize_winners,
             'amount' => ltrim($record->second_prize_amount, '0'),
             'wager_type' => ''
            ], false, true, $pid);

            $this->addWinnersData($drawingDataParagraph, [
             'prize_label' => 'Third - Megaplier',
             'winners' => $third_prize_winners,
             'amount' => ltrim($record->third_prize_amount, '0'),
             'wager_type' => ''
            ], false, true, $pid);

            $this->addWinnersData($drawingDataParagraph, [
              'prize_label' => 'Fourth - Megaplier',
              'winners' => ltrim($record->fourth_prize_winners, '0'),
              'amount' => ltrim($record->fourth_prize_amount, '0'),
              'wager_type' => ''
            ], false, true, $pid);

            $this->addWinnersData($drawingDataParagraph, [
              'prize_label' => 'Fifth - Megaplier',
              'winners' => ltrim($record->fifth_prize_winners, '0'),
              'amount' => ltrim($record->fifth_prize_amount, '0'),
              'wager_type' => ''
            ], false, true, $pid);

            $this->addWinnersData($drawingDataParagraph, [
              'prize_label' => 'Sixth - Megaplier',
              'winners' => ltrim($record->sixth_prize_winners, '0'),
              'amount' => ltrim($record->sixth_prize_amount, '0'),
              'wager_type' => ''
            ], false, true, $pid);

            $this->addWinnersData($drawingDataParagraph, [
              'prize_label' => 'Seventh - Megaplier',
              'winners' => ltrim($record->seventh_prize_winners, '0'),
              'amount' => ltrim($record->seventh_prize_amount, '0'),
              'wager_type' => ''
            ], false, true, $pid);

            $this->addWinnersData($drawingDataParagraph, [
              'prize_label' => 'Eighth - Megaplier',
              'winners' => ltrim($record->eighth_prize_winners, '0'),
              'amount' => ltrim($record->eighth_prize_amount, '0'),
              'wager_type' => ''
            ], false, true, $pid);

            $this->addWinnersData($drawingDataParagraph, [
              'prize_label' => 'Ninth - Megaplier',
              'winners' => ltrim($record->ninth_prize_winners, '0'),
              'amount' => ltrim($record->ninth_prize_amount, '0'),
              'wager_type' => ''
            ], false, true, $pid);
        } else {
            drupal_set_message("Could not find drawing data paragraph for {$record->draw_date}");
        }
    }

    /**
     *
     */
    protected function megaplierWinnerLocation($drawingDataParagraph, $data, $level)
    {
        // next we need the winner data ...
        $winnerDataParagraph = $this->getWinnerDataParagraph($drawingDataParagraph->id(), $level);

        $this->addLocationData($winnerDataParagraph, [
          'ticket_type' => $data->ticket_type,
          'address' => $data->retailer_address,
          'name' => $data->retailer_name,
          'city' => $data->retailer_city,
          'winning_county' => $data->winning_county,
          'winning_identify' => $data->winning_loc_identify
        ], 'Second - Megaplier');
    }
}
