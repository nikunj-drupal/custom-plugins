<?php

namespace Drupal\nylotto_data_import\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Builds the form to delete FTP Source entities.
 */
class ImportFtpSourceDeleteForm extends EntityConfirmFormBase
{

  /**
   * {@inheritdoc}
   */
    public function getQuestion()
    {
        return $this->t('Are you sure you want to delete %name?', ['%name' => $this->entity->label()]);
    }

    /**
     * {@inheritdoc}
     */
    public function getCancelUrl()
    {
        return new Url('entity.import_ftp_source.collection');
    }

    /**
     * {@inheritdoc}
     */
    public function getConfirmText()
    {
        return $this->t('Delete');
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $this->entity->delete();

        $this->messenger()->addMessage(
            $this->t('content @type: deleted @label.', [
              '@type' => $this->entity->bundle(),
              '@label' => $this->entity->label(),
            ])
        );

        $form_state->setRedirectUrl($this->getCancelUrl());
    }
}
