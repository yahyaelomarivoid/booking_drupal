<?php

namespace Drupal\booking\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\booking\Service\ExportService;
use Drupal\booking\AdviserBookingListBuilder;

/**
 * Controller for the Booking module exports.
 */
class BookingController extends ControllerBase
{

  /**
   * @var \Drupal\booking\Service\ExportService
   */
  protected $exportService;

  /**
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  public function __construct(ExportService $exportService, \Drupal\Core\Form\FormBuilderInterface $formBuilder)
  {
    $this->exportService = $exportService;
    $this->formBuilder = $formBuilder;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('booking.export_service'),
      $container->get('form_builder')
    );
  }

  public function exportData()
  {
    $response = new StreamedResponse(function () {
      $this->exportService->prepareDataForExport();
    });

    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="bookings_export.csv"');

    return $response;
  }

  public function adviserList()
  {
    $listBuilder = $this->entityTypeManager()
      ->createHandlerInstance(AdviserBookingListBuilder::class, $this->entityTypeManager()->getDefinition('booking'));

    return $listBuilder->render();
  }

  /**
   * Managed unified /mes-rdv page.
   */
  public function manageRendezVous()
  {
    // Use the role check to decide the view.
    // Only users with the 'booking_adviser' role see the management list.
    if (in_array('booking_adviser', $this->currentUser()->getRoles())) {
      return $this->adviserList();
    }

    // Everyone else gets the client/guest search form.
    return $this->formBuilder->getForm('\Drupal\booking\Form\BookingEditForm');
  }
}
