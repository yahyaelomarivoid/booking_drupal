<?php

namespace Drupal\booking\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Admin form for quickly updating only the booking status.
 */
class BookingStatusForm extends ContentEntityForm
{

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\booking\Entity\BookingEntity $booking */
    $booking = $this->entity;

    $form['info'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Booking details'),
      '#items' => [
        $this->t('Reference: <strong>@ref</strong>', ['@ref' => $booking->get('reference')->value]),
        $this->t('Customer: <strong>@name</strong>', ['@name' => $booking->get('booking_customer_name')->value]),
        $this->t('Date: <strong>@date</strong>', ['@date' => $booking->get('booking_date')->value]),
      ],
      '#weight' => -100,
    ];

    $hide_fields = ['reference', 'booking_customer_name', 'booking_customer_email', 'booking_customer_phone', 'booking_date', 'booking_agency', 'booking_adviser', 'booking_type', 'booking_notes', 'user_id', 'created', 'changed'];
    foreach ($hide_fields as $field_name) {
      if (isset($form[$field_name])) {
        $form[$field_name]['#access'] = FALSE;
      }
    }

    if (isset($form['booking_status'])) {
      $form['booking_status']['#weight'] = 0;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int
  {
    try {
      $result = parent::save($form, $form_state);

      /** @var \Drupal\booking\Entity\BookingEntity $booking */
      $booking = $this->entity;
      
      $this->messenger()->addStatus($this->t('Booking status updated to <strong>@status</strong>.', [
        '@status' => $booking->getStatusLabel(),
      ]));

      if (in_array('booking_adviser', \Drupal::currentUser()->getRoles())) {
        $form_state->setRedirect('booking.mes-rdv');
      }
      else {
        $form_state->setRedirect('entity.booking.collection');
      }

      return $result;
    } catch (\Exception $e) {
      $this->logger('booking')->error('Failed to update booking status: @msg', ['@msg' => $e->getMessage()]);
      $this->messenger()->addError($this->t('An error occurred while saving the booking status. Please try again.'));
      return 0;
    }
  }

}
