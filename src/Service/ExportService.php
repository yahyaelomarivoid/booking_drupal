<?php

namespace Drupal\booking\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class ExportService
{
    use StringTranslationTrait;

    protected $entityTypeManager;
    protected $logger;

    public function __construct(EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $logger_factory)
    {
        $this->entityTypeManager = $entityTypeManager;
        $this->logger = $logger_factory->get('booking_export');
    }

    public function prepareDataForExport()
    {
        $handle = fopen('php://output', 'w');
        if ($handle === FALSE) {
            $this->logger->error('Could not open output stream for CSV export.');
            return;
        }

        fputcsv($handle, ['Reference', 'Date', 'Customer', 'Email', 'Phone', 'Agency', 'Adviser', 'Status']);

        try {
            // Fetching the correct 'booking' entity type mappings
            $storage = $this->entityTypeManager->getStorage('booking');

            $chunkSize = 50;
            $offset = 0;
            while (TRUE) {
                $ids = $storage->getQuery()
                    ->accessCheck(FALSE)
                    ->range($offset, $chunkSize)
                    ->execute();

                if (empty($ids)) {
                    break;
                }

                $bookings = $storage->loadMultiple($ids);


                foreach ($bookings as $booking) {
                    $agencyLabel = $booking->hasField('booking_agency') && !$booking->get('booking_agency')->isEmpty()
                        ? $booking->get('booking_agency')->entity->label() : 'N/A';
                    $adviserLabel = $booking->hasField('booking_adviser') && !$booking->get('booking_adviser')->isEmpty()
                        ? $booking->get('booking_adviser')->entity->getDisplayName() : 'N/A';

                    fputcsv($handle, [
                        $booking->get('reference')->value ?? '',
                        $booking->get('booking_date')->value ?? '',
                        $booking->get('booking_customer_name')->value ?? '',
                        $booking->get('booking_customer_email')->value ?? '',
                        $booking->get('booking_customer_phone')->value ?? '',
                        $agencyLabel,
                        $adviserLabel,
                        $booking->get('booking_status')->value ?? '',
                    ]);
                }

                $offset += $chunkSize;
                $storage->resetCache($ids);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error exporting bookings: @message', ['@message' => $e->getMessage()]);
        }

        fclose($handle);
    }
}
