<?php

namespace Drupal\booking\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\booking\Entity\BookingEntity;
use Drupal\booking\Entity\Enums\BookingStatus;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    if ($date instanceof \Drupal\Core\Datetime\DrupalDateTime) {
      $date = $date->format('Y-m-d\TH:i:s');
    } elseif (is_array($date)) {
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
        ->condition('booking_status', [BookingStatus::CANCELLED->value, BookingStatus::DELETED->value], 'NOT IN')
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
      return TRUE;
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

  public function getAdviserOptions($agencyId = NULL, $serviceId = NULL): array
  {
    try {
      $storage = $this->entityTypeManager->getStorage('user');
      $query = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('status', 1);

      if ($agencyId) {
        $query->condition('field_agency', $agencyId);
      }

      if ($serviceId) {
        $query->condition('field_specializations', $serviceId);
      }

      $uids = $query->execute();
      $users = $storage->loadMultiple($uids);

      $options = [];
      foreach ($users as $user) {
        if ($user->id() == 0)
          continue;
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

  /**
   * Gets rich agency options for form radios (as cards).
   */
  public function getAgencyRichOptions(): array
  {
    try {
      $agencies = $this->entityTypeManager->getStorage('agency')->loadMultiple();
      $options = [];
      foreach ($agencies as $agency) {
        $card = '<div class="booking-card" style="height: 100%;">';
        $card .= '<h4 class="booking-card-title">' . htmlspecialchars((string) $agency->label()) . '</h4>';

        if ($agency->hasField('address') && !$agency->get('address')->isEmpty()) {
          $card .= '<div class="booking-card-detail"><strong>' . $this->t('Address') . ':</strong> ' . htmlspecialchars((string) $agency->get('address')->value) . '</div>';
        }
        if ($agency->hasField('phone') && !$agency->get('phone')->isEmpty()) {
          $card .= '<div class="booking-card-detail"><strong>' . $this->t('Phone') . ':</strong> ' . htmlspecialchars((string) $agency->get('phone')->value) . '</div>';
        }
        if ($agency->hasField('operating_hours') && !$agency->get('operating_hours')->isEmpty()) {
          $card .= '<div class="booking-card-detail"><strong>' . $this->t('Hours') . ':</strong> ' . htmlspecialchars((string) $agency->get('operating_hours')->value) . '</div>';
        }
        $card .= '</div>';
        $options[$agency->id()] = \Drupal\Core\Render\Markup::create($card);
      }
      return $options;
    } catch (\Exception $e) {
      $this->logger->error('Error fetching rich agency options: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  public function getAdviserRichOptions($agencyId = NULL, $serviceId = NULL): array
  {
    try {
      $storage = $this->entityTypeManager->getStorage('user');
      $query = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('status', 1);

      if ($agencyId) {
        $query->condition('field_agency', $agencyId);
      }

      if ($serviceId) {
        $query->condition('field_specializations', $serviceId);
      }

      $uids = $query->execute();
      $users = $storage->loadMultiple($uids);

      $options = [];
      foreach ($users as $user) {
        if ($user->id() == 0)
          continue;
        /** @var \Drupal\user\UserInterface $user */

        $card = '<div class="booking-card" style="height: 100%;">';
        $card .= '<h4 class="booking-card-title">' . htmlspecialchars((string) $user->getDisplayName()) . '</h4>';
        if ($user->hasField('mail') && !$user->get('mail')->isEmpty()) {
          $card .= '<div class="booking-card-detail"><strong>' . $this->t('Email') . ':</strong> ' . htmlspecialchars((string) $user->getEmail()) . '</div>';
        }
        $card .= '</div>';

        $options[$user->id()] = \Drupal\Core\Render\Markup::create($card);
      }
      return $options;
    } catch (\Exception $e) {
      $this->logger->error('Error fetching rich adviser options: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Gets rich service options for form radios (as cards).
   */
  public function getServiceRichOptions(): array
  {
    try {
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => 'services']);
      $options = [];
      foreach ($terms as $term) {
        $card = '<div class="booking-card" style="height: 100%;">';
        $card .= '<h4 class="booking-card-title">' . htmlspecialchars((string) $term->label()) . '</h4>';
        if ($term->hasField('description') && !$term->get('description')->isEmpty()) {
          $card .= '<div class="booking-card-detail">' . $term->get('description')->value . '</div>';
        }
        $card .= '</div>';
        $options[$term->id()] = \Drupal\Core\Render\Markup::create($card);
      }
      return $options;
    } catch (\Exception $e) {
      $this->logger->error('Error fetching rich service options: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Generates available 1-hour time slots for an adviser on a specific date.
   */
  public function getAvailableTimeSlots(int $adviserId, string $date): array
  {
    try {
      $adviser = $this->entityTypeManager->getStorage('user')->load($adviserId);
      if (!$adviser || !$adviser->hasField('field_working_hours')) {
        return [];
      }

      $workingHours = $adviser->get('field_working_hours')->value ?? '09:00-17:00';
      $ranges = explode(',', $workingHours);
      $allSlots = [];

      foreach ($ranges as $range) {
        if (str_contains($range, '-')) {
          [$start, $end] = explode('-', trim($range));
          $startTime = (int) explode(':', $start)[0];
          $endTime = (int) explode(':', $end)[0];

          for ($hour = $startTime; $hour < $endTime; $hour++) {
            $allSlots[] = sprintf('%02d:00', $hour);
          }
        }
      }

      $query = $this->entityTypeManager->getStorage('booking')->getQuery()
        ->accessCheck(FALSE)
        ->condition('booking_adviser', $adviserId)
        ->condition('booking_date', $date . '%', 'LIKE')
        ->condition('booking_status', [BookingStatus::CANCELLED->value, BookingStatus::DELETED->value], 'NOT IN');

      $bookingIds = $query->execute();
      $bookings = $this->entityTypeManager->getStorage('booking')->loadMultiple($bookingIds);

      $busySlots = [];
      foreach ($bookings as $busy) {
        $bookingDate = $busy->get('booking_date')->value;
        if ($bookingDate) {
          $timePart = (str_contains($bookingDate, 'T')) ? explode('T', $bookingDate)[1] : explode(' ', $bookingDate)[1];
          // Round down to the nearest hour for the busy list
          $hour = explode(':', $timePart)[0];
          $busySlots[] = $hour . ':00';
        }
      }

      return array_values(array_filter($allSlots, fn($slot) => !in_array($slot, $busySlots)));
    } catch (\Exception $e) {
      $this->logger->error('Error calculating time slots: @message', ['@message' => $e->getMessage()]);
      return [];
    }
  }
}
