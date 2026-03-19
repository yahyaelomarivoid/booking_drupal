<?php

namespace Drupal\booking;

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;

class BookingListBuilder extends EntityListBuilder
{

  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage)
  {
    parent::__construct($entity_type, $storage);
    $this->limit = 10; // Automatically handles pagination
  }

  public function render()
  {
    $build['filter_form'] = \Drupal::formBuilder()->getForm('\Drupal\booking\Form\BookingFilterForm');
    $build += parent::render();
    return $build;
  }

  public function buildHeader(): array
  {
    $header['reference'] = [
      'data' => $this->t('Reference'),
      'field' => 'reference',
      'specifier' => 'reference',
    ];
    $header['customer'] = [
      'data' => $this->t('Customer'),
      'field' => 'booking_customer_name',
      'specifier' => 'booking_customer_name',
    ];
    $header['date'] = [
      'data' => $this->t('Date'),
      'field' => 'booking_date',
      'specifier' => 'booking_date',
      'sort' => 'desc',
    ];
    $header['status'] = [
      'data' => $this->t('Status'),
      'field' => 'booking_status',
      'specifier' => 'booking_status',
    ];
    $header['agency'] = [
      'data' => $this->t('Agency'),
      'field' => 'booking_agency',
      'specifier' => 'booking_agency',
    ];
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

  protected function getEntityIds()
  {
    $query = $this->getStorage()->getQuery()
      ->accessCheck(TRUE)
      ->pager($this->limit);

    $header = $this->buildHeader();
    $query->tableSort($header);

    $request = \Drupal::request();

    if ($search = $request->query->get('search')) {
      $orGroup = $query->orConditionGroup()
        ->condition('reference', '%' . $search . '%', 'LIKE')
        ->condition('booking_customer_name', '%' . $search . '%', 'LIKE');
      $query->condition($orGroup);
    }

    if ($status = $request->query->get('status')) {
      $query->condition('booking_status', $status);
    }

    if ($agency = $request->query->get('agency')) {
      $query->condition('booking_agency', $agency);
    }

    return $query->execute();
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