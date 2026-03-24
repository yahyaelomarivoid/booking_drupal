<?php

namespace Drupal\booking\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure global settings for the Booking module.
 */
class BookingSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['booking.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'booking_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('booking.settings');

    $form['slot_duration'] = [
      '#type' => 'select',
      '#title' => $this->t('Slot Duration'),
      '#description' => $this->t('Duration of each booking slot in minutes.'),
      '#options' => [
        '30' => $this->t('30 minutes'),
        '60' => $this->t('1 hour'),
        '120' => $this->t('2 hours'),
      ],
      '#default_value' => $config->get('slot_duration') ?? '60',
    ];

    $form['default_start_hour'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Start Hour'),
      '#description' => $this->t('The default starting hour for bookings (e.g., 09:00).'),
      '#default_value' => $config->get('default_start_hour') ?? '09:00',
      '#size' => 5,
    ];

    $form['default_end_hour'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default End Hour'),
      '#description' => $this->t('The default ending hour for bookings (e.g., 18:00).'),
      '#default_value' => $config->get('default_end_hour') ?? '18:00',
      '#size' => 5,
    ];

    $form['notifications_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Email Notifications'),
      '#description' => $this->t('If checked, users will receive a verification code and booking confirmation via email.'),
      '#default_value' => $config->get('notifications_enabled') ?? TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('booking.settings')
      ->set('slot_duration', $form_state->getValue('slot_duration'))
      ->set('default_start_hour', $form_state->getValue('default_start_hour'))
      ->set('default_end_hour', $form_state->getValue('default_end_hour'))
      ->set('notifications_enabled', $form_state->getValue('notifications_enabled'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
