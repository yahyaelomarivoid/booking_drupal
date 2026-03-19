<?php

namespace Drupal\booking\Plugin\Validation\Constraint;

use Drupal\booking\Service\BookingService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validates the DoubleBooking constraint.
 */
class DoubleBookingConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface
{

  /**
   * The booking service.
   *
   * @var \Drupal\booking\Service\BookingService
   */
  protected $bookingService;

  /**
   * Constructs a new DoubleBookingConstraintValidator.
   */
  public function __construct(BookingService $bookingService)
  {
    $this->bookingService = $bookingService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('booking.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint)
  {
    // Check if we have a double booking using the service.
    if ($this->bookingService->hasDoubleBooking($entity)) {
      $this->context->addViolation($constraint->message);
    }
  }
}
