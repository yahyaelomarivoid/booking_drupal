<?php

/**
 * @file
 * Professional Data Seeder for Booking Module.
 * Execute with: drush scr web/modules/custom/booking/populate_data.php
 */

use Drupal\booking\Entity\AgencyEntity;
use Drupal\booking\Entity\BookingEntity;
use Drupal\user\Entity\User;
use Drupal\booking\Entity\Enums\BookingStatus;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;

// 1. Ensure 'services' vocabulary exists.
$vid = 'services';
if (!Vocabulary::load($vid)) {
  $vocabulary = Vocabulary::create([
    'vid' => $vid,
    'description' => 'Types of booking services available.',
    'name' => 'Services',
  ]);
  $vocabulary->save();
  print "Created Vocabulary: Services\n";
}

// 2. Add Service Terms.
$services = ['General Consultation', 'Technical Support', 'Initial Interview', 'Follow-up Meeting'];
$service_ids = [];
$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
foreach ($services as $name) {
  $existing = $term_storage->loadByProperties(['name' => $name, 'vid' => $vid]);
  if (!$existing) {
    $term = Term::create([
      'name' => $name,
      'vid' => $vid,
    ]);
    $term->save();
    $service_ids[] = $term->id();
    print "Created Service Term: $name\n";
  } else {
    $service_ids[] = reset($existing)->id();
  }
}

// 3. Create Agencies
$agencies = [
    [
        'name' => 'Agadir Central Office',
        'address' => 'Avenue Mohammed V, Agadir 80000',
        'phone' => '0528123456',
        'email' => 'central@agadir-booking.ma',
        'hours' => 'Mon–Fri 09:00–18:00',
    ],
    [
        'name' => 'Talborjt Branch',
        'address' => 'Quartier Talborjt, Agadir',
        'phone' => '0528654321',
        'email' => 'talborjt@agadir-booking.ma',
        'hours' => 'Mon–Fri 08:30–16:30',
    ],
];

$agency_ids = [];
$agency_storage = \Drupal::entityTypeManager()->getStorage('agency');
foreach ($agencies as $data) {
    $existing = $agency_storage->loadByProperties(['name' => $data['name']]);
    if (!$existing) {
        $agency = AgencyEntity::create([
            'name' => $data['name'],
            'address' => $data['address'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'operating_hours' => $data['hours'],
            'active' => TRUE,
        ]);
        $agency->save();
        $agency_ids[] = $agency->id();
        print "Created Agency: " . $data['name'] . "\n";
    } else {
        $agency_ids[] = reset($existing)->id();
    }
}

// 4. Create Adviser Users
$advisers = ['john_adviser', 'jane_adviser'];
$adviser_uids = [1]; // Include admin
$user_storage = \Drupal::entityTypeManager()->getStorage('user');
foreach ($advisers as $username) {
  $user = $user_storage->loadByProperties(['name' => $username]);
  if (!$user) {
    $user = User::create([
      'name' => $username,
      'mail' => "$username@example.com",
      'pass' => 'password',
      'status' => 1,
    ]);
    $user->save();
    print "Created Adviser User: $username\n";
    $adviser_uids[] = $user->id();
  } else {
    $adviser_uids[] = reset($user)->id();
  }
}

// 5. Create Fake Bookings
$customers = ['Ahmed Idrissi', 'Fatima Zahra', 'Omar El Amrani'];

for ($i = 0; $i < 5; $i++) {
    $random_customer = $customers[array_rand($customers)];
    $random_agency = $agency_ids[array_rand($agency_ids)];
    $random_adviser = $adviser_uids[array_rand($adviser_uids)];
    $random_service_tid = $service_ids[array_rand($service_ids)];

    $timestamp = strtotime('+' . rand(1, 30) . ' days ' . rand(9, 16) . ':00:00');
    $date_string = date('Y-m-d\TH:i:s', $timestamp);

    $booking = BookingEntity::create([
        'booking_customer_name' => $random_customer,
        'booking_customer_email' => strtolower(str_replace(' ', '.', $random_customer)) . '@gmail.com',
        'booking_customer_phone' => '0661' . rand(100000, 999999),
        'booking_date' => $date_string,
        'booking_agency' => $random_agency,
        'booking_adviser' => $random_adviser,
        'booking_type' => $random_service_tid,
        'booking_status' => BookingStatus::PENDING->value,
        'booking_notes' => 'Pre-populated data for testing.',
    ]);

    $booking->save();
    print "Created Booking: " . $booking->label() . " for " . $random_customer . "\n";
}

print "\nDatabase population complete!\n";