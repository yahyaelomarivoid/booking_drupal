<?php

namespace Drupal\booking\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Template\Attribute;

/**
 * Trait for the booking form steps.
 */
trait BookingFormTrait
{

  /**
   * Builds the agency selection step.
   */
  public function buildAgencyOptionsStep(array &$form, FormStateInterface $form_state)
  {
    try {
      $stored = $form_state->get('stored_values') ?: [];
      $options = $this->bookingService->getAgencyRichOptions();
      if (empty($options)) {
        $this->messenger()->addWarning($this->t('No agencies available at the moment.'));
      }

      $form['agency_options'] = [
        '#type' => 'radios',
        '#title' => $this->t('Select an agency'),
        '#options' => $options,
        '#required' => TRUE,
        '#default_value' => $stored['agency_options'] ?? NULL,
        '#attributes' => ['class' => ['booking-cards-wrapper']],
        '#attached' => [
          'library' => ['booking/form_cards'],
        ],
        '#ajax' => [
          'callback' => '::ajaxCallback',
          'wrapper' => 'booking-form-wrapper',
        ],
      ];
    } catch (\Exception $e) {
      $this->logger('booking')->error('Error in buildAgencyOptionsStep: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('An error occurred while loading agencies.'));
    }
  }

  /**
   * Builds the adviser selection step.
   */
  public function buildAdviserStep(array &$form, FormStateInterface $form_state)
  {
    try {
      $stored = $form_state->get('stored_values') ?: [];
      $agencyId = $stored['agency_options'] ?? NULL;
      $serviceId = $stored['service_options'] ?? NULL;
      $options = $this->bookingService->getAdviserRichOptions($agencyId, $serviceId);
      if (empty($options)) {
        $this->messenger()->addWarning($this->t('No advisers available for the selected agency.'));
      }
      $form['adviser_options'] = [
        '#type' => 'radios',
        '#title' => $this->t('Select an adviser'),
        '#options' => $options,
        '#required' => TRUE,
        '#default_value' => $stored['adviser_options'] ?? NULL,
        '#attributes' => ['class' => ['booking-cards-wrapper']],
        '#attached' => [
          'library' => ['booking/form_cards'],
        ],
        '#ajax' => [
          'callback' => '::ajaxCallback',
          'wrapper' => 'booking-form-wrapper',
        ],
      ];
    } catch (\Exception $e) {
      $this->logger('booking')->error('Error in buildAdviserStep: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('An error occurred while loading advisers.'));
    }
  }

  /**
   * Builds the service selection step.
   */
  public function buildServiceOptionsStep(array &$form, FormStateInterface $form_state)
  {
    try {
      $stored = $form_state->get('stored_values') ?: [];
      $options = $this->bookingService->getServiceRichOptions();
      $form['service_options'] = [
        '#type' => 'radios',
        '#title' => $this->t('Select a service'),
        '#options' => $options,
        '#required' => TRUE,
        '#default_value' => $stored['service_options'] ?? NULL,
        '#attributes' => ['class' => ['booking-cards-wrapper']],
        '#attached' => [
          'library' => ['booking/form_cards'],
        ],
        '#ajax' => [
          'callback' => '::ajaxCallback',
          'wrapper' => 'booking-form-wrapper',
        ],
      ];
    } catch (\Exception $e) {
      $this->logger('booking')->error('Error in buildServiceOptionsStep: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('An error occurred while loading services.'));
    }
  }

  /**
   * Builds the date and time selection step.
   */
  public function buildDateTimeStep(array &$form, FormStateInterface $form_state)
  {
    $stored = $form_state->get('stored_values') ?: [];
    $adviserId = $stored['adviser_options'] ?? NULL;

    $form['calendar_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'calendar-wrapper'],
    ];

    $form['calendar_wrapper']['calendar_display'] = [
      '#markup' => '<div id="calendar-container"></div>',
      '#attached' => [
        'library' => ['booking/booking_calendar'],
      ],
    ];

    $form['calendar_wrapper']['booking_date_selection'] = [
      '#type' => 'textfield',
      '#attributes' => ['style' => 'display:none;'],
      '#default_value' => $stored['date_only'] ?? NULL,
      '#parents' => ['booking_date_selection'],
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'booking-form-wrapper',
        'event' => 'change',
      ],
    ];

    $selectedDate = $form_state->getValue('booking_date_selection') ?? $stored['date_only'] ?? NULL;

    if ($selectedDate && $adviserId) {
      $availableSlots = $this->bookingService->getAvailableTimeSlots((int) $adviserId, $selectedDate);
      
      $options = [];
      foreach ($availableSlots as $slot) {
        // Create a nice label e.g. "09:00 - 10:00"
        $hour = (int) explode(':', $slot)[0];
        $next = $hour + 1;
        $label = $slot . ' - ' . sprintf('%02d:00', $next);
        $options[$slot] = $label;
      }

      if (empty($options)) {
        $form['no_slots'] = [
          '#markup' => '<div class="messages messages--warning">' . $this->t('No available slots for this date.') . '</div>',
        ];
      } else {
        $form['booking_time_slot'] = [
          '#type' => 'radios',
          '#title' => $this->t('Choose a time slot (1 hour)'),
          '#options' => $options,
          '#required' => TRUE,
          '#default_value' => $stored['time_slot'] ?? NULL,
          '#attributes' => ['class' => ['booking-cards-wrapper']],
          '#attached' => [
            'library' => ['booking/form_cards'],
          ],
        ];
      }
    }
  }

  /**
   * Builds the personal information step.
   */
  public function buildPersonalInformationStep(array &$form, FormStateInterface $form_state)
  {
    $stored = $form_state->get('stored_values') ?: [];

    $form['personal_information'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Personal Information'),
    ];

    $form['personal_information']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
      '#default_value' => $stored['name'] ?? '',
    ];

    $form['personal_information']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#default_value' => $stored['email'] ?? '',
    ];

    $form['personal_information']['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone'),
      '#required' => TRUE,
      '#default_value' => $stored['phone'] ?? '',
      '#attributes' => [
        'pattern' => '0[567][0-9]{8}',
        'title' => $this->t('10 digits starting with 05, 06, or 07'),
      ],
    ];
  }

  /**
   * Builds the confirmation step.
   *
   * Reads from stored_values (populated by BookingSubmitForm::storeCurrentStepValues).
   */
  public function buildConfirmationStep(array &$form, FormStateInterface $form_state)
  {
    $stored = $form_state->get('stored_values') ?: [];

    $agency_label = $stored['agency_options'] ?? '';
    $adviser_label = $stored['adviser_options'] ?? '';
    $service_label = $stored['service_options'] ?? '';

    try {
      $agency_options = $this->bookingService->getAgencyOptions();
      $agency_label = $agency_options[$stored['agency_options']] ?? $stored['agency_options'];

      $adviser_options = $this->bookingService->getAdviserOptions();
      $adviser_label = $adviser_options[$stored['adviser_options']] ?? $stored['adviser_options'];

      $service_options = $this->bookingService->getServiceOptions();
      $service_label = $service_options[$stored['service_options']] ?? $stored['service_options'];
    } catch (\Exception $e) {
    }

    $date_value = $stored['date_time'] ?? '';
    $date_display = $date_value;
    if ($date_value instanceof \Drupal\Core\Datetime\DrupalDateTime) {
      $date_display = $date_value->format('Y-m-d H:i');
    }
    elseif (is_string($date_value) && !empty($date_value)) {
      $date_display = str_replace('T', ' ', $date_value);
    }

    $form['confirmation'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Confirmation'),
    ];

    $form['confirmation']['summary'] = [
      '#markup' => '<p>' . $this->t('Please review your booking details below:') . '</p>',
    ];

    $summary_data = [
      'Agency' => $agency_label,
      'Adviser' => $adviser_label,
      'Service' => $service_label,
      'Date' => $date_display,
      'Name' => $stored['name'] ?? '',
      'Email' => $stored['email'] ?? '',
      'Phone' => $stored['phone'] ?? '',
    ];

    $output = '<ul>';
    foreach ($summary_data as $label => $value) {
      $value_safe = htmlspecialchars((string) $value);
      $output .= '<li><strong>' . $label . ':</strong> ' . $value_safe . '</li>';
    }
    $output .= '</ul>';

    $form['confirmation']['details'] = [
      '#markup' => $output,
    ];
  }

}
