<?php

namespace Drupal\nylotto_data_import\Plugin\NYLotto\Data;

use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Yaml\Yaml;

/**
 * Pick10 data import plugin.
 *
 * @NyDataType(
 *   id = "pick10"
 * )
 */
class Pick10 extends BaseData
{
    use StringTranslationTrait;

    /**
     * Pick10 yml file name.
     *
     * @var string
     */
    public $schemaFile = '/yamls/pick10.yml';

    /**
     * Pick10 file prefix for import.
     *
     * @var string
     */
    public $filenamePrefix = 'Pick10_';

    /**
     * Pick10 plugin id name.
     *
     * @var string
     */
    public $pluginId = 'pick10';

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->pluginId = 'pick10';
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
        $this->pluginId = 'pick10';
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
                    $time = 'Daytime';
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
        \Drupal::logger('nylotto_importer')->error("Processing row for Pick10");
        $ids = \Drupal::entityQuery('node')
          ->condition('type', 'game')
          ->condition('field_game_id', 'pick10')
          ->execute();
        $node = entity_load('node', array_values($ids)[0]);

        if ($node) {
            error_log("pick10 before data: " . print_r($data, true));
            $this->drawing($node, $data);
            error_log("pick10 after data: " . print_r($data, true));
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
        $drawDate = new \DateTime();
        $drawDate->setTimeStamp(strtotime($record->draw_date));
        // Check for a drawing data paragraph for this node.
        $query = \Drupal::entityQuery('drawing')
          ->condition('game', $node->id())
          ->condition('field_draw_date', $drawDate->format('Y-m-d'));
        $$pid = '';
        $pids = $query->execute();
        if (!empty($pids)) {
            $pid = array_shift($pids);
        }

        // $this->sanitizeData($record);
        $total_prizes = ltrim($record->prizes_won, '0');
        $jackpot_winners = ltrim($record->number_of_winners, '0');
        error_log("pick10 sanitize data: " . print_r($record, true));
        $entity = $this->addDrawingData($node, [
          'draw_date' => $record->draw_date,
          'total_prizes' => $total_prizes,
          'jackpot_winners' => $jackpot_winners,
          'file_name' => $record->file_name,
        ], $pid);

        if (ltrim($record->ten, '0') == '') {
            $ten = 0;
        } else {
            $ten = ltrim($record->ten, '0');
        }

        if (ltrim($record->nine, '0') == '') {
            $nine = 0;
        } else {
            $nine = ltrim($record->nine, '0');
        }

        $this->addWinnersData($entity, [
          'prize_label' => 'First',
          'winners' => $ten,
          'wager_type' => '',
          'amount' => '',
        ], false, false, $pid);

        $this->addWinnersData($entity, [
           'prize_label' => 'Second',
           'winners' => $nine,
           'wager_type' => '',
           'amount' => '',
        ], false, false, $pid);

        $this->addWinnersData($entity, [
           'prize_label' => 'Third',
           'winners' => ltrim($record->eight, '0'),
           'wager_type' => '',
           'amount' => '',
        ], false, false, $pid);

        $this->addWinnersData($entity, [
           'prize_label' => 'Fourth',
           'winners' => ltrim($record->seven, '0'),
           'wager_type' => '',
           'amount' => '',
        ], false, false, $pid);

        $this->addWinnersData($entity, [
           'prize_label' => 'Fifth',
           'winners' => ltrim($record->six, '0'),
           'wager_type' => '',
           'amount' => '',
        ], false, false, $pid);

        $this->addWinnersData($entity, [
           'prize_label' => 'Sixth',
           'winners' => ltrim($record->none, '0'),
           'wager_type' => '',
           'amount' => '',
        ], false, false, $pid);
    }
}
