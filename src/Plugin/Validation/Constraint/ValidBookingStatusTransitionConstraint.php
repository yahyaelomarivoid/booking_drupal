<?php

namespace Drupal\booking\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validates booking status transitions.
 *
 * @Constraint(
 *   id = "ValidBookingStatusTransition",
 *   label = @Translation("Valid Booking Status Transition", context = "Validation"),
 *   type = "entity:booking"
 * )
 */
class ValidBookingStatusTransitionConstraint extends Constraint
{
  public $message = 'You cannot change the booking status from %original to %new.';
}
