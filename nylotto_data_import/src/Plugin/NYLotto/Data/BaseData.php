<?php

namespace Drupal\nylotto_data_import\Plugin\NYLotto\Data;

use Drupal\file\Entity\File;
use Symfony\Component\Yaml\Yaml;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\nylotto_drawing\Entity\Drawing;

/**
 * Provides a base class for the Data import plugins.
 */
class BaseData
{

  /**
   * Yaml schema file used for importing data.
   *
   * @var string
   */
    public $schemaFile = '/yamls/powerball.yml';

    /**
     * Filename prefix used to check if we are on right file.
     *
     * @var string
     */
    public $filenamePrefix = 'Power_';

    /**
     * Plugin id used in processing files.
     *
     * @var string
     */
    public $pluginId = '';

    /**
     * {@inheritdoc}
     */
    public function validFile(File $file)
    {
        $fileURI = $file->getFileURI();
        if (strpos($fileURI, $this->filenamePrefix) > -1) {
            return true;
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function importFile(File $file) {
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
                foreach ($contents as $row) {
                    if (!empty($row)) {
                        $object = $this->parseRow($schema, $row);
                        if ($object) {
                            $object->pluginId = $this->pluginId;
                            $object->file_name = $fileNames;
                            if ($fileName == 'mid' || $fileName == 'eve') {
                                $object->field_draw_time = $fileName;
                            }
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
     * Parse the data row and import into drupal.
     */
    protected function parseRow($schema, $row)
    {
        $isRecordId = strpos($row, '|', 0);
        if ($isRecordId > 1 || $isRecordId == false) {
            $recordTypeId = 0;
        } else {
            $recordTypeId = substr($row, 0, 1);
        }
        if ($recordTypeId !== '') {
            $recordType = isset($schema['record_types'][$recordTypeId]) ? $schema['record_types'][$recordTypeId] : false;
            if ($recordType) {
                $object = new \stdClass();
                foreach ($recordType['fields'] as $field => $fieldInfo) {
                    $object->{$field} = substr($row, $fieldInfo['pos'], $fieldInfo['length']);
                }
                return $object;
            } else {
                $recordType = isset($schema['record_types'][0]) ? $schema['record_types'][0] : false;
                if ($recordType) {
                    $object = new \stdClass();
                    foreach ($recordType['fields'] as $field => $fieldInfo) {
                        $object->{$field} = substr($row, $fieldInfo['pos'], $fieldInfo['length']);
                    }
                    return $object;
                } else {
                    return false;
                }
            }
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function processRow($data)
    {
    }


    public function getOtherWinningNumbers($winning_numbers)
    {
        $new_winning_numbers = '';
        for ($x = 0; $x < strlen($winning_numbers); $x++) {
            $new_winning_numbers .= substr($winning_numbers, $x, 1);

            if ($x !== (strlen($winning_numbers)-1)) {
                $new_winning_numbers .= '|';
            }
        }
        $winning_numbers = $new_winning_numbers;
        return $winning_numbers;
    }

    /**
    * Remove Null from data values.
    * trim extra spaces.
    * remove extra zeros
    * format floats
    */
    public function sanitizeData(&$data)
    {
        $not_include = ['winning_numbers', 'powerball', 'megaball', 'cash_ball', 'bonus', 'power_play'];
        foreach ($data as $delta => &$row) {
          if (!in_array($delta, $not_include)) {
            $data->{$delta} = trim($row);
            if ($row == 'NULL') {
                $data->{$delta} = '';
            }
            if (is_numeric($row)) {
                $data->{$delta} = floatval($row);
                if (strpos($row, '000') > -1) {
                    $data->{$delta} = intval($row);
                }
            }
          }
        }
    }

    /**
     * Returns the paragraph for drawing data.
     */
    public function getDrawingDataParagraph($nid, $date, $time = '')
    {
        $drawDate = new \DateTime();
        $drawDate->setTimeStamp(strtotime($date));
        // Check for a drawing data paragraph for this node.
        $query = \Drupal::entityQuery('drawing')
          ->condition('game', $nid)
          ->condition('field_draw_date', $drawDate->format('Y-m-d'));

        if ($time !== '') {
            $query->condition('field_draw_time', $time);
        }
        $pids = $query->execute();
        return entity_load('drawing', array_values($pids)[0]);
    }

    /**
     * Returns the paragraph for winner data.
     */
    public function getWinnerDataParagraph($pid, $level, $wager = '')
    {
        $query = \Drupal::entityQuery('paragraph')
          ->condition('type', 'winners_data')
          ->condition('field_prize_label', $level)
          ->condition('parent_id', $pid)
          ->condition('parent_type', 'drawing');
        if (!empty($wager)) {
            $query->condition('field_wager_type', $wager);
        }
        $id = $query->execute();
        return entity_load('paragraph', array_values($id)[0]);
    }

    /**
     * Returns the paragraph for location data.
     */
    public function getLocationDataParagraph($pid, $winning_identify)
    {
        $id = \Drupal::entityQuery('paragraph')
          ->condition('type', 'winning_location')
          ->condition('field_winning_loc_identify', $winning_identify)
          ->condition('parent_id', $pid)
          ->condition('parent_type', 'paragraph')
          ->execute();
        return entity_load('paragraph', array_values($id)[0]);
    }

    /**
     * Call this function win adding drawing data.
     * It will check for existing paragraphs and either update or add new ones.
     */
    public function addDrawingData($node, $data, $pid)
    {
        if ($pid != '') {
            $entity = entity_load('drawing', $pid);
            return $this->updateDrawingData($node, $entity, $data);
        // if ($entity = $this->getDrawingDataParagraph($node->id(), $data['draw_date'], $data['draw_time'])) {
    //   return $this->updateDrawingData($node, $entity, $data);
        } else {
            return $this->createDrawingData($node, $data);
        }
    }

    /**
     * Create Drawing Data Paragraph plugin
     */
    public function createDrawingData($node, $data)
    {
        $date = new \DateTime();
        $date->setTimeStamp(strtotime($data['draw_date']));
        $entity = Drawing::create([
          'type' => 'drawing_data',
          'field_draw_date' => $date->format('Y-m-d'),
          'field_winning_numbers_txt' => array_key_exists('winning_numbers', $data) ? $data['winning_numbers'] : '',
          'field_bonus_ball' => array_key_exists('bonus_ball', $data) ? $data['bonus_ball'] : '',
          'field_multiplier' => array_key_exists('multiplier', $data) ? $data['multiplier'] : '',
          'field_draw_time' => array_key_exists('draw_time', $data) ? $data['draw_time'] : '',
          'field_collect_time' => array_key_exists('collect_time', $data) ? $data['collect_time'] : '',
          'field_jackpot' => array_key_exists('jackpot', $data) ? $data['jackpot'] : '',
          'field_total_prizes' => array_key_exists('total_prizes', $data) ? $data['total_prizes'] : '',
          'field_next_draw_date' => array_key_exists('next_draw_date', $data) ? $data['next_draw_date'] : '',
          'field_next_jackpot' => array_key_exists('next_jackpot', $data) ? $data['next_jackpot'] : '',
          'field_jackpot_winners' => array_key_exists('jackpot_winners', $data) ? $data['jackpot_winners'] : '',
          'field_file_name' => array_key_exists('file_name', $data) ? $data['file_name'] : '',
          'name' => array_key_exists('file_name', $data) ? $data['file_name'] : '',
          'game' =>[['target_id' => $node->id()]],
        ]);
        $entity->save();
        return $entity;
    }

    /**
     * Update Drawing Data Paragraph plugin
     */
    public function updateDrawingData($node, $entity, $data)
    {
        if (array_key_exists('winning_numbers', $data)) {
            $entity->set('field_winning_numbers_txt', $data['winning_numbers']);
        }
        if (array_key_exists('bonus_ball', $data)) {
            $entity->set('field_bonus_ball', $data['bonus_ball']);
        }
        if (array_key_exists('multiplier', $data)) {
            $entity->set('field_multiplier', $data['multiplier']);
        }
        if (array_key_exists('draw_time', $data)) {
            $entity->set('field_draw_time', $data['draw_time']);
        }
        if (array_key_exists('total_prizes', $data)) {
            $entity->set('field_total_prizes', $data['total_prizes']);
        }
        if (array_key_exists('collect_time', $data)) {
            $entity->set('field_collect_time', $data['collect_time']);
        }
        if (array_key_exists('jackpot', $data)) {
            $entity->set('field_jackpot', $data['jackpot']);
        }
        if (array_key_exists('next_draw_date', $data)) {
            $entity->set('field_next_draw_date', $data['next_draw_date']);
        }
        if (array_key_exists('next_jackpot', $data)) {
            $entity->set('field_next_jackpot', $data['next_jackpot']);
        }
        if (array_key_exists('jackpot_winners', $data)) {
            $entity->set('field_jackpot_winners', $data['jackpot_winners']);
        }
        if (array_key_exists('file_name', $data)) {
            $entity->set('field_file_name', $data['file_name']);
        }

        if (!empty($entity)) {
            $entity->save();
        }

        return $entity;
    }


    /**
     * Update Drawing Data Paragraph plugin
     */
    public function updateQuickDrawingData($node, $data, $drawids)
    {
        foreach ($drawids as $drawidskey => $drawidsvalue) {
            $entity = entity_load('drawing', $drawidsvalue);
            $entity->set('field_jackpot', $data['jackpot']);
            $entity->set('field_jackpot_winners', $data['jackpot_winners']);
            $entity->set('field_file_name', $data['file_name']);
            $entity->save();
        }

        return $entity;
    }

    /**
     * Add Winner data to a drawing paragraph. It will check if there is existing
     * winners and update them or create new entries.
     */
    public function addWinnersData($entity, $data, $national, $multiplier, $pid)
    {
        if ($pid != '') {
            $paragraph = $this->getWinnerDataParagraph($entity->id(), $data['prize_label'], $data['wager_type']);
            if (empty($paragraph)) {
                return $this->createWinnersData($entity, $data, $national, $multiplier);
            } else {
                return $this->updateWinnersData($entity, $paragraph, $data, $national, $multiplier);
            }
        } else {
            return $this->createWinnersData($entity, $data, $national, $multiplier);
        }
    }

    /**
     * Add Winner data to drawing data.
     */
    public function createWinnersData($entity, $data, $national = false, $multiplier = false)
    {
        $paragraph = Paragraph::create([
          'type' => 'winners_data',
          'field_prize_label' => $data['prize_label'],
          'field_prize_winners' => $data['winners'],
          'field_prize_amount' => $data['amount'],
          'field_wager_type' => $data['wager_type'],
          'parent_id' => $entity->id(),
        ]);

        $paragraph->save();
        if ($national) {
            if ($multiplier) {
                $entity->field_multiplier_national_winner[] = ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
            } else {
                $entity->field_national_winners[] = ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
            }
        } else {
            if ($multiplier) {
                $entity->field_multiplier_local_winners[] = ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
            } else {
                $entity->field_winners[] = ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
            }
        }

        $entity->save();
        return $paragraph;
    }

    /**
     * Update Winner data for drawing data
     */
    public function updateWinnersData($entity, $winnerParagraph, $data)
    {
        $winnerParagraph->set('field_prize_winners', $data['winners']);
        $winnerParagraph->set('field_prize_amount', $data['amount']);
        $winnerParagraph->save();
        return $winnerParagraph;
    }

    public function addLocationData($winnerParagraph, $data)
    {
        if ($winnerParagraph) {
            if ($paragraph = $this->getLocationDataParagraph(
                $winnerParagraph->id(),
                $data['winning_identify']
            )) {
                return $this->updateLocationData($winnerParagraph, $paragraph, $data);
            } else {
                return $this->createLocationData($winnerParagraph, $data);
            }
        }
    }

    /**
     * Create Winning Location.
     */
    public function createLocationData($winnerParagraph, $data)
    {
        $paragraph = Paragraph::create([
          'type' => 'winning_location',
          'field_ticket_type' => $data['ticket_type'],
          'field_retailer_address' => $data['address'],
          'field_retailer_name' => $data['name'],
          'field_retailer_city' => $data['city'],
          'field_winning_county' => $data['winning_county'],
          'field_winning_loc_identify' => $data['winning_identify'],
          'parent_id' => $winnerParagraph->id()
        ]);
        $paragraph->save();
        $winnerParagraph->field_winning_locations[] = ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
        $winnerParagraph->save();
        return $paragraph;
    }

    /**
     * Update Winning location.
     */
    public function updateLocationData($winnerParagraph, $locationParagraph, $data)
    {
        $locationParagraph->set('field_ticket_type', $data['ticket_type']);
        $locationParagraph->set('field_retailer_address', $data['address']);
        $locationParagraph->set('field_retailer_name', $data['name']);
        $locationParagraph->set('field_retailer_city', $data['city']);
        $locationParagraph->set('field_winning_loc_identify', $data['winning_identify']);
        $locationParagraph->set('field_winning_county', $data['winning_county']);
        $locationParagraph->save();
        return $locationParagraph;
    }
}
