<?php

namespace Drupal\nylotto_data_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class HistoricalImports.
 *
 * @package Drupal\nylotto_data_import\Form
 */
class HistoricalImports extends FormBase
{


  /**
   * {@inheritdoc}
   */
    public function getFormId()
    {
        return 'historical_imports_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['historical_file'] = [
          '#type' => 'managed_file',
          '#title' => $this->t('Data File'),
          '#description' => $this->t('Select the data file you wish to import. Upload a CSV file to import.'),
          '#multiple' => false,
          '#required' => true,
          '#upload_location' => 'public://tmp',
          '#upload_validators' => [
            'file_validate_extensions' => ['csv json'],
          ],
        ];

        $form['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Start Import'),
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $fileValue = $form_state->getValue('historical_file');
        $file = entity_load('file', array_shift($fileValue));
        $fileMime = $file->get('filemime')->getString();
        $fileName = '';
        $fileName = $file->getFilename();

        if ($fileMime == 'text/csv') {
            $handle = fopen($file->getFileURI(), "r");
            $all_rows = array();
            $header = fgetcsv($handle);
            array_push($header, "file_name");
            while ($row = fgetcsv($handle)) {
                array_push($row, $fileName);
                $all_rows[] = array_combine($header, $row);
            }

            $historical_data = array_chunk($all_rows, 500, true);
            if (strpos($fileName, 'Quickdraw_Historical') > -1) {
                foreach ($historical_data as $chunk) {
                    // Initialise operations.
                    $batch_operations[] = [
                    '\Drupal\nylotto_data_import\Historical\QuickDrawHistoricalImport::quickDrawImport',
                    [
                      $chunk,
                    ],
                  ];
                }
                $batch = array(
                'title' => t('Historical Data Imports'),
                'operations' => $batch_operations,
                'finished' => '\Drupal\nylotto_data_import\Historical\QuickDrawHistoricalImport::entityImportFinishedCallback',
              );
                batch_set($batch);
            }
        }
        
        if ($fileMime == 'application/octet-stream') {
            $str = file_get_contents($file->getFileURI());
            $jsonData = json_decode($str, true);
            $draw_datail = array_chunk($jsonData['draws'], 5, true);
            if (strpos($fileName, 'pick10') > -1) {
                foreach ($draw_datail as $chunk) {
                    array_push($chunk, $fileName);
                    // Initialise operations.
                    $batch_operations[] = [
                    '\Drupal\nylotto_data_import\Historical\Pick10HistoricalImport::pick10DrawImport',
                    [
                      $chunk,
                    ],
                  ];
                }
                $batch = array(
                'title' => t('Historical Data Imports'),
                'operations' => $batch_operations,
                'finished' => '\Drupal\nylotto_data_import\Historical\QuickDrawHistoricalImport::entityImportFinishedCallback',
              );
                batch_set($batch);
            }
            if (strpos($fileName, 'cash4life') > -1) {
                foreach ($draw_datail as $chunk) {
                    array_push($chunk, $fileName);
                    // Initialise operations.
                    $batch_operations[] = [
                    '\Drupal\nylotto_data_import\Historical\Cash4lifeHistoricalImport::cash4lifeDrawImport',
                    [
                      $chunk,
                    ],
                  ];
                }
                $batch = array(
                'title' => t('Historical Data Imports'),
                'operations' => $batch_operations,
                'finished' => '\Drupal\nylotto_data_import\Historical\QuickDrawHistoricalImport::entityImportFinishedCallback',
              );
                batch_set($batch);
            }
            if (strpos($fileName, 'lotto') > -1) {
                foreach ($draw_datail as $chunk) {
                    array_push($chunk, $fileName);
                    // Initialise operations.
                    $batch_operations[] = [
                    '\Drupal\nylotto_data_import\Historical\LottoHistoricalImport::lottoDrawImport',
                    [
                      $chunk,
                    ],
                  ];
                }
                $batch = array(
                'title' => t('Historical Data Imports'),
                'operations' => $batch_operations,
                'finished' => '\Drupal\nylotto_data_import\Historical\QuickDrawHistoricalImport::entityImportFinishedCallback',
              );
                batch_set($batch);
            }
            if (strpos($fileName, 'mega_millions') > -1) {
                foreach ($draw_datail as $chunk) {
                    array_push($chunk, $fileName);
                    // Initialise operations.
                    $batch_operations[] = [
                    '\Drupal\nylotto_data_import\Historical\MegaMillionsHistoricalImport::megaMillionsDrawImport',
                    [
                      $chunk,
                    ],
                  ];
                }
                $batch = array(
                'title' => t('Historical Data Imports'),
                'operations' => $batch_operations,
                'finished' => '\Drupal\nylotto_data_import\Historical\QuickDrawHistoricalImport::entityImportFinishedCallback',
              );
                batch_set($batch);
            }
            if (strpos($fileName, 'powerball') > -1) {
                foreach ($draw_datail as $chunk) {
                    array_push($chunk, $fileName);
                    // Initialise operations.
                    $batch_operations[] = [
                    '\Drupal\nylotto_data_import\Historical\PowerballHistoricalImport::powerballDrawImport',
                    [
                      $chunk,
                    ],
                  ];
                }
                $batch = array(
                'title' => t('Historical Data Imports'),
                'operations' => $batch_operations,
                'finished' => '\Drupal\nylotto_data_import\Historical\QuickDrawHistoricalImport::entityImportFinishedCallback',
              );
                batch_set($batch);
            }
            if (strpos($fileName, 'take_5') > -1) {
                foreach ($draw_datail as $chunk) {
                    array_push($chunk, $fileName);
                    // Initialise operations.
                    $batch_operations[] = [
                    '\Drupal\nylotto_data_import\Historical\Take5HistoricalImport::take5DrawImport',
                    [
                      $chunk,
                    ],
                  ];
                }
                $batch = array(
                'title' => t('Historical Data Imports'),
                'operations' => $batch_operations,
                'finished' => '\Drupal\nylotto_data_import\Historical\QuickDrawHistoricalImport::entityImportFinishedCallback',
              );
                batch_set($batch);
            }
            if (strpos($fileName, 'numbers') > -1) {
                foreach ($draw_datail as $chunk) {
                    array_push($chunk, $fileName);
                    // Initialise operations.
                    $batch_operations[] = [
                    '\Drupal\nylotto_data_import\Historical\NumbersHistoricalImport::numbersDrawImport',
                    [
                      $chunk,
                    ],
                  ];
                }
                $batch = array(
                'title' => t('Historical Data Imports'),
                'operations' => $batch_operations,
                'finished' => '\Drupal\nylotto_data_import\Historical\QuickDrawHistoricalImport::entityImportFinishedCallback',
              );
                batch_set($batch);
            }
            if (strpos($fileName, 'win4') > -1) {
                foreach ($draw_datail as $chunk) {
                    array_push($chunk, $fileName);
                    // Initialise operations.
                    $batch_operations[] = [
                    '\Drupal\nylotto_data_import\Historical\Win4HistoricalImport::win4DrawImport',
                    [
                      $chunk,
                    ],
                  ];
                }
                $batch = array(
                'title' => t('Historical Data Imports'),
                'operations' => $batch_operations,
                'finished' => '\Drupal\nylotto_data_import\Historical\QuickDrawHistoricalImport::entityImportFinishedCallback',
              );
                batch_set($batch);
            }
        }
    }
}
