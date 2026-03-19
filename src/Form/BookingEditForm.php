<?php

namespace Drupal\booking\Form;

use Drupal\booking\Service\BookingService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\booking\Entity\Enums\BookingStatus;

/**
 * Front-end form for a client to edit their booking via reference code.
 */
class BookingEditForm extends FormBase
{
  use BookingFormTrait;

  protected EntityTypeManagerInterface $entityTypeManager;
  protected BookingService $bookingService;
  protected AccountInterface $currentUser;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    BookingService $bookingService,
    AccountInterface $currentUser
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->bookingService = $bookingService;
    $this->currentUser = $currentUser;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('booking.service'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'booking_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $form['#prefix'] = '<div id="booking-edit-wrapper">';
    $form['#suffix'] = '</div>';

    $booking = $form_state->get('booking');

    if (!$booking) {
      $form['reference'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Enter your booking reference'),
        '#placeholder' => 'REF-YYYYMMDD-XXXX',
        '#required' => TRUE,
      ];
      $form['actions']['#type'] = 'actions';
      $form['actions']['lookup'] = [
        '#type' => 'submit',
        '#value' => $this->t('Find my booking'),
        '#submit' => ['::lookupSubmit'],
        '#ajax' => [
          'callback' => '::ajaxCallback',
          'wrapper' => 'booking-edit-wrapper',
        ],
      ];
      return $form;
    }

    $this->buildEditForm($form, $form_state, $booking);

    $form['actions']['#type'] = 'actions';
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save changes'),
    ];
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel my booking'),
      '#submit' => ['::cancelSubmit'],
      '#attributes' => [
        'class' => ['button--danger'],
        'onclick' => 'return confirm("' . $this->t('Are you sure you want to cancel this booking?') . '");',
      ],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * Builds the actual editable fields with dependent AJAX dropdowns.
   */
  protected function buildEditForm(array &$form, FormStateInterface $form_state, $booking): void
  {
    // Store booking id as a value so it's reliably available in submit handlers.
    $form['booking_id'] = ['#type' => 'value', '#value' => $booking->id()];

    // Agency select — drives adviser dropdown
    $agency_options = $this->bookingService->getAgencyOptions();
    $selected_agency = $form_state->getValue('booking_agency') ?? $booking->get('booking_agency')->target_id;

    $form['booking_agency'] = [
      '#type' => 'select',
      '#title' => $this->t('Agency'),
      '#options' => $agency_options,
      '#default_value' => $selected_agency,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::agencyChanged',
        'wrapper' => 'adviser-wrapper',
        'event' => 'change',
      ],
    ];

    // Adviser select — depends on agency
    $adviser_options = $this->bookingService->getAdviserOptions();
    $selected_adviser = $form_state->getValue('booking_adviser') ?? $booking->get('booking_adviser')->target_id;

