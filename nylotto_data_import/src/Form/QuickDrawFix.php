<?php

namespace Drupal\nylotto_data_import\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Quick Draw Fix form.
 */
class QuickDrawFix extends ConfigFormBase
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
        return 'quickdraw_fix';
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

        $form['quickdraw_fix'] = [
          '#type' => 'checkbox',
          '#title' => t('Offset 12am - 4am Quick Draw results to be the following day.'),
          '#default_value' => $config->get('quickdraw_fix')
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
        $config->set('quickdraw_fix', $form_state->getValue('quickdraw_fix'));
        $config->save();

        parent::submitForm($form, $form_state);
    }
}
