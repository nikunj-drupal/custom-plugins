<?php

namespace Drupal\nylotto_data_import\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that configures forms module settings.
 */
class ImportConfig extends ConfigFormBase
{

  /**
   * {@inheritdoc}
   */
    public function getFormId()
    {
        return 'nylotto_data_import_data_config';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return [
          'nylotto_data_import_data_config.settings',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('nylotto_data_import_data_config.settings');
        $form['track_files'] = array(
        '#type' => 'radios',
        '#title' => t('Track files'),
        '#options' => array('Yes' => 'Yes', 'No' => 'No'),
        '#required' => true,
        '#default_value' => $config->get('track_files'),
        );
        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $track_files = $form_state->getValue('track_files');
        $this->config('nylotto_data_import_data_config.settings')
          ->set('track_files', $track_files)
          ->save();
        parent::submitForm($form, $form_state);
    }
}
