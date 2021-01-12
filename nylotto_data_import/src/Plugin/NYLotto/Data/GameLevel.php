<?php

namespace Drupal\nylotto_data_import\Plugin\NYLotto\Data;

use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Yaml\Yaml;
use \Drupal\node\Entity\Node;

/**
 * GameLevel data import plugin.
 *
 * @NyDataType(
 *   id = "game_level"
 * )
 */
class GameLevel extends BaseData
{
    use StringTranslationTrait;

    /**
     * GameLevel yml file name.
     *
     * @var string
     */
    public $schemaFile = '/yamls/game_level.yml';

    /**
     * GameLevel file prefix for import.
     *
     * @var string
     */
    public $filenamePrefix = 'Instant_Games_';

    /**
     * GameLevel plugin id name.
     *
     * @var string
     */
    public $pluginId = 'game_level';

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->pluginId = 'game_level';
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
        // $this->pluginId = 'game_level';
        // parent::importFile($file);
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
                foreach ($contents as $row) {
                    if (!empty($row)) {
                        $object = $this->parseRow($schema, $row);
                        $object->file_name = $fileNames;
                        if ($object) {
                            $object->pluginId = "game_level";
                            $this->processRow($object);
                        }
                    }
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
        //foreach ($data as $dataKey => $dataValue) {
        $id = \Drupal::entityQuery('node')
              ->condition('type', 'scratch_off')
              ->condition('field_game_number', $data->game_number)
              ->execute();

        if (empty($id) || $id == '') {
            if ($data) {
                $this->CreateGameLevel($data);
            }
        } else {
            if ($data) {
                $nid = array_shift($id);
                $this->UpdateGameLevel($nid, $data);
            }
        }

        //}
    }

    /**
     * Imports the data for the drawing row.
     */
    protected function CreateGameLevel($data)
    {
        $user = \Drupal::currentUser()->id();
        if ($user) {
            $uid = $user;
        } else {
            $uid = 1;
        }
        //Creating new game for game level
        $node = Node::create([
           'type' => 'scratch_off',
           'title' => $data->game_name,
           'field_game_name' => $data->game_name,
           'field_game_number' => $data->game_number,
           'field_release_date' => $data->start_date,
           'field_prizes_thru_date' => $data->prizes_thru_date,
           //'field_game_id'=>'game_level',
           'status'=> 0,
           'field_imported_file_name' => $data->file_name,
           'created'=> REQUEST_TIME,
           'uid' => $uid,
        ]);
        $node->setPublished(false);
        $node->save();
    }

    /**
     * Imports the data for the drawing row.
     */
    protected function UpdateGameLevel($nid, $data)
    {
        $node = Node::load($nid);
        $node->set('title', $data->game_name);
        $node->set('field_game_name', $data->game_name);
        $node->set('field_game_number', $data->game_number);
        $node->set('field_release_date', $data->start_date);
        $node->set('field_prizes_thru_date', $data->prizes_thru_date);
        $node->set('field_imported_file_name', $data->file_name);
        $node->save();
    }
}
