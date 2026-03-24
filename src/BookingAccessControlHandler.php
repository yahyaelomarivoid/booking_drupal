<?php

namespace Drupal\booking;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Booking entity.
 */
class BookingAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\booking\Entity\BookingEntity $entity */

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'access booking overview');

      case 'update':
        // Allow if admin OR if the user has the 'edit own booking' permission.
        // (Note: The actual reference check is handled in the BookingEditForm).
        return AccessResult::allowedIfHasPermission($account, 'edit booking entity')
          ->orIf(AccessResult::allowedIfHasPermission($account, 'edit own booking'));

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete booking entity');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context = [], $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add booking entity')
      ->orIf(AccessResult::allowedIfHasPermission($account, 'create booking'));
  }

}
