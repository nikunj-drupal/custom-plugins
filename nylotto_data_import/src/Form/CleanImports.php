<?php

namespace Drupal\nylotto_data_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\nylotto_data_import\ImportData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates the manual importing file.
 */
class CleanImports extends FormBase
{
    use StringTranslationTrait;

    /**
     * Provides the data importing service.
     *
     * @var \Drupal\nylotto_data_import\ImportDataprovidesthedataimportservice
     */
    protected $dataImport;

    /**
     * Class constructor.
     */
    public function __construct(ImportData $dataImportService)
    {
        $this->dataImport = $dataImportService;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        // Instantiates this form class.
        return new static(
        // Load the service required to construct this class.
            $container->get('nylotto.data')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'nylotto_data_import_clean';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $gameids = \Drupal::entityQuery('node')
           ->condition('type', 'game')
           ->execute();
        foreach ($gameids as $gameidskey => $gameidsvalue) {
            $node = \Drupal\node\Entity\Node::load($gameidsvalue);
            $options['all'] = "All";
            $options[$gameidsvalue] = $node->getTitle();
        }
        $form['draw_type'] = [
          '#type' => 'select',
          '#options' => $options,
          '#title' => $this->t('Draw Game Type'),
          '#maxlength' => 255,
          '#description' => $this->t("Draw game type to clean."),
        ];

        $form['paragraph_type'] = [
          '#type' => 'select',
          '#options' => [
            '-none-' => t('Select'),
            'winners_data' => t('Winners Data'),
            'winning_location' => t('Winning Location')
          ],
          '#title' => $this->t('Paragraph Type'),
          '#maxlength' => 255,
          '#description' => $this->t("Paragraph type to clean."),
        ];

        $form['submit'] = [
          '#type' => 'submit',
          '#value' => t('Clear')
        ];
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $draw_type = $form_state->getValue('draw_type');
        $paragraph_type = $form_state->getValue('paragraph_type');
        if (!empty($draw_type)) {
            if ($draw_type == 'all') {
                $drawingIds = \Drupal::entityQuery('drawing')
              ->execute();
            } else {
                $drawingIds = \Drupal::entityQuery('drawing')
              ->condition('game', $draw_type)
              ->execute();
            }
          
            if (!empty($drawingIds)) {
                $drawing_ids = array_chunk($drawingIds, 10, true);
                foreach ($drawing_ids as $chunk) {
                    $batch_operations[] = [
                    'clean_imports_drawing_task',
                    [
                      $chunk,
                    ],
                  ];
                }
            }
            $batch = array(
            'title' => t('Cleaning Import data'),
            'operations' => $batch_operations,
            'finished' => 'clean_imports_finished',
            'file' => drupal_get_path('module', 'nylotto_data_import') . '/nylotto_data_import.module',
          );
            batch_set($batch);
        }
        if (!empty($paragraph_type) && $paragraph_type != '-none-') {
            $paragraphIds = \Drupal::entityQuery('paragraph')
              ->condition('type', $paragraph_type)
              ->execute();
          
            if (!empty($paragraphIds)) {
                $paragraph_ids = array_chunk($paragraphIds, 25, true);
                foreach ($paragraph_ids as $chunk) {
                    $batch_operations[] = [
                    'clean_imports_paragraph_task',
                    [
                      $chunk,
                    ],
                  ];
                }
            }
            $batch = array(
            'title' => t('Cleaning Import data'),
            'operations' => $batch_operations,
            'finished' => 'clean_imports_finished',
            'file' => drupal_get_path('module', 'nylotto_data_import') . '/nylotto_data_import.module',
          );
            batch_set($batch);
        }
    }
}
