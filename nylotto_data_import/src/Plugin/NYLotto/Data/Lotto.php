<?php

namespace Drupal\nylotto_data_import\Plugin\NYLotto\Data;

use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Yaml\Yaml;

/**
 * Lotto data import plugin.
 *
 * @NyDataType(
 *   id = "lotto"
 * )
 */
class Lotto extends BaseData
{
    use StringTranslationTrait;

    /**
     * Lotto yml file name.
     *
     * @var string
     */
    public $schemaFile = '/yamls/lotto.yml';

    /**
     * Lotto file prefix for import.
     *
     * @var string
     */
    public $filenamePrefix = 'Lotto_';

    /**
     * Lotto plugin id name.
     *
     * @var string
     */
    public $pluginId = 'lotto';

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->pluginId = 'lotto';
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
                $importItem->pluginId = 'lotto';
                foreach ($contents as $row) {
                    $object = $this->parseRow($schema, $row);

                    // Adding this allows us to associate the location data to the draw date...
                    if (isset($object->draw_date)) {
                        $drawDate = $object->draw_date;
                    } elseif ($object) {
                        $object->draw_date = $drawDate;
                    }

                    if ($object) {
                        $object->pluginId = 'lotto';
                        $object->file_name = $fileNames;
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
                            case '0':
                                $importItem->drawing = $object;
                                break;

                            case '1':
                                if (!isset($importItem->jackpotLocation)) {
                                    $importItem->jackpotLocation = [];
                                }
                                $importItem->jackpotLocation[] = $object;
                                break;

                            case '2':
                                if (!isset($importItem->secondLocation)) {
                                    $importItem->secondLocation = [];
                                }
                                $importItem->secondLocation[] = $object;
                                break;
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
        $ids = \Drupal::entityQuery('node')
          ->condition('type', 'game')
          ->condition('field_game_id', 'lotto')
          ->execute();
        $node = entity_load('node', array_values($ids)[0]);
        if ($node) {
            $drawingDataParagraph = $this->drawing($node, $data->drawing);
            if ($data->jackpotLocation) {
                $identify = 0;
                foreach ($data->jackpotLocation as $delta => $jackpotLocation) {
                    $identify++;
                    $jackpotLocation->winning_loc_identify = $identify.'_'.$jackpotLocation->pluginId;
                    $this->locationData($drawingDataParagraph, $jackpotLocation, 'Jackpot');
                }
            }
            if ($data->secondLocation) {
                $identify = 0;
                foreach ($data->secondLocation as $delta => $secondLocation) {
                    $identify++;
                    $secondLocation->winning_loc_identify = $identify.'_'.$secondLocation->pluginId;
                    $this->locationData($drawingDataParagraph, $secondLocation, 'Second');
                }
            }
        } else {
            // We could not find the New York Lotto1 node!!!!
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

        $entity = $this->addDrawingData($node, [
          'draw_date' => $record->draw_date,
          'winning_numbers' => $record->winning_numbers,
          'bonus_ball' => $record->bonus,
          'jackpot' => $record->jackpot,
          'jackpot_winners' => $record->jackpot_winners,
          'file_name' => $record->file_name,
        ], $pid);

        // There are five levels for this game....
        $this->addWinnersData($entity, [
          'prize_label' => 'Jackpot',
          'winners' => $record->first_prize_winners,
          'amount' => $record->first_prize,
          'wager_type' => ''
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Second',
          'winners' => $record->second_prize_winners,
          'amount' => $record->second_prize,
          'wager_type' => ''
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Third',
          'winners' => $record->third_prize_winners,
          'amount' => $record->third_prize,
          'wager_type' => ''
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Fourth',
          'winners' => $record->fourth_prize_winners,
          'amount' => $record->fourth_prize,
          'wager_type' => ''
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Fifth',
          'winners' => $record->fifth_prize_winners,
          'amount' => $record->fifth_prize,
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
}
