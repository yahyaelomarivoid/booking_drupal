<?php

namespace Drupal\booking;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\booking\Entity\BookingEntity;
use Drupal\booking\Entity\Enums\BookingStatus;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service to manage appointment bookings and availability.
 */
class BookingService
{

  use StringTranslationTrait;


  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new BookingService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $logger_factory,
    MessengerInterface $messenger
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger_factory->get('booking');
    $this->messenger = $messenger;
  }

  /**
   * Checks if an appointment slot is available.
   *
   * @param string|\DateTimeInterface $date
   *   The date and time (string in ISO 8601 or DateTime object).
   * @param int|string $agencyId
   *   The agency ID.
   * @param int|string $adviserId
   *   The adviser (user) ID.
   * @param int|string $excludeId
   *   An optional entity ID to exclude from the check (useful for updates).
   *
   * @return bool
   *   TRUE if available, FALSE otherwise.
   */
  public function checkAvailability($date, $agencyId, $adviserId, $excludeId = NULL): bool
  {
    if (!$date || !$agencyId || !$adviserId) {
      return FALSE;
    }

    // Normalize date to string if it's a DrupalDateTime object.
    if ($date instanceof \Drupal\Core\Datetime\DrupalDateTime) {
      $date = $date->format('Y-m-d\TH:i:s');
    }
    elseif (is_array($date)) {
      // Handle array format ['date' => '...', 'time' => '...']
      $date_str = $date['date'] ?? '';
      $time_str = $date['time'] ?? '00:00:00';
      if (!empty($date_str)) {
        $date = str_replace(' ', 'T', trim($date_str . ' ' . $time_str));
      }
    }

    try {
      if ($date instanceof \DateTimeInterface) {
        $date = $date->format('Y-m-d\TH:i:s');
      }

      $query = $this->entityTypeManager->getStorage('booking')->getQuery()
        ->condition('booking_date', $date)
        ->condition('booking_agency', $agencyId)
        ->condition('booking_adviser', $adviserId)
        ->condition('booking_status', BookingStatus::CANCELLED->value, '<>')
        ->accessCheck(FALSE);

      if ($excludeId) {
        $query->condition('id', $excludeId, '<>');
      }

      $ids = $query->execute();

      return empty($ids);
    } catch (\Exception $e) {
      $this->logger->error('Error checking availability: @message', ['@message' => $e->getMessage()]);
      $this->messenger->addError($this->t('An error occurred while checking availability. Please try again later.'));
      return FALSE;
    }
  }

  /**
   * Validates if a booking entity would cause a double booking.
   *
   * @param \Drupal\booking\Entity\BookingEntity $entity
   *   The booking entity to validate.
   *
   * @return bool
   *   TRUE if it is a double booking, FALSE otherwise.
   */
  public function hasDoubleBooking(BookingEntity $entity): bool
  {
    try {
      $date = $entity->get('booking_date')->value;
      $agencyId = $entity->get('booking_agency')->target_id;
      $adviserId = $entity->get('booking_adviser')->target_id;

      if (empty($date) || empty($agencyId) || empty($adviserId)) {
        return FALSE;
      }

      return !$this->checkAvailability($date, $agencyId, $adviserId, $entity->id());
    } catch (\Exception $e) {
      $this->logger->error('Error validating double booking for entity @id: @message', [
        '@id' => $entity->id(),
        '@message' => $e->getMessage(),
      ]);
      $this->messenger->addError($this->t('An error occurred while validating the booking.'));
      return TRUE; // Assume busy on error to be safe? Or return FALSE? 
      // Usually, we want to prevent booking if we can't verify availability.
    }
  }

  /**
   * Gets agency options for form selects/radios.
   */
  public function getAgencyOptions(): array
  {
    try {
      $agencies = $this->entityTypeManager->getStorage('agency')->loadMultiple();
      $options = [];
      foreach ($agencies as $agency) {
        $options[$agency->id()] = $agency->label();
      }
      return $options;
    } catch (\Exception $e) {
      $this->logger->error('Error fetching agency options: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Gets adviser options.
   */
  public function getAdviserOptions(): array
  {
    try {
      $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['status' => 1]);
      $options = [];
      foreach ($users as $user) {
        if ($user->id() == 0) continue;
        /** @var \Drupal\user\UserInterface $user */
        $options[$user->id()] = $user->getDisplayName();
      }
      return $options;
    } catch (\Exception $e) {
      $this->logger->error('Error fetching adviser options: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Gets service options.
   */
  public function getServiceOptions(): array
  {
    try {
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => 'services']);
      $options = [];
      foreach ($terms as $term) {
        $options[$term->id()] = $term->label();
      }
      return $options;
    } catch (\Exception $e) {
      $this->logger->error('Error fetching service options: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

}
