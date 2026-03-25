<?php

namespace Drupal\booking;

class AdviserBookingListBuilder extends BookingListBuilder
{

    protected function getEntityIds()
    {
        $query = $this->getStorage()->getQuery()
            ->accessCheck(TRUE)
            ->condition('booking_adviser', \Drupal::currentUser()->id()) // Keep the hard filter
            ->pager($this->limit);

        $header = $this->buildHeader();
        $query->tableSort($header);
        $request = \Drupal::request();

        // Re-enable the search box logic
        if ($search = $request->query->get('search')) {
            $orGroup = $query->orConditionGroup()
                ->condition('reference', '%' . $search . '%', 'LIKE')
                ->condition('booking_customer_name', '%' . $search . '%', 'LIKE');
            $query->condition($orGroup);
        }

        // Handle the Status filter (preventing 'deleted' by default)
        $status = $request->query->get('status');
        if ($status) {
            $query->condition('booking_status', $status);
        } else {
            $query->condition('booking_status', 'deleted', '<>');
        }

        return $query->execute();
    }


}