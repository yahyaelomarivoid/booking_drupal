<?php

namespace Drupal\booking\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityChangedTrait;

/**
 * Defines the Agency entity.
 *
 * An agency is a physical location where advisers are based and where
 * appointments can be booked.
 *
 * @ContentEntityType(
 *   id = "agency",
 *   label = @Translation("Agency"),
 *   label_collection = @Translation("Agencies"),
 *   label_singular = @Translation("agency"),
 *   label_plural = @Translation("agencies"),
 *   label_count = @PluralTranslation(
 *     singular = "@count agency",
 *     plural = "@count agencies",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *   },
 *   base_table = "agency",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "name",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/agency/{agency}",
 *     "edit-form" = "/admin/structure/agency/{agency}/edit",
 *     "delete-form" = "/admin/structure/agency/{agency}/delete",
 *     "collection" = "/admin/structure/agency",
 *   },
 * )
 */
class AgencyEntity extends ContentEntityBase implements ContentEntityInterface
{

  use EntityChangedTrait;
  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array
  {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Agency name'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 128)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => -10])
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => -10])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['address'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Address'))
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => 0])
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['phone'] = BaseFieldDefinition::create('telephone')
      ->setLabel(t('Phone'))
      ->setSetting('max_length', 10)
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'telephone', 'weight' => 1])
      ->setDisplayOptions('form', ['type' => 'telephone', 'weight' => 1])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'email_mailto', 'weight' => 2])
      ->setDisplayOptions('form', ['type' => 'email_default', 'weight' => 2])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['operating_hours'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Operating hours'))
      ->setDescription(t('e.g. Monday–Friday 9h30–16h'))
      ->setDisplayOptions('view', ['label' => 'above', 'type' => 'string', 'weight' => 3])
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 3])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['active'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', ['type' => 'boolean_checkbox', 'weight' => 4]);

    return $fields;
  }

}
