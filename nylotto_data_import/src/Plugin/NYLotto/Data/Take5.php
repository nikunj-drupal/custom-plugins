<?php

namespace Drupal\nylotto_data_import\Plugin\NYLotto\Data;

use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Yaml\Yaml;

/**
 * Take 5 data import plugin.
 *
 * @NyDataType(
 *   id = "take_5"
 * )
 */
class Take5 extends BaseData
{
    use StringTranslationTrait;

    /**
     * Take_5 yml file name.
     *
     * @var string
     */
    public $schemaFile = '/yamls/take_5.yml';

    /**
     * Take_5 file prefix for import.
     *
     * @var string
     */
    public $filenamePrefix = 'Take5_';

    /**
     * Take_5 plugin id name.
     *
     * @var string
     */
    public $pluginId = 'take_5';

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->pluginId = 'take_5';
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
        $this->pluginId = 'take_5';
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
                $importItem->pluginId = 'take_5';

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
        \Drupal::logger('nylotto_importer')->error("Processing row for Take5");
        $ids = \Drupal::entityQuery('node')
          ->condition('type', 'game')
          ->condition('field_game_id', 'Take5')
          ->execute();
        $node = entity_load('node', array_values($ids)[0]);
        if ($node) {
            $drawingDataParagraph = $this->drawing($node, $data->drawing);
            if ($data->locationData) {
                $identify = 0;
                $locationData = $data->locationData;
                foreach ($locationData as $locationDatakey => $locationDatavalue) {
                    $identify++;
                    $locationDatavalue->winning_loc_identify = $identify.'_'.$locationDatavalue->pluginId;
                    $this->locationData($node, $drawingDataParagraph, $locationDatavalue, 'First');
                }
            }

            if ($data->promoDrawing) {
                $this->promoDrawing($node, $drawingDataParagraph, $data->promoDrawing);
            }
        } else {
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
          'bonus_ball' => $record->cash_ball,
          'jackpot_winners' => $record->cash_winners,
          'file_name' => $record->file_name,
        ], $pid);

        /**
         * There are 4 levels here. All local, and none have locations.
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


        return $entity;
    }


    /**
     * {@inheritdoc}
     */
    protected function locationData($node, $drawingDataParagraph, $record, $level)
    {
        $drawDate = new \DateTime();
        $drawDate->setTimeStamp(strtotime($record->draw_date));
        // Check for a drawing data paragraph for this node.
        $query = \Drupal::entityQuery('drawing')
          ->condition('game', $node->id())
          ->condition('field_draw_date', $drawDate->format('Y-m-d'));
        if (!empty($record->draw_time)) {
            $query->condition('field_draw_time', $record->draw_time);
        }
        $pids = $query->execute();
        $pid = array_shift($pids);

        // next we need the winner data ...
        $winnerDataParagraph = $this->getWinnerDataParagraph($drawingDataParagraph->id(), $level);
        if (!($winnerDataParagraph)) {
            $winnerDataParagraph = $this->addWinnersData($drawingDataParagraph, [
              'prize_label' => $level,
              'winners' => '',
              'amount' => '',
              'wager_type' => ''
            ], false, false, $pid);
        }
        // now we can add our location data
        $this->addLocationData($winnerDataParagraph, [
          'ticket_type' => rtrim($record->ticket_type),
          'address' => rtrim($record->retailer_address),
          'name' => rtrim($record->retailer_name),
          'city' => rtrim($record->retailer_city),
          'winning_county' => rtrim($record->winning_county),
          'winning_identify' => rtrim($record->winning_loc_identify)
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function promoDrawing($node, $drawingDataParagraph, $record)
    {
        $drawDate = new \DateTime();
        $drawDate->setTimeStamp(strtotime($record->draw_date));
        // Check for a drawing data paragraph for this node.
        $query = \Drupal::entityQuery('drawing')
          ->condition('game', $node->id())
          ->condition('field_draw_date', $drawDate->format('Y-m-d'));
        if (!empty($record->draw_time)) {
            $query->condition('field_draw_time', $record->draw_time);
        }
        $pids = $query->execute();
        $pid = array_values($pids)[0];


        if ($drawingDataParagraph) {
            $drawingDataParagraph->set('field_multiplier', $record->promo_ball);
            $drawingDataParagraph->save();
            $this-addWinnersData($drawingParagraph, [
              'prize_label' => 'Fifth',
              'winners' => $record->fifth_prize_winners,
              'amount' => $record->fifth_prize_amount,
            ], false, true, $pid);

            $this-addWinnersData($drawingParagraph, [
              'prize_label' => 'Sixth',
              'winners' => $record->sixth_prize_winners,
              'amount' => $record->sixth_prize_amount,
            ], false, true, $pid);

            $this-addWinnersData($drawingParagraph, [
              'prize_label' => 'Seventh',
              'winners' => $record->seventh_prize_winners,
              'amount' => $record->seventh_prize_amount,
            ], false, true, $pid);

            $this-addWinnersData($drawingParagraph, [
              'prize_label' => 'Eighth',
              'winners' => $record->eighth_prize_winners,
              'amount' => '',
            ], false, true, $pid);
        }
    }
}
