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
use Drupal\Core\Mail\MailManagerInterface;

/**
 * Front-end form for a client to edit their booking via reference code.
 */
class BookingEditForm extends FormBase
{
  use BookingFormTrait;

  protected EntityTypeManagerInterface $entityTypeManager;
  protected BookingService $bookingService;
  protected AccountInterface $currentUser;
  protected MailManagerInterface $mailManager;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    BookingService $bookingService,
    AccountInterface $currentUser,
    MailManagerInterface $mailManager
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->bookingService = $bookingService;
    $this->currentUser = $currentUser;
    $this->mailManager = $mailManager;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('booking.service'),
      $container->get('current_user'),
      $container->get('plugin.manager.mail')
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

    if (!$form_state->get('verified')) {
      $form['verification_help'] = [
        '#markup' => '<div class="messages messages--warning">' . $this->t('A verification code has been sent to your email. Please enter it below to continue.') . '</div>',
      ];
      $form['verification_code'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Verification Code'),
        '#required' => TRUE,
        '#attributes' => ['placeholder' => '123456'],
      ];
      $form['actions']['#type'] = 'actions';
      $form['actions']['verify'] = [
        '#type' => 'submit',
        '#value' => $this->t('Verify Code'),
        '#submit' => ['::verifySubmit'],
        '#ajax' => [
          'callback' => '::ajaxCallback',
          'wrapper' => 'booking-edit-wrapper',
        ],
      ];
      $form['actions']['restart'] = [
        '#type' => 'submit',
        '#value' => $this->t('Back'),
        '#limit_validation_errors' => [],
        '#submit' => ['::restartSubmit'],
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

    // Date selection
    $form['calendar_display'] = [
      '#markup' => '<div id="calendar-container"></div>',
      '#attached' => [
        'library' => ['booking/booking_calendar'],
      ],
    ];

    $form['booking_date_selection'] = [
      '#type' => 'textfield',
      '#attributes' => ['style' => 'display:none;'],
      '#default_value' => !empty($booking->get('booking_date')->value)
        ? explode('T', $booking->get('booking_date')->value)[0]
        : NULL,
      '#parents' => ['booking_date_selection'],
      '#ajax' => [
        'callback' => '::dateChanged',
        'wrapper' => 'time-slot-wrapper',
        'event' => 'change',
      ],
    ];

    // Time slot selection
    $form['time_slot_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'time-slot-wrapper'],
    ];

    $selected_date = $form_state->getValue('booking_date_selection') ?? explode('T', $booking->get('booking_date')->value)[0];
    
    if ($selected_date && $selected_adviser) {
      $available_slots = $this->bookingService->getAvailableTimeSlots((int) $selected_adviser, $selected_date);
      $current_time = substr(explode('T', $booking->get('booking_date')->value)[1], 0, 5);
      
      // If editing, the CURRENT slot should be included even if it's "busy" by the database
      if (!in_array($current_time, $available_slots)) {
        $available_slots[] = $current_time;
        sort($available_slots);
      }

      $options = [];
      foreach ($available_slots as $slot) {
        $hour = (int) explode(':', $slot)[0];
        $label = $slot . ' - ' . sprintf('%02d:00', $hour + 1);
        $options[$slot] = $label;
      }

      $form['time_slot_wrapper']['booking_time_slot'] = [
        '#type' => 'radios',
        '#title' => $this->t('Time slot (1 hour)'),
        '#options' => $options,
        '#default_value' => $form_state->getValue('booking_time_slot') ?? $current_time,
        '#required' => TRUE,
        '#attributes' => ['class' => ['booking-cards-wrapper']],
        '#attached' => [
          'library' => ['booking/form_cards'],
        ],
      ];
    }

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
      '#attributes' => [
        'pattern' => '0[567][0-9]{8}',
        'title' => $this->t('10 digits starting with 05, 06, or 07'),
      ],
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
   * AJAX callback: re-renders time slots when date changes.
   */
  public function dateChanged(array &$form, FormStateInterface $form_state): array
  {
    return $form['time_slot_wrapper'];
  }

  public function ajaxCallback(array &$form, FormStateInterface $form_state): array
  {
    return $form;
  }

  public function lookupSubmit(array &$form, FormStateInterface $form_state): void
  {
    $reference = strtoupper(trim($form_state->getValue('reference')));
    $results = $this->entityTypeManager->getStorage('booking')->loadByProperties(['reference' => $reference]);

    if (empty($results)) {
      $this->messenger()->addError($this->t('No booking found with reference @ref.', ['@ref' => $reference]));
      $form_state->setRebuild();
      return;
    }

    $booking = reset($results);

    // Block access to deleted bookings.
    if ($booking->get('booking_status')->value === BookingStatus::DELETED->value) {
      $this->messenger()->addError($this->t('This booking has been deleted and cannot be accessed.'));
      $form_state->setRebuild();
      return;
    }
    
    $code = (string) rand(100000, 999999);
    $booking->set('booking_secret_code', $code);
    $booking->save();

    // Send the email.
    try {
      $to = $booking->get('booking_customer_email')->value;
      $params = [
        'code' => $code,
        'reference' => $booking->get('reference')->value,
      ];
      $this->mailManager->mail('booking', 'edit_access_code', $to, 'fr', $params);
    } catch (\Exception $e) {
      $this->logger('booking')->error('Failed to send verification email: @msg', ['@msg' => $e->getMessage()]);
      $this->messenger()->addError($this->t('Unable to send the verification code. Please check your mail server settings.'));
      $form_state->set('booking', NULL);
      $form_state->setRebuild();
      return;
    }

    $form_state->set('booking', $booking);
    $form_state->setRebuild();
  }

  public function verifySubmit(array &$form, FormStateInterface $form_state): void
  {
    $entered_code = trim($form_state->getValue('verification_code'));
    /** @var \Drupal\booking\Entity\BookingEntity $booking */
    $booking = $form_state->get('booking');

    if ($booking && $booking->get('booking_secret_code')->value === $entered_code) {
      $form_state->set('verified', TRUE);
      $this->messenger()->addStatus($this->t('Code verified successfully.'));
    }
    else {
      $this->messenger()->addError($this->t('Invalid verification code. Please check your email.'));
    }
    $form_state->setRebuild();
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void
  {
    if ($form_state->get('verified')) {
      $phone = $form_state->getValue('booking_customer_phone');
      if (!empty($phone) && !preg_match('/^0[567]\d{8}$/', $phone)) {
        $form_state->setErrorByName('booking_customer_phone', $this->t('The phone number must be exactly 10 digits and start with 05, 06, or 07.'));
      }
    }
  }

  public function restartSubmit(array &$form, FormStateInterface $form_state): void
  {
    $form_state->set('booking', NULL);
    $form_state->set('verified', FALSE);
    $form_state->setRebuild();
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    try {
      /** @var \Drupal\booking\Entity\BookingEntity $booking */
      $booking = $this->entityTypeManager->getStorage('booking')->load($form_state->getValue('booking_id'));
      
      if (!$booking) {
        $this->messenger()->addError($this->t('Booking not found.'));
        return;
      }

      $dateOnly = $form_state->getValue('booking_date_selection');
      $timeSlot = $form_state->getValue('booking_time_slot');
      $date_string = $dateOnly . 'T' . $timeSlot . ':00';

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
      
      // We only care about status transition violations for a cancellation.
      $violations = $booking->validate();
      $status_violations = [];
      foreach ($violations as $v) {
        if ($v->getPropertyPath() === 'booking_status') {
          $status_violations[] = $v;
          $this->messenger()->addError($v->getMessage());
        }
      }

      if (count($status_violations) > 0) {
        return;
      }
      
      // Save directly, allowing the status change to persist even if other fields are technically invalid.
      $booking->save();
      $this->logger('booking')->notice('Booking @id cancelled successfully.', ['@id' => $booking->id()]);
      $this->messenger()->addStatus($this->t('Your booking has been cancelled.'));
      
      $form_state->set('booking', NULL);
      $form_state->setRedirect('booking.mes-rdv');
    } catch (\Exception $e) {
      $this->logger('booking')->error('Failed to cancel booking: @msg', ['@msg' => $e->getMessage()]);
      $this->messenger()->addError($this->t('An error occurred while cancelling. Please contact us.'));
    }
  }

}
