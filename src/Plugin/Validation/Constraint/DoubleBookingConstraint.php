<?php

namespace Drupal\booking\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the booking does not conflict with an existing one.
 *
 * @Constraint(
 *   id = "DoubleBooking",
 *   label = @Translation("Double Booking", context = "Validation"),
 *   type = "entity:booking"
 * )
 */
class DoubleBookingConstraint extends Constraint
{
  public $message = 'The selected slot is already booked for this agency/adviser.';
}
