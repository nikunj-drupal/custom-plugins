<?php

namespace Drupal\nylotto_data_import\Plugin\NYLotto\Data;

use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Yaml\Yaml;

/**
 * Cash4Life data import plugin.
 *
 * @NyDataType(
 *   id = "cash_4_life"
 * )
 */
class Cash4Life extends BaseData
{
    use StringTranslationTrait;

    /**
     * Cash4Life yml file name.
     *
     * @var string
     */
    public $schemaFile = '/yamls/cash4life.yml';

    /**
     * Cash4Life file prefix for import.
     *
     * @var string
     */

    public $filenamePrefix = 'Cash4Life_';

    /**
     * Cash4Life plugin id name.
     *
     * @var string
     */

    public $pluginId = 'cash_4_life';

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->pluginId = 'cash_4_life';
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
     * - Challenge here is we dont have the date
     */
    public function importFile(File $file)
    {
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
                $importItem->pluginId = 'cash_4_life';
                $importItem->file_name = $fileNames;
                foreach ($contents as $row) {
                    if (!empty($row)) {
                        $object = $this->parseRow($schema, $row);

                        // Adding this allows us to associate the location data to the draw date...
                        if (isset($object->draw_date)) {
                            $drawDate = $object->draw_date;
                            $object->file_name = $fileNames;
                        } elseif ($object) {
                            $object->draw_date = $drawDate;
                            $object->file_name = $fileNames;
                        }

                        if ($object) {
                            $object->pluginId = $this->pluginId;
                            if (strpos($fileNames, 'eve') > 0) {
                                $time = 'Evening';
                                $object->draw_time = $time;
                            } else {
                                $time = 'Daytime';
                                $object->draw_time = $time;
                            }
                            // if ($fileName == 'mid' || $fileName == 'eve') {
                            //   $object->field_draw_time = $fileName;
                            // }
                            switch ($object->record_indicator) {
                                case '0':
                                    $importItem->drawing  = $object;
                                    break;
                                case '1':
                                    if (!isset($importItem->locationData)) {
                                        $importItem->locationData = [];
                                    }
                                    $importItem->locationData[] = $object;
                                    break;
                                case '2':
                                    if (!isset($importItem->secondLocationData)) {
                                        $importItem->secondLocationData = [];
                                    }
                                    $importItem->secondLocationData[] = $object;
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
        $startTime = time();
        $ids = \Drupal::entityQuery('node')
          ->condition('type', 'game')
          ->condition('field_game_id', 'cash4life')
          ->execute();
        $node = entity_load('node', array_values($ids)[0]);
        if ($node) {
            $drawingDataParagraph = $this->drawing($node, $data->drawing, $data->file_name);
            if ($data->locationData) {
                $identify = 0;
                foreach ($data->locationData as $delta => $locationData) {
                    $identify++;
                    $locationData->winning_loc_identify = $identify.'_'.$locationData->pluginId;
                    $this->locationData($drawingDataParagraph, $locationData, 'First');
                }
            }
            if ($data->secondLocationData) {
                $identify = 0;
                foreach ($data->secondLocationData as $delta => $locationData) {
                    $identify++;
                    $locationData->winning_loc_identify = $identify.'_'.$locationData->pluginId;
                    $this->locationData($drawingDataParagraph, $locationData, 'Second');
                }
            }
        } else {
            throw new \Exception("No node found for Cash4life");
            // We could not find the cash 4 life node!!!!
            \Drupal::logger('nylotto_importer')->error("No node found");
        }
        $endTime = time();
    }

    /**
     * Imports the data for the drawing row.
     */
    protected function drawing($node, $record, $filename)
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
        $entity = $this->addDrawingData($node, [
          'draw_date' => $record->draw_date,
          'winning_numbers' => $record->winning_numbers,
          'bonus_ball' => $record->cash_ball,
          'file_name' => $filename,
        ], $pid);

        /**
         * There are 9 levels here. All local, and none have locations.
         */
        $this->addWinnersData($entity, [
         'prize_label' => 'First',
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
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Ninth',
          'winners' => $record->ninth_prize_winners,
          'amount' => $record->ninth_prize_amount,
          'wager_type' => ''
        ], false, false, $pid);

        // Nodes are saved in the winners data call.
        return $entity;
    }

    /**
     * {@inheritdoc}
     */
    protected function locationData($drawingDataParagraph, $record, $level)
    {
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
}
