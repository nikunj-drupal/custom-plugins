<?php

namespace Drupal\nylotto_data_import\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ImportFtpSourceForm.
 */
class ImportFtpSourceForm extends EntityForm
{

  /**
   * {@inheritdoc}
   */
    public function form(array $form, FormStateInterface $form_state)
    {
        $form = parent::form($form, $form_state);

        $import_ftp_source = $this->entity;
        $form['label'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Label'),
          '#maxlength' => 255,
          '#default_value' => $import_ftp_source->label(),
          '#description' => $this->t("Label for the FTP Source."),
          '#required' => true,
        ];

        $form['id'] = [
          '#type' => 'machine_name',
          '#default_value' => $import_ftp_source->id(),
          '#machine_name' => [
            'exists' => '\Drupal\nylotto_data_import\Entity\ImportFtpSource::load',
          ],
          '#disabled' => !$import_ftp_source->isNew(),
        ];

        /* You will need additional form elements for your custom properties. */
        $form['server'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Server'),
          '#maxlength' => 255,
          '#default_value' => $import_ftp_source->get('server'),
          '#description' => $this->t("Server for the FTP Source."),
          '#required' => true,
        ];

        $form['port'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Port'),
          '#maxlength' => 255,
          '#default_value' => $import_ftp_source->get('port'),
          '#description' => $this->t("Port for the FTP Source."),
          '#required' => true,
        ];

        $form['path'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Path'),
          '#maxlength' => 255,
          '#default_value' => $import_ftp_source->get('path'),
          '#description' => $this->t("Port for the FTP Source."),
          '#required' => true,
        ];

        $form['user'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Username'),
          '#maxlength' => 255,
          '#default_value' => $import_ftp_source->get('user'),
          '#description' => $this->t("Username for the FTP Source."),
          '#required' => true,
        ];

        $form['password'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Password'),
          '#maxlength' => 255,
          '#default_value' => $import_ftp_source->get('password'),
          '#description' => $this->t("Password for the FTP Source."),
        ];

        $form['cron_type'] = [
          '#type' => 'select',
          '#options' => [
            'weekly' => t('Weekly'),
            'daily' => t('Daily')
          ],
          '#title' => $this->t('Cron Type'),
          '#maxlength' => 255,
          '#default_value' => $import_ftp_source->get('cron_type'),
          '#description' => $this->t("Cron type for this FTP Source."),
          '#required' => true,
        ];

        $form['import_schedule'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Import Schedule'),
          '#maxlength' => 255,
          '#default_value' => $import_ftp_source->get('import_schedule'),
          '#description' => $this->t("Enter the time of day or day of the week to run this importer. Enter the time first, the day second."),
          '#required' => true,
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $form, FormStateInterface $form_state)
    {
        $import_ftp_source = $this->entity;
        $status = $import_ftp_source->save();

        switch ($status) {
            case SAVED_NEW:
                $this->messenger()->addMessage($this->t('Created the %label FTP Source.', [
                  '%label' => $import_ftp_source->label(),
                ]));

                break;

            default:
                $this->messenger()->addMessage($this->t('Saved the %label FTP Source.', [
                  '%label' => $import_ftp_source->label(),
                ]));
        }
        $form_state->setRedirectUrl($import_ftp_source->toUrl('collection'));
    }
}
