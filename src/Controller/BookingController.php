<?php

namespace Drupal\booking\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\booking\Service\ExportService;

/**
 * Controller for the Booking module exports.
 */
class BookingController extends ControllerBase {

  /**
   * @var \Drupal\booking\Service\ExportService
   */
  protected $exportService;

  public function __construct(ExportService $exportService) {
    $this->exportService = $exportService;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('booking.export_service')
    );
  }

  public function exportData() {
    $response = new StreamedResponse(function () {
      $this->exportService->prepareDataForExport();
    });

    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="bookings_export.csv"');

    return $response;
  }

}
