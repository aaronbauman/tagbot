<?php

namespace Drupal\tagbot\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a confirmation form before clearing out the examples.
 */
class ManualRespondConfirm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tagbot_manual_respond';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $mention_id = \Drupal::routeMatch()->getParameter('mention_id');
    $tweet = \Drupal::service('tagbot.twitter_client')->getStatus($mention_id);
    dpm($tweet);
    $url = 'https://twitter.com/i/web/status/' . $mention_id;
    if (!$form_state->isSubmitted()) {
      $this->messenger()
        ->addStatus($this->t('Mention URL: <a href="' . $url . '">' . $url . '</a>'));
    }
    $form['tweet'] = ['#type' => 'value', '#value' => $tweet];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Confirm respond to mention');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('tagbot.manual_respond');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::service('tagbot.responder')->respondToMention($form_state->getValue('tweet'));
    $form_state->setRedirect('tagbot.manual_respond');
  }

}
