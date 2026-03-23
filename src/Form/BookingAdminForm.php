<?php

namespace Drupal\booking\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\booking\Service\BookingService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\booking\Entity\Enums\BookingStatus;

/**
 * Form controller for the Booking entity edit forms.
 */
class BookingAdminForm extends ContentEntityForm
{

  /**
   * The booking service.
   *
   * @var \Drupal\booking\Service\BookingService
   */
  protected $bookingService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    $instance = parent::create($container);
    $instance->bookingService = $container->get('booking.service');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\booking\Entity\BookingEntity $entity */
    $entity = $this->entity;

    // AJAX Filtering
    if (isset($form['booking_agency'])) {
      $form['booking_agency']['widget']['#ajax'] = [
        'callback' => [$this, 'updateAdviserCallback'],
        'wrapper' => 'adviser-admin-wrapper',
        'event' => 'change',
      ];
    }

    // 2. Service (type) AJAX
    if (isset($form['booking_type'])) {
      $form['booking_type']['widget']['#ajax'] = [
        'callback' => [$this, 'updateAdviserCallback'],
        'wrapper' => 'adviser-admin-wrapper',
        'event' => 'change',
      ];
    }

    // Adviser Filtering
    if (isset($form['booking_adviser'])) {
      $form['booking_adviser']['#prefix'] = '<div id="adviser-admin-wrapper">';
      $form['booking_adviser']['#suffix'] = '</div>';

      $selected_agency = $form_state->getValue(['booking_agency', 0, 'target_id']) ?? $entity->get('booking_agency')->target_id;
      $selected_service = $form_state->getValue(['booking_type', 0, 'target_id']) ?? $entity->get('booking_type')->target_id;

      if ($selected_agency || $selected_service) {
        $options = $this->bookingService->getAdviserOptions($selected_agency, $selected_service);
        $form['booking_adviser']['widget']['#options'] = ['' => $this->t('- Select -')] + $options;
      }

      $form['booking_adviser']['widget']['#ajax'] = [
        'callback' => [$this, 'updateSlotsCallback'],
        'wrapper' => 'slots-admin-wrapper',
        'event' => 'change',
      ];
    }

    // Date & Time Slot Logic
    if (isset($form['booking_date'])) {
      // Hide the default datetime widget
      $form['booking_date']['#access'] = FALSE;

      $current_date_val = $entity->get('booking_date')->value;
      $default_date = $form_state->getValue('admin_date_selection') ?? (!empty($current_date_val) ? explode('T', $current_date_val)[0] : NULL);
      
      $form['admin_calendar_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'admin-calendar-wrapper'],
      ];

      $form['admin_calendar_wrapper']['calendar_display'] = [
        '#markup' => '<div id="calendar-container"></div>',
        '#attached' => [
          'library' => ['booking/booking_calendar'],
        ],
      ];

      $form['admin_calendar_wrapper']['admin_date_selection'] = [
        '#type' => 'textfield',
        '#attributes' => ['style' => 'display:none;'],
        '#default_value' => $default_date,
        '#parents' => ['admin_date_selection'],
        '#ajax' => [
          'callback' => [$this, 'updateSlotsCallback'],
          'wrapper' => 'slots-admin-wrapper',
          'event' => 'change',
        ],
      ];

      $form['slots_container'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'slots-admin-wrapper'],
        '#weight' => $form['booking_date']['#weight'] + 0.1,
      ];

      $adviser_id = $form_state->getValue(['booking_adviser', 0, 'target_id']) ?? $entity->get('booking_adviser')->target_id;
      
      if ($default_date && $adviser_id) {
        $available_slots = $this->bookingService->getAvailableTimeSlots((int) $adviser_id, $default_date);
        $current_time = !empty($current_date_val) ? substr(explode('T', $current_date_val)[1], 0, 5) : NULL;

        if ($current_time && !in_array($current_time, $available_slots)) {
           $available_slots[] = $current_time;
           sort($available_slots);
        }

        $options = [];
        foreach ($available_slots as $slot) {
          $hour = (int) explode(':', $slot)[0];
          $options[$slot] = $slot . ' - ' . sprintf('%02d:00', $hour + 1);
        }

        $form['slots_container']['admin_time_slot'] = [
          '#type' => 'radios',
          '#title' => $this->t('Available Time Slots (1 hour)'),
          '#options' => $options,
          '#default_value' => $form_state->getValue('admin_time_slot') ?? $current_time,
          '#required' => TRUE,
        ];
      }
    }

    return $form;
  }

  /**
   * AJAX callback to update adviser dropdown.
   */
  public function updateAdviserCallback(array &$form, FormStateInterface $form_state)
  {
    return $form['booking_adviser'];
  }

  /**
   * AJAX callback to update time slots.
   */
  public function updateSlotsCallback(array &$form, FormStateInterface $form_state)
  {
    return $form['slots_container'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    $date = $form_state->getValue('admin_date_selection');
    $slot = $form_state->getValue('admin_time_slot');
    
    if ($date && $slot) {
      $form_state->setValue(['booking_date', 0, 'value'], $date . 'T' . $slot . ':00');
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state)
  {
    $status = parent::save($form, $form_state);
    return $status;
  }

}
