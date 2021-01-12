<?php

namespace Drupal\nylotto_data_import\Plugin\NYLotto\Data;

use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * QuickDraw data import plugin.
 *
 * @NyDataType(
 *   id = "quick_draw"
 * )
 */
class QuickDraw extends BaseData
{
    use StringTranslationTrait;

    /**
     * QuickDraw yml file name.
     *
     * @var string
     */
    public $schemaFile = '/yamls/quick_draw.yml';

    /**
     * QuickDraw file prefix for import.
     *
     * @var string
     */
    public $filenamePrefix = 'QuickDraw_';

    /**
     * QuickDraw plugin id name.
     *
     * @var string
     */
    public $pluginId = 'quick_draw';

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->pluginId = 'quick_draw';
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
        $this->pluginId = 'quick_draw';
        parent::importFile($file);
    }

    /**
     * {@inheritdoc}
     */
    public function processRow($data)
    {
        \Drupal::logger('nylotto_importer')->notice("Processing row for QuickDraw");
        $ids = \Drupal::entityQuery('node')
          ->condition('type', 'game')
          ->condition('title', 'Quick Draw')
          ->execute();
        $node = entity_load('node', array_values($ids)[0]);
        if ($node) {
            $this->drawing($node, $data);
        } else {
            // We could not find the QuickDraw node!!!!
            \Drupal::logger('nylotto_importer')->error("No drawing date found for Quick draw import. " . print_r($data, true));
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
        $pids = $query->execute();

        $this->sanitizeData($record);
        if ($record->draw_date !== '' && !empty($pids)) {
            $drawingDataParagraph = $this->updateQuickDrawingData($node, [
              'draw_date' => $record->draw_date,
              'jackpot' => $record->prizes_won,
              'jackpot_winners' => $record->winners,
              'file_name' => $record->file_name,
            ], $pids);
        }
    }
}
