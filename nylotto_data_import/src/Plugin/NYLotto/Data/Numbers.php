<?php

namespace Drupal\nylotto_data_import\Plugin\NYLotto\Data;

use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Yaml\Yaml;

/**
 * Numbers data import plugin.
 *
 * @NyDataType(
 *   id = "numbers"
 * )
 */
class Numbers extends BaseData
{
    use StringTranslationTrait;

    /**
     * Numbers yml file name.
     *
     * @var string
     */
    public $schemaFile = '/yamls/numbers.yml';

    /**
     * Numbers file prefix for import.
     *
     * @var string
     */
    public $filenamePrefix = 'Numbers_';

    /**
     * Numbers plugin id name.
     *
     * @var string
     */
    public $pluginId = 'numbers';

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->pluginId = 'numbers';
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
        $this->pluginId = 'numbers';
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
            $time = '';
            if (isset($fileNames)) {
                $fileNameArray = explode('_', $fileNames);
                $fileName = $fileNameArray[1];

                if (strpos($fileNames, 'eve') > 0) {
                    $time = 'Evening';
                } else {
                    $time = 'Midday';
                }
            }

            // Now we can loop through the file.
            if ($contents !== '') {
                // $importItem = new \stdClass();;
                // $importItem->pluginId = 'numbers';
                foreach ($contents as $row) {
                    if (!empty($row)) {
                        $object = $this->parseRow($schema, $row);
                        // Adding this allows us to associate the location data to the draw date...
                        if (isset($object->draw_date)) {
                            $drawDate = $object->draw_date;
                        } elseif ($object) {
                            $object->draw_date = $drawDate;
                        }
                        $object->draw_time = $time;
                        $object->file_name = $fileNames;

                        if ($object) {
                            $object->pluginId = $this->pluginId;
                        }
                    }
                }
                $this->processRow($object);
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
        \Drupal::logger('nylotto_importer')->error("Processing row for Numbers");
        $ids = [];
        $ids = \Drupal::entityQuery('node')
          ->condition('type', 'game')
          ->condition('field_game_id', 'numbers')
          ->execute();
        $node = entity_load('node', array_values($ids)[0]);
        if ($node) {
            $this->drawing($node, $data);
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
        \Drupal::logger('nylotto_importer')->error("Numbers: " .print_r($record, true));
        if ($record->draw_date == '') {
            return;
        }
        $total_prizes = ltrim($record->prizes_won, '0');
        $jackpot_winners = ltrim($record->number_of_winners, '0');
        $drawDate = new \DateTime();
        $drawDate->setTimeStamp(strtotime($record->draw_date));
        // Check for a drawing data paragraph for this node.

        $query = \Drupal::entityQuery('drawing')
          ->condition('game', $node->id())
          ->condition('field_draw_date', $drawDate->format('Y-m-d'));
        if (!empty($record->draw_time)) {
            $query->condition('field_draw_time', $record->draw_time);
        }
        $pid = '';
        $pids = $query->execute();
        if (!empty($pids)) {
            $pid = array_shift($pids);
        }

        $entity = $this->addDrawingData($node, [
          'draw_date' => $record->draw_date,
          'winning_numbers' => $this->getOtherWinningNumbers($record->winning_numbers),
          'draw_time' => $record->draw_time,
          'total_prizes' => $total_prizes,
          'jackpot_winners' => $jackpot_winners,
          'file_name' => $record->file_name,
        ], $pid);

        // 7 levels of winners
        $this->addWinnersData($entity, [
          'prize_label' => 'N/A',
          'winners' => ltrim($record->straight_play_winners, '0'),
          'amount' => '0',
          'wager_type' => 'Straight Play',
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'N/A',
          'winners' => ltrim($record->box_play_winners, '0'),
          'amount' => '0',
          'wager_type' => 'Box Play',
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Front Pair',
          'winners' => ltrim($record->front_pair_winners, '0'),
          'amount' => '0',
          'wager_type' => 'Pair Play',
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Back Pair',
          'winners' => ltrim($record->back_pair_winners, '0'),
          'amount' => '0',
          'wager_type' => 'Pair Play',
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Exact',
          'winners' => ltrim($record->straight_box_exact_winners, '0'),
          'amount' => '0',
          'wager_type' => 'Straight/Box',
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'Box',
          'winners' => ltrim($record->straight_box_box_winners, '0'),
          'amount' => '0',
          'wager_type' => 'Straight/Box',
        ], false, false, $pid);

        $this->addWinnersData($entity, [
          'prize_label' => 'N/A',
          'winners' => ltrim($record->combination_winners, '0'),
          'amount' => '0',
          'wager_type' => 'Combination',
        ], false, false, $pid);

        //return $node;
    }
}
