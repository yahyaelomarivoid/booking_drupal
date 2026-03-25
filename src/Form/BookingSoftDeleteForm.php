<?php

namespace Drupal\booking\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\booking\Entity\Enums\BookingStatus;

/**
 * Provides a form for soft-deleting a Booking entity.
 */
class BookingSoftDeleteForm extends ContentEntityConfirmFormBase
{

  /**
   * {@inheritdoc}
   */
  public function getQuestion()
  {
    return $this->t('Are you sure you want to delete the booking %label?', [
      '%label' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription()
  {
    return $this->t('This action will mark the booking as deleted. It will be hidden from lists but preserved in the database.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl()
  {
    if (in_array('booking_adviser', \Drupal::currentUser()->getRoles())) {
      return Url::fromRoute('booking.mes-rdv');
    }
    return new Url('entity.booking.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText()
  {
    return $this->t('Delete (Soft)');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    /** @var \Drupal\booking\Entity\BookingEntity $entity */
    $entity = $this->entity;
    $entity->set('booking_status', BookingStatus::DELETED->value);
    $entity->save();

    $this->messenger()->addStatus($this->t('The booking %label has been marked as deleted.', [
      '%label' => $entity->label(),
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
