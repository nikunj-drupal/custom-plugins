<?php

namespace Drupal\nylotto_data_import\Plugin\NYLotto\Data;

use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Yaml\Yaml;
use \Drupal\node\Entity\Node;

/**
 * GameDetail data import plugin.
 *
 * @NyDataType(
 *   id = "game_detail"
 * )
 */
class GameDetail extends BaseData
{
    use StringTranslationTrait;

    /**
     * GameDetail yml file name.
     *
     * @var string
     */
    public $schemaFile = '/yamls/game_detail.yml';

    /**
     * GameDetail file prefix for import.
     *
     * @var string
     */
    public $filenamePrefix = 'Instant_Levels_';

    /**
     * GameDetail plugin id name.
     *
     * @var string
     */
    public $pluginId = 'game_detail';

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->pluginId = 'game_detail';
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
    public function importFile(File $file) {
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
                $objects = new \stdClass();
                foreach ($contents as $row) {
                    if (!empty($row)) {
                        $object = $this->parseRow($schema, $row);
                        $object->file_name = $fileNames;
                        if ($object) {
                            $scratch_off[$object->game_number][] = $object;
                        }
                    }
                }
                foreach ($scratch_off as $key => $value) {
                    $objects->pluginId = 'game_detail';
                    $objects->values = $value;
                    $this->processRow($objects);
                }
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
        $games = $data->values;
        $this->drawing($games);
    }

    /**
     * Imports the data for the drawing row.
     */
    protected function drawing($games)
    {
        $ids = \Drupal::entityQuery('node')
        ->condition('type', 'scratch_off')
        ->condition('field_game_number', $games[0]->game_number)
        ->execute();
        if (!empty($ids)) {
            $node = entity_load('node', array_shift($ids));
            if ($node) {
                $gameRecord = end($games);
                $game = $node->get('field_game_number')->getString();
                $Odds = $node->get('field_odds')->getValue();
                $record_order = array_reverse($games);
                if (empty($Odds) || $Odds == '') {
                    $field_section_title = 0;
                    foreach ($record_order as $games_key => $games_value) {
                        $field_section_title++;
                        $identifier = 'game-'.$game.'-'.$field_section_title;
                        $paragraph = Paragraph::create([
                          'type' => 'odds_and_prizes',
                          'field_section_title' => $identifier,
                          'field_last_updated' => date('Y-m-d\TH:i:s', time()),
                          'field_prizes_remaining' => (string)((int)$games_value->number_unpaid),
                          'field_prizes_paid_out' => (string)((int)$games_value->number_paid),
                          'parent_id' => $node->id(),
                        ]);
                        $paragraph->save();
                        $node->field_odds[] = ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
                    }
                    $node->set('field_top_prize_remaining', (string)((int)$gameRecord->number_unpaid));
                    $node->set('field_prizes_paid_out', (string)((int)$gameRecord->number_paid));
                    $node->setNewRevision(false);
                    $node->save();
                } else {
                    $field_section_title = 0;
                    foreach ($record_order as $key => $value) {
                        $field_section_title++;
                        $identifier = 'game-'.$game.'-'.$field_section_title;
                        $id = \Drupal::entityQuery('paragraph')
                          ->condition('type', 'odds_and_prizes')
                          ->condition('field_section_title', $identifier)
                          ->condition('parent_id', $node->id())
                          ->execute();
                        if (!empty($id) && $id != '') {
                            $paragraph = entity_load('paragraph', array_shift($id));
                            $paragraph->set('field_last_updated', date('Y-m-d\TH:i:s', time()));
                            $paragraph->set('field_prizes_remaining', (string)((int)$value->number_unpaid));
                            $paragraph->set('field_prizes_paid_out', (string)((int)$value->number_paid));
                            $paragraph->save();
                        } else {
                            $paragraph = Paragraph::create([
                              'type' => 'odds_and_prizes',
                              'field_section_title' => $identifier,
                              'field_last_updated' => date('Y-m-d\TH:i:s', time()),
                              'field_prizes_remaining' => (string)((int)$value->number_unpaid),
                              'field_prizes_paid_out' => (string)((int)$value->number_paid),
                              'parent_id' => $node->id(),
                            ]);
                            $paragraph->save();
                            $node->field_odds[] = ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
                        }
                    }
                    $node->set('field_top_prize_remaining', (string)((int)$gameRecord->number_unpaid));
                    $node->set('field_prizes_paid_out', (string)((int)$gameRecord->number_paid));
                    $node->setNewRevision(false);
                    $node->save();
                }
            }
        } else {
            $this->CreateGameLevel($games);
        }
    }

    /**
     * Imports the data for the drawing row.
     */
    protected function CreateGameLevel($games)
    {
        $user = \Drupal::currentUser()->id();
        if ($user) {
            $uid = $user;
        } else {
            $uid = 1;
        }
        $gameRecord = end($games);
        $game = $games[0];
        $gameNumber = $game->game_number;
        $record_order = array_reverse($games);
        $field_section_title = 0;
        //Creating new game for game level
        $node = Node::create([
           'type' => 'scratch_off',
           'title' => 'ganme-'. $gameNumber,
           'field_game_name' => 'ganme-'. $gameNumber,
           'field_game_number' => $gameNumber,
           'field_top_prize_remaining' => (string)((int)$gameRecord->number_unpaid),
           'field_prizes_paid_out' => (string)((int)$gameRecord->number_paid),
           //'field_game_id'=>'game_level',
           'field_imported_file_name' => $game->file_name,
           'created'=> REQUEST_TIME,
           'uid' => $uid,
        ]);
        foreach ($record_order as $games_key => $games_value) {
            $field_section_title++;
            $identifier = 'game-'.$gameNumber.'-'.$field_section_title;
            $paragraph = Paragraph::create([
              'type' => 'odds_and_prizes',
              'field_section_title' => $identifier,
              'field_prizes_remaining' => (string)((int)$games_value->number_unpaid),
              'field_prizes_paid_out' => (string)((int)$games_value->number_paid),
              'parent_id' => $node->id(),
            ]);
            $paragraph->save();
            $node->field_odds[] = ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()];
        }
        $node->setPublished(false);
        $node->save();
    }
}
