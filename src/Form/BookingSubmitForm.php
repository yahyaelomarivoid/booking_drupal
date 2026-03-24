<?php

namespace Drupal\booking\Form;

use Drupal\booking\Service\BookingService;
use Drupal\booking\Service\BookingMailService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a multi-step booking form.
 */
class BookingSubmitForm extends FormBase
{

  use BookingFormTrait;

  protected EntityTypeManagerInterface $entityTypeManager;
  protected BookingService $bookingService;
  protected AccountInterface $currentUser;
  protected BookingMailService $mailService;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    BookingService $bookingService,
    AccountInterface $currentUser,
    BookingMailService $mailService,
    \Drupal\Core\Config\ConfigFactoryInterface $configFactory
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->bookingService = $bookingService;
    $this->currentUser = $currentUser;
    $this->mailService = $mailService;
    $this->configFactory = $configFactory;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('booking.service'),
      $container->get('current_user'),
      $container->get('booking.mail_service'),
      $container->get('config.factory')
    );
  }

  public function getFormId()
  {
    return 'booking_submit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $step = $form_state->get('step') ?: 1;
    $form_state->set('step', $step);

    if (!$form_state->has('stored_values')) {
      $form_state->set('stored_values', []);
    }

    $form['#prefix'] = '<div id="booking-form-wrapper">';
    $form['#suffix'] = '</div>';

    switch ($step) {
      case 1:
        $this->buildAgencyOptionsStep($form, $form_state);
        break;
      case 2:
        $this->buildServiceOptionsStep($form, $form_state);
        break;
      case 3:
        $this->buildAdviserStep($form, $form_state);
        break;
      case 4:
        $this->buildDateTimeStep($form, $form_state);
        break;
      case 5:
        $this->buildPersonalInformationStep($form, $form_state);
        break;
      case 6:
        $this->buildConfirmationStep($form, $form_state);
        break;
    }

    $form['actions'] = ['#type' => 'actions'];

    if ($step > 1 && $step < 6) {
      $form['actions']['back'] = [
        '#type' => 'submit',
        '#value' => $this->t('« Back'),
        '#submit' => ['::backSubmit'],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::ajaxCallback',
          'wrapper' => 'booking-form-wrapper',
        ],
      ];
    }

    if ($step < 6) {
      $form['actions']['next'] = [
        '#type' => 'submit',
        '#value' => $this->t('Next »'),
        '#ajax' => [
          'callback' => '::ajaxCallback',
          'wrapper' => 'booking-form-wrapper',
        ],
      ];
    } else {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Finish'),
      ];
    }

    return $form;
  }

  public function ajaxCallback(array &$form, FormStateInterface $form_state)
  {
    return $form;
  }

  public function backSubmit(array &$form, FormStateInterface $form_state)
  {
    $step = $form_state->get('step');
    $form_state->set('step', $step - 1);
    $form_state->setRebuild();
  }

  /**
   * Stores current step values before moving to next step.
   */
  protected function storeCurrentStepValues(FormStateInterface $form_state): void
  {
    $step = $form_state->get('step');
    $stored = $form_state->get('stored_values') ?: [];

    switch ($step) {
      case 1:
        $stored['agency_options'] = $form_state->getValue('agency_options');
        break;
      case 2:
        $stored['service_options'] = $form_state->getValue('service_options');
        break;
      case 3:
        $stored['adviser_options'] = $form_state->getValue('adviser_options');
        break;
      case 4:
        $dateOnly = $form_state->getValue('booking_date_selection');
        $timeSlot = $form_state->getValue('booking_time_slot');
        $stored['date_only'] = $dateOnly;
        $stored['time_slot'] = $timeSlot;
        // Combine into standard ISO format for the entity field
        $stored['date_time'] = $dateOnly . 'T' . $timeSlot . ':00';
        break;
      case 5:
        $stored['name'] = $form_state->getValue('name');
        $stored['email'] = $form_state->getValue('email');
        $stored['phone'] = $form_state->getValue('phone');
        break;
    }

    $form_state->set('stored_values', $stored);
  }

  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $step = $form_state->get('step');

    if ($step == 4) {
      $stored = $form_state->get('stored_values') ?: [];
      $dateOnly = $form_state->getValue('booking_date_selection');
      $timeSlot = $form_state->getValue('booking_time_slot');
      $agencyId = $stored['agency_options'] ?? NULL;
      $adviserId = $stored['adviser_options'] ?? NULL;

      if ($agencyId && $adviserId && $dateOnly && $timeSlot) {
        $dateTime = $dateOnly . 'T' . $timeSlot . ':00';
        if (!$this->bookingService->checkAvailability($dateTime, (int) $agencyId, (int) $adviserId)) {
          $form_state->setErrorByName('booking_time_slot', $this->t('This slot is no longer available. Please choose another time.'));
        }
      }
    }

    if ($step == 5) {
      $phone = $form_state->getValue('phone');
      if (!empty($phone) && !preg_match('/^0[567]\d{8}$/', $phone)) {
        $form_state->setErrorByName('phone', $this->t('The phone number must be exactly 10 digits and start with 05, 06, or 07.'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $step = $form_state->get('step');

    if ($step < 6) {
      $this->storeCurrentStepValues($form_state);
      $form_state->set('step', $step + 1);
      $form_state->setRebuild();
      return;
    }

    try {
      $stored = $form_state->get('stored_values');

      /** @var \Drupal\booking\Entity\BookingEntity $booking */
      $booking = $this->entityTypeManager->getStorage('booking')->create([
        'booking_customer_name' => $stored['name'] ?? '',
        'booking_customer_email' => $stored['email'] ?? '',
        'booking_customer_phone' => $stored['phone'] ?? '',
        'booking_date' => $stored['date_time'] ?? '',
        'booking_agency' => $stored['agency_options'] ?? '',
        'booking_adviser' => $stored['adviser_options'] ?? '',
        'booking_type' => $stored['service_options'] ?? '',
        'booking_status' => 'pending',
      ]);

      $violations = $booking->validate();
      if ($violations->count() > 0) {
        foreach ($violations as $violation) {
          $this->messenger()->addError($violation->getMessage());
        }
        return;
      }

      $booking->save();

      // Send confirmation emails.
      $this->mailService->sendBookingConfirmation($booking);
      $this->mailService->sendAdviserNotification($booking, 'new');



      $this->messenger()->addStatus($this->t('Your booking has been saved successfully! Reference: <strong>@ref</strong>. You can manage it at <a href="@url">@url</a>', [
        '@ref' => $booking->get('reference')->value,
        '@url' => Url::fromRoute('booking.mes-rdv', [], ['absolute' => TRUE])->toString(),
      ]));

      $form_state->setRedirect('booking.rdv');
    } catch (\Exception $e) {
      $this->logger('booking')->error('Final submit failed: @message', ['@message' => $e->getMessage()]);
      $this->messenger()->addError($this->t('An unexpected error occurred while saving your booking: @msg', ['@msg' => $e->getMessage()]));
    }
  }

}
