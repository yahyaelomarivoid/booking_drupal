<?php

namespace Drupal\booking\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Drupal\booking\Entity\Enums\BookingStatus;

/**
 * Validates the ValidBookingStatusTransition constraint.
 */
class ValidBookingStatusTransitionConstraintValidator extends ConstraintValidator
{

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint)
  {
    if ($entity->isNew() || !isset($entity->original)) {
      return;
    }

    $original_status = $entity->original->get('booking_status')->value;
    $new_status = $entity->get('booking_status')->value;

    if ($original_status === $new_status) {
      return;
    }

    $allowed = [
      BookingStatus::PENDING->value => [
        BookingStatus::CONFIRMED->value,
        BookingStatus::CANCELLED->value,
        BookingStatus::COMPLETED->value,
      ],
      BookingStatus::CONFIRMED->value => [
        BookingStatus::COMPLETED->value,
        BookingStatus::CANCELLED->value,
      ],
      BookingStatus::CANCELLED->value => [],
      BookingStatus::COMPLETED->value => [],
    ];

    if (!in_array($new_status, $allowed[$original_status] ?? [])) {
      $this->context->buildViolation($constraint->message)
        ->setParameter('%original', $original_status)
        ->setParameter('%new', $new_status)
        ->atPath('booking_status')
        ->addViolation();
    }
  }
}
