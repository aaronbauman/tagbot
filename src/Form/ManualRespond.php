<?php

namespace Drupal\tagbot\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a tagbot form.
 */
class ManualRespond extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tagbot_manual_respond';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['mention_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mention ID'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $since_id = \Drupal::state()->get('tagbot_last_mention');
    if ($since_id < $form_state->getValue('mention_id')) {
      $form_state->setErrorByName('mention_id', 'Mention ID has not been queried yet. Please be patient.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('tagbot.manual_respond_confirm', ['mention_id' => $form_state->getValue('mention_id')]);
  }

}
