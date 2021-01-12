<?php

namespace Drupal\nylotto_data_import\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure example settings for this site.
 */
class FTPKeySettings extends ConfigFormBase
{

  /**
   * Config settings.
   *
   * @var string
   */
    const SETTINGS = 'nylotto_custom_json.ftp.settings';

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'ftp_key';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return [
          static::SETTINGS,
        ];
    }

    /**
     * This form allows us to setup basic page nodes to be used for the front end
     * pages that require more flexible layout than the other programmatic pages.
     *
     * This is then returned into a restful endpoint that is consumable by the front end.
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config(static::SETTINGS);

        $form['private_key'] = [
          '#type' => 'textarea',
          '#title' => t('Private Key'),
          '#default_value' => $config->get('private_key')
        ];

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        // Retrieve the configuration.
        $config = $this->configFactory->getEditable(static::SETTINGS);
        $config->set('private_key', $form_state->getValue('private_key'));
        $config->save();

        parent::submitForm($form, $form_state);
    }
}
