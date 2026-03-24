<?php

namespace Drupal\booking\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\booking\Entity\Enums\BookingStatus;

/**
 * Defines the Booking entity.
 *
 * @ContentEntityType(
 *   id = "booking",
 *   label = @Translation("Booking"),
 *   label_collection = @Translation("Bookings"),
 *   label_singular = @Translation("booking"),
 *   label_plural = @Translation("bookings"),
 *   label_count = @PluralTranslation(
 *     singular = "@count booking",
 *     plural = "@count bookings",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\booking\BookingListBuilder",
 *     "form" = {
 *       "default" = "Drupal\booking\Form\BookingAdminForm",
 *       "delete" = "Drupal\booking\Form\BookingSoftDeleteForm",
 *       "status" = "Drupal\booking\Form\BookingStatusForm",
 *     },
 *   },
 *   base_table = "booking",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "reference",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/booking/{booking}",
 *     "edit-form" = "/admin/structure/booking/{booking}/edit",
 *     "delete-form" = "/admin/structure/booking/{booking}/delete",
 *     "collection" = "/admin/structure/booking",
 *   },
 *   constraints = {
 *     "DoubleBooking" = {},
 *     "ValidBookingStatusTransition" = {}
 *   }
 * )
 */
class BookingEntity extends ContentEntityBase implements ContentEntityInterface
{
  use EntityChangedTrait;
  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array
  {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['reference'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Reference'))
      ->setDescription(new TranslatableMarkup('Unique appointment reference code.'))
      ->setRequired(TRUE)
      ->setReadOnly(TRUE)
      ->setSetting('max_length', 20)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => -10])
      ->setDisplayConfigurable('view', TRUE);

    $fields['booking_customer_name'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup("Customer name"))
      ->setRequired(TRUE)
      ->setSetting('max_length', 128)
      ->addPropertyConstraints('value', [
        'Length' => [
          'min' => 2,
          'max' => 128,
          'minMessage' => 'The customer name must be at least 2 characters long.',
        ]
      ])
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => 0])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['booking_customer_email'] = BaseFieldDefinition::create('email')
      ->setLabel(new TranslatableMarkup('Customer email'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'email_mailto', 'weight' => 1])
      ->setDisplayOptions('form', ['type' => 'email_default', 'weight' => 1])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['booking_customer_phone'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Phone number'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 20)
      ->addPropertyConstraints('value', [
        'Regex' => [
          'pattern' => '/^[\+\d\s\-\(\)]+$/',
          'message' => 'The phone number contains invalid characters. Only digits, spaces, dashes, and plus signs are allowed.',
        ]
      ])
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => 2])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['booking_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(new TranslatableMarkup('Appointment date and time'))
      ->setRequired(TRUE)
      ->setSetting('datetime_type', 'datetime')
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'datetime_default', 'weight' => 3])
      ->setDisplayOptions('form', ['type' => 'datetime_default', 'weight' => 3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['booking_agency'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Agency'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'agency')
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'entity_reference_label', 'weight' => 4])
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 4])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['booking_adviser'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Adviser'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'entity_reference_label', 'weight' => 5])
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 5])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['booking_type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Appointment type'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler_settings', ['target_bundles' => ['services' => 'services']])
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'entity_reference_label', 'weight' => 6])
      ->setDisplayOptions('form', ['type' => 'entity_reference_autocomplete', 'weight' => 6])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['booking_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setDefaultValue(BookingStatus::PENDING->value)
      ->setRequired(TRUE)
      ->setSetting('allowed_values', BookingStatus::labels())
      ->addConstraint('ValidBookingStatusTransition', [])
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'list_default', 'weight' => 7])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 7])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['booking_notes'] = BaseFieldDefinition::create('text_long')
      ->setLabel(new TranslatableMarkup('Notes'))
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'text_default', 'weight' => 8])
      ->setDisplayOptions('form', ['type' => 'text_textarea', 'weight' => 8])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['booking_secret_code'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Verification Code'))
      ->setDescription(new TranslatableMarkup('The 6-digit code for edit access.'))
      ->setReadOnly(TRUE)
      ->setSetting('max_length', 6)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => 10])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  public static function preCreate(EntityStorageInterface $storage, array &$values): void
  {
    parent::preCreate($storage, $values);
    if (empty($values['reference'])) {
      $values['reference'] = 'REF-' . strtoupper(date('Ymd')) . '-' . strtoupper(substr(uniqid(), -4));
    }
  }

  public function getStatusLabel(): string
  {
    $allowed = $this->getFieldDefinition('booking_status')
      ->getSetting('allowed_values');
    $value = $this->get('booking_status')->value;
    return (string) ($allowed[$value] ?? ($value ?? ''));
  }

}