    $form['adviser_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'adviser-wrapper'],
    ];
    $form['adviser_wrapper']['booking_adviser'] = [
      '#type' => 'select',
      '#title' => $this->t('Adviser'),
      '#options' => $adviser_options,
      '#default_value' => $selected_adviser,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::adviserChanged',
        'wrapper' => 'service-wrapper',
        'event' => 'change',
      ],
    ];

    // Service select
    $service_options = $this->bookingService->getServiceOptions();
    $selected_service = $form_state->getValue('booking_type') ?? $booking->get('booking_type')->target_id;

    $form['service_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'service-wrapper'],
    ];
    $form['service_wrapper']['booking_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Service'),
      '#options' => $service_options,
      '#default_value' => $selected_service,
      '#required' => TRUE,
    ];

    // Date/time
    $form['booking_date'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Appointment date'),
      '#default_value' => !empty($booking->get('booking_date')->value)
        ? DrupalDateTime::createFromFormat('Y-m-d\TH:i:s', $booking->get('booking_date')->value)
        : NULL,
      '#required' => TRUE,
    ];

    // Customer name
    $form['booking_customer_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your name'),
      '#default_value' => $booking->get('booking_customer_name')->value,
      '#required' => TRUE,
    ];

    // Email
    $form['booking_customer_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => $booking->get('booking_customer_email')->value,
      '#required' => TRUE,
    ];

    // Phone
    $form['booking_customer_phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone'),
      '#default_value' => $booking->get('booking_customer_phone')->value,
      '#required' => TRUE,
    ];
  }

  /**
   * AJAX callback: re-renders adviser dropdown when agency changes.
   */
  public function agencyChanged(array &$form, FormStateInterface $form_state): array
  {
    return $form['adviser_wrapper'];
  }

  /**
   * AJAX callback: re-renders service dropdown when adviser changes.
   */
  public function adviserChanged(array &$form, FormStateInterface $form_state): array
  {
    return $form['service_wrapper'];
  }

  /**
   * General AJAX callback.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state): array
  {
    return $form;
  }

  /**
   * Submit for reference lookup.
   */
  public function lookupSubmit(array &$form, FormStateInterface $form_state): void
  {
    $reference = strtoupper(trim($form_state->getValue('reference')));
    $results = $this->entityTypeManager->getStorage('booking')->loadByProperties(['reference' => $reference]);

    if (empty($results)) {
      $form_state->setErrorByName('reference', $this->t('No booking found with reference @ref.', ['@ref' => $reference]));
      return;
    }

    $booking = reset($results);
    $form_state->set('booking', $booking);
    $form_state->setRebuild();
  }

  /**
   * Submit for saving changes.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    try {
      /** @var \Drupal\booking\Entity\BookingEntity $booking */
      $booking = $this->entityTypeManager->getStorage('booking')->load($form_state->getValue('booking_id'));
      
      if (!$booking) {
        $this->messenger()->addError($this->t('Booking not found.'));
        return;
      }

      $date = $form_state->getValue('booking_date');
      $date_string = ($date instanceof DrupalDateTime)
        ? $date->format('Y-m-d\TH:i:s')
        : $date;

      $booking->set('booking_agency', $form_state->getValue('booking_agency'));
      $booking->set('booking_adviser', $form_state->getValue('booking_adviser'));
      $booking->set('booking_type', $form_state->getValue('booking_type'));
      $booking->set('booking_date', $date_string);
      $booking->set('booking_customer_name', $form_state->getValue('booking_customer_name'));
      $booking->set('booking_customer_email', $form_state->getValue('booking_customer_email'));
      $booking->set('booking_customer_phone', $form_state->getValue('booking_customer_phone'));
      
      $violations = $booking->validate();
      if ($violations->count() > 0) {
        foreach ($violations as $violation) {
          $this->messenger()->addError($violation->getMessage());
        }
        return;
      }

      $booking->save();

      $this->messenger()->addStatus($this->t('Your booking has been updated successfully.'));
    } catch (\Exception $e) {
      $this->logger('booking')->error('Failed to update booking: @msg', ['@msg' => $e->getMessage()]);
      $this->messenger()->addError($this->t('An error occurred. Please try again.'));
    }
  }

  /**
   * Submit for cancelling the booking.
   */
  public function cancelSubmit(array &$form, FormStateInterface $form_state): void
  {
    $this->messenger()->addStatus('Debug: cancelSubmit called.');
    try {
      /** @var \Drupal\booking\Entity\BookingEntity $stored_booking */
      $stored_booking = $form_state->get('booking');

      if (!$stored_booking) {
        $this->logger('booking')->error('Cancel failed: booking not found in form state.');
        $this->messenger()->addError($this->t('Booking not found.'));
        return;
      }

      /** @var \Drupal\booking\Entity\BookingEntity $booking */
      $booking = $this->entityTypeManager->getStorage('booking')->load($stored_booking->id());
      
      if (!$booking) {
        $this->logger('booking')->error('Cancel failed: booking_id @id not found.', ['@id' => $stored_booking->id()]);
        $this->messenger()->addError($this->t('Booking not found.'));
        return;
      }

      $booking->set('booking_status', BookingStatus::CANCELLED->value);
      
      $violations = $booking->validate();
      if ($violations->count() > 0) {
        foreach ($violations as $violation) {
          $this->messenger()->addError($violation->getMessage());
        }
        return;
      }
      
      $booking->save();
      $this->messenger()->addStatus($this->t('Your booking has been cancelled.'));
      
      // Clear the booking from form state and redirect to start.
      $form_state->set('booking', NULL);
      $form_state->setRedirect('booking.mes-rdv');
    } catch (\Exception $e) {
      $this->logger('booking')->error('Failed to cancel booking: @msg', ['@msg' => $e->getMessage()]);
      $this->messenger()->addError($this->t('An error occurred while cancelling. Please contact us.'));
    }
  }

}
