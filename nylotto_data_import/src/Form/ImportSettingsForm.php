<?php

namespace Drupal\nylotto_data_import\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Defines a form that configures forms module settings.
 */
class ImportSettingsForm extends ConfigFormBase
{

  /**
   * {@inheritdoc}
   */
    public function getFormId()
    {
        return 'nylotto_data_import_cron_config';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return [
          'nylotto_data_import_cron_config.settings',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('nylotto_data_import_cron_config.settings');
        $default_cron_on_api = $default_cron_on_ftp = $default_cron_on_retailor = '';
        $form['ftp_download'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('FTP Download'),
          '#default_value' => $config->get('ftp_download'),
        ];
        $form['retailer_import'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Retailer Import'),
          '#default_value' => $config->get('retailer_import'),
        ];
        $form['api_import'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('API Import'),
          '#default_value' => $config->get('api_import'),
        ];
        $form['api'] = [
          '#type' => 'fieldset',
          '#title' => 'Api import run time configuration',
          '#description' => t("<ul><li>If Interval is enable please enter value in '+xhour +xminute(i.e. +1hour +30minutes) format. In that case cron will run after every X Hours X Minutes.</li><li>If Interval is not enable please enter value in 'H:i:s(i.e. 10:00:00AM) format. In that case, cron will run import at H:i:s, once in a day.</li></ul>"),
          '#tree' => TRUE
        ];
        $form['api']['cron_on_api_checkbox'] = array(
          '#type' => 'checkbox',
          '#title' => $this->t('Interval'),
          '#default_value' => $config->get('cron_on_api_checkbox'),
          '#description' => 'Cron will run in interval, if it is enable',
        );
        $form['api']['cron_on_api_time'] = [
          '#type' => 'textfield',
          '#title' => t('Api Import Run Time'),
          '#default_value' => $config->get('cron_on_api_time'),
          '#size' => 45,
          // Hostnames can be 255 characters long.
          '#maxlength' => 255,

        ];
         $form['ftp'] = [
          '#type' => 'fieldset',
          '#title' => 'FTP run time configuration',
          '#description' => t("<ul><li>If Interval is enable please enter value in '+xhour +xminute(i.e. +1hour +30minutes) format. In that case cron will run after every X Hours X Minutes.</li><li>If Interval is not enable please enter value in 'H:i:s(i.e. 10:00:00AM) format. In that case, cron will run import at H:i:s, once in a day.</li></ul>"),
          '#tree' => TRUE
        ];
        $form['ftp']['cron_on_ftp_checkbox'] = array(
          '#type' => 'checkbox',
          '#title' => $this->t('Interval'),
          '#default_value' => $config->get('cron_on_ftp_checkbox'),
          '#description' => 'Cron will run in interval, if it is enable',
        );
         $form['ftp']['cron_on_ftp_time'] = array(
          '#type' => 'textfield',
          '#title' => t('FTP Import Run Time'),
          '#default_value' => $config->get('cron_on_ftp_time'),
          '#size' => 45,
          // Hostnames can be 255 characters long.
          '#maxlength' => 255,
        );
         $form['retailor'] = [
          '#type' => 'fieldset',
          '#title' => 'Retailor run time configuration',
          '#description' => t("<ul><li>If Interval is enable please enter value in '+xhour +xminute(i.e. +1hour +30minutes) format. In that case cron will run after every X Hours X Minutes.</li><li>If Interval is not enable please enter value in 'H:i:s(i.e. 10:00:00AM) format. In that case, cron will run import at H:i:s, once in a day.</li></ul>"),
          '#tree' => TRUE
        ];
        $form['retailor']['cron_on_retailor_checkbox'] = array(
          '#type' => 'checkbox',
          '#title' => $this->t('Interval'),
          '#default_value' => $config->get('cron_on_retailor_checkbox'),
          '#description' => 'Cron will run in interval, if it is enable',
        );
         $form['retailor']['cron_on_retailor_time'] = array(
          '#type' => 'textfield',
          '#title' => t('Retailor Import Run Time'),
          '#default_value' => $config->get('cron_on_retailor_time'),
          '#size' => 45,
          // Hostnames can be 255 characters long.
          '#maxlength' => 255,
        );
        $form['import'] = array(
          '#type' => 'submit',
          '#weight' => 999,
          '#limit_validation_errors' => array() ,
          '#button_type' => 'danger',
          '#submit' => ['::importAPIFiles'],
          '#value' => t('Import FTP Files') ,
        );

        $form['clear_cron'] = array(
          '#type' => 'submit',
          '#weight' => 998,
          '#limit_validation_errors' => array() ,
          '#button_type' => 'danger',
          '#submit' => ['::clearCron'],
          '#value' => t('Clear Cron Process') ,
        );

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {   
        $values = $form_state->getValues();

        $ftp_download = $form_state->getValue('ftp_download');
        $retailer_import = $form_state->getValue('retailer_import');
        $api_import = $form_state->getValue('api_import');
        $cron_on_api_checkbox = $values['api']['cron_on_api_checkbox'];
        $cron_on_api_time = $values['api']['cron_on_api_time'];
        $cron_on_ftp_checkbox = $values['ftp']['cron_on_ftp_checkbox'];
        $cron_on_ftp_time = $values['ftp']['cron_on_ftp_time'];
        $cron_on_retailor_checkbox = $values['retailor']['cron_on_retailor_checkbox'];
        $cron_on_retailor_time = $values['retailor']['cron_on_retailor_time'];
        $this->config('nylotto_data_import_cron_config.settings')
          ->set('ftp_download', $ftp_download)
          ->set('retailer_import', $retailer_import)
          ->set('api_import', $api_import)
          ->set('cron_on_api_checkbox', $cron_on_api_checkbox)
          ->set('cron_on_api_time', $cron_on_api_time)
          ->set('cron_on_ftp_checkbox', $cron_on_ftp_checkbox)
          ->set('cron_on_ftp_time', $cron_on_ftp_time)
          ->set('cron_on_retailor_checkbox', $cron_on_retailor_checkbox)
          ->set('cron_on_retailor_time', $cron_on_retailor_time)
          ->save();
        parent::submitForm($form, $form_state);
    }

    public function importAPIFiles(array &$form, FormStateInterface $form_state)
    {
        nylotto_ftp_download_cron();
        \Drupal::messenger()->addMessage(t('FTP files are imported successfully.'), 'success');
    }

    public function clearCron(array &$form, FormStateInterface $form_state)
    {
        $database = \Drupal::database();
        $database->query("DELETE FROM semaphore WHERE name = 'cron'");
        \Drupal::lock()->release('cron');
        \Drupal::messenger()->addMessage(t('Cron process is clear.'), 'success');
    }
}
