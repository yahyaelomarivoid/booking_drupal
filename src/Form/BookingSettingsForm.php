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

    $form['export_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Export Settings'),
      '#open' => TRUE,
    ];

    $form['export_settings']['export_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Export Mode'),
      '#options' => [
        'global' => $this->t('Global (All Bookings)'),
        'agency' => $this->t('By Agency'),
        'adviser' => $this->t('By Adviser'),
      ],
      '#default_value' => $config->get('export_mode') ?? 'global',
    ];

    $booking_service = \Drupal::service('booking.service');

    $form['export_settings']['export_agency'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Agency'),
      '#options' => $booking_service->getAgencyOptions(),
      '#default_value' => $config->get('export_agency'),
      '#states' => [
        'visible' => [
          ':input[name="export_mode"]' => ['value' => 'agency'],
        ],
      ],
    ];

    $form['export_settings']['export_adviser'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Adviser'),
      '#options' => $booking_service->getAdviserOptions(),
      '#default_value' => $config->get('export_adviser'),
      '#states' => [
        'visible' => [
          ':input[name="export_mode"]' => ['value' => 'adviser'],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $start = $form_state->getValue('default_start_hour');
    $end = $form_state->getValue('default_end_hour');

    $time_pattern = '/^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/';

    if (!empty($start) && !preg_match($time_pattern, $start)) {
      $form_state->setErrorByName('default_start_hour', $this->t('The start hour must be in HH:MM format (e.g., 09:00).'));
    }

    if (!empty($end) && !preg_match($time_pattern, $end)) {
      $form_state->setErrorByName('default_end_hour', $this->t('The end hour must be in HH:MM format (e.g., 18:00).'));
    }

    parent::validateForm($form, $form_state);
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
      ->set('export_mode', $form_state->getValue('export_mode'))
      ->set('export_agency', $form_state->getValue('export_agency'))
      ->set('export_adviser', $form_state->getValue('export_adviser'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
