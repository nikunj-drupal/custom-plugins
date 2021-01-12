<?php

namespace Drupal\nylotto_data_import\Plugin\NYLotto\Data;

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Symfony\Component\Yaml\Yaml;

/**
 * Regional report data import plugin.
 *
 * @NyDataType(
 *   id = "reginal_report"
 * )
 */
class RegionalReport extends BaseData
{


  /**
   * Regional report yml file name.
   *
   * @var string
   */
    public $schemaFile = '/yamls/regional_report.yml';

    /**
     * Regional report file prefix for import.
     *
     * @var string
     */
    public $filenamePrefix = 'Regional_';

    /**
     * Regional report plugin id name.
     *
     * @var string
     */
    public $pluginId = 'reginal_report';

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->pluginId = 'reginal_report';
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

    // $this->pluginId = 'reginal_report';
        // parent::importFile($file);
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
        \Drupal::logger('nylotto_importer')->error("Processing row for RegionalReport");
        if (!empty($data->location)) {
            $title = isset($data->location) && $data->location ? $data->location : null;
            // Check if we have a title.
            if (!$title) {
                throw new \Exception('Missing Title');
            }

            $startDate = new \DateTime();
            $startDate->setTimestamp(strtotime($data->week_start_date));

            $endDate = new \DateTime();
            $endDate->setTimestamp(strtotime($data->week_end_date));
            $tids = \Drupal::entityQuery('taxonomy_term')
                  ->condition('field_region_id', $data->location)
                  ->execute();
            $term = entity_load('taxonomy_term', array_shift($tids));
            $ids = \Drupal::entityQuery('node')
                  ->condition('type', 'reginoal_reports')
                  ->condition('title', $term->label())
                  ->condition('field_start_date', $startDate->format('Y-m-d'))
                  ->condition('field_end_date', $endDate->format('Y-m-d'))
                  ->execute();
            if (!empty($ids)) {
                $node = entity_load('node', array_shift($ids));
            }

            if (empty($node) && ($term)) {
                $recode = [
                      'type' => 'reginoal_reports',
                      'title' => $term->label(),
                      'field_start_date' => $startDate->format('Y-m-d'),
                      'field_end_date' => $endDate->format('Y-m-d'),
                      'field_prizes_won' => $data->prizes_won,
                      'field_lotto_prizes_won' => $data->lotto_prizes_won,
                      'field_numbers_prizes_won' => $data->numbers_prizes_won,
                      'field_win4_prizes_won' => $data->win4_prizes_won,
                      'field_pick_10_prizes_won' => $data->pick_10_prizes_won,
                      'field_take_five_prizes_won' => $data->take_five_prizes_won,
                      'field_quick_draw_prizes_won' => $data->quick_draw_prizes_won,
                      'field_instants_prizes_won' => $data->instants_prizes_won,
                      'field_mega_millions_prizes_won' => $data->mega_millions_prizes_won,
                      'field_sweet_millions_prizes_won' => $data->sweet_million_prizes_won,
                      'field_powerball_prizes_won' => $data->powerball_prizes_won,
                      'field_cash_4_life_prizes_won' => $data->cash_4_life_prizes_won,
                      'field_monopoly_millionaires_club' => $data->monopoly_millionaires_clud_prizes_won,
                      'field_top_instant_game' => $data->top_instant_game,
                      'field_2nd_instant_game' => $data->second_instant_game,
                      'field_3rd_instant_game' => $data->third_instant_game,
                      'field_earned_for_education' => $data->earned_for_education,
                      'field_file_name' => $data->file_name,
                      'field_region' => ['target_id' => $term->id()]
                    ];
                $node = Node::create($recode);
                $changed = $node->getChangedTime();
                $node->setNewRevision(false);
                $node->setChangedTime($changed);
                $node->setRevisionLogMessage('Data Feed Import for ' . $data->location);
                $node->setRevisionUserId(1);
                $node->save();
            } elseif (($term)) {
                $node->set('title', $term->label());
                $node->set('field_region', ['target_id' => $term->id()]);
                $node->set('field_start_date', $startDate->format('Y-m-d'));
                $node->set('field_end_date', $endDate->format('Y-m-d'));
                $node->set('field_prizes_won', $data->prizes_won);
                $node->set('field_lotto_prizes_won', $data->lotto_prizes_won);
                $node->set('field_numbers_prizes_won', $data->numbers_prizes_won);
                $node->set('field_win4_prizes_won', $data->win4_prizes_won);
                $node->set('field_pick_10_prizes_won', $data->pick_10_prizes_won);
                $node->set('field_take_five_prizes_won', $data->take_five_prizes_won);
                $node->set('field_quick_draw_prizes_won', $data->quick_draw_prizes_won);
                $node->set('field_instants_prizes_won', $data->instants_prizes_won);
                $node->set('field_mega_millions_prizes_won', $data->mega_millions_prizes_won);
                $node->set('field_sweet_millions_prizes_won', $data->sweet_million_prizes_won);
                $node->set('field_powerball_prizes_won', $data->powerball_prizes_won);
                $node->set('field_cash_4_life_prizes_won', $data->cash_4_life_prizes_won);
                $node->set('field_monopoly_millionaires_club', $record->monopoly_millionaires_clud_prizes_won);
                $node->set('field_top_instant_game', $data->top_instant_game);
                $node->set('field_2nd_instant_game', $data->second_instant_game);
                $node->set('field_3rd_instant_game', $data->third_instant_game);
                $node->set('field_file_name', $data->file_name);
                $node->set('field_earned_for_education', $data->earned_for_education);
                $changed = $node->getChangedTime();
                $node->setNewRevision(true);
                $node->setChangedTime($changed);
                $node->setRevisionLogMessage('Data Feed Import for ' . $data->location);
                $node->setRevisionUserId(1);
                $node->save();
            }
        }
    }
}
