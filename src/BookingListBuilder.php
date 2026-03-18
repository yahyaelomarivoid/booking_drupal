<?php

namespace Drupal\booking;

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

class BookingListBuilder extends EntityListBuilder
{

  public function buildHeader(): array
  {
    $header['reference'] = $this->t('Reference');
    $header['customer'] = $this->t('Customer');
    $header['date'] = $this->t('Date');
    $header['status'] = $this->t('Status');
    $header['agency'] = $this->t('Agency');
    return $header + parent::buildHeader();
  }

  public function buildRow(EntityInterface $entity): array
  {
    /** @var \Drupal\booking\Entity\BookingEntity $entity */
    $row['reference'] = [
      'data' => Link::fromTextAndUrl(
        $entity->get('reference')->value,
        $entity->toUrl('canonical')
      ),
    ];
    $row['customer'] = (string) $entity->get('booking_customer_name')->value;
    $row['date'] = (string) $entity->get('booking_date')->value;

    // Status as a badge-like markup
    $status = $entity->get('booking_status')->value;
    $row['status'] = [
      'data' => [
        '#markup' => '<span class="booking-status booking-status--' . htmlspecialchars($status) . '">'
          . htmlspecialchars($entity->getStatusLabel()) . '</span>',
      ],
    ];

    // Agency label
    $agency = $entity->get('booking_agency')->entity;
    $row['agency'] = $agency ? (string) $agency->label() : $this->t('N/A');

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   *
   * Adds View, Edit, Set Status, and Delete operations.
   */
  public function getDefaultOperations(EntityInterface $entity): array
  {
    $operations = [];

    $operations['view'] = [
      'title' => $this->t('View'),
      'weight' => 10,
      'url' => $entity->toUrl('canonical'),
    ];

    $operations['edit'] = [
      'title' => $this->t('Edit'),
      'weight' => 20,
      'url' => $entity->toUrl('edit-form'),
    ];

    $operations['status'] = [
      'title' => $this->t('Set Status'),
      'weight' => 30,
      'url' => Url::fromRoute('entity.booking.status_form', ['booking' => $entity->id()]),
    ];

    $operations['delete'] = [
      'title' => $this->t('Delete'),
      'weight' => 40,
      'url' => $entity->toUrl('delete-form'),
    ];

    return $operations;
  }

}