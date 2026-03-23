<?php

/**
 * @file
 * Programmatically create fields for the Adviser (User) entity.
 * Run with: drush scr web/modules/custom/booking/update_user_fields.php
 */

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

$entity_type = 'user';
$bundle = 'user';

$fields = [
  'field_specializations' => [
    'type' => 'entity_reference',
    'label' => 'Specializations',
    'settings' => [
      'target_type' => 'taxonomy_term',
      'handler' => 'default:taxonomy_term',
      'handler_settings' => [
        'target_bundles' => ['services' => 'services'],
      ],
    ],
    'cardinality' => -1, // Multiple
  ],
  'field_working_hours' => [
    'type' => 'string',
    'label' => 'Working Hours',
    'description' => 'E.g., 09:00-17:00 or JSON format.',
    'cardinality' => 1,
  ],
];

foreach ($fields as $field_name => $info) {
  // Create Storage if not exists
  if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => $info['type'],
      'settings' => $info['settings'] ?? [],
      'cardinality' => $info['cardinality'] ?? 1,
    ])->save();
    echo "Created storage for $field_name\n";
  }

  // Create Instance if not exists
  if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'label' => $info['label'],
      'settings' => $info['settings'] ?? [],
      'description' => $info['description'] ?? '',
      'required' => FALSE,
    ])->save();
    
    // Assign to default form display
    \Drupal::service('entity_display.repository')
      ->getFormDisplay($entity_type, $bundle, 'default')
      ->setComponent($field_name, ['type' => ($info['type'] === 'entity_reference' ? 'options_buttons' : 'string_textfield')])
      ->save();
      
    echo "Created instance for $field_name\n";
  }
}

\Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
echo "Field update complete!\n";
