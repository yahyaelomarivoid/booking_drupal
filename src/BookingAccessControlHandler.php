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

    // 1. Super-admin bypass (users who can manage everything).
    if ($account->hasPermission('administer booking settings') || $account->hasPermission('administer booking')) {
      return AccessResult::allowed();
    }

    $is_assigned_adviser = ($entity->get('booking_adviser')->target_id == $account->id());

    switch ($operation) {
      case 'view':
        // Allow if they have global view permission.
        if ($account->hasPermission('access booking overview')) {
          return AccessResult::allowed();
        }
        // Allow if they are the assigned adviser for THIS specific booking.
        return AccessResult::allowedIfHasPermission($account, 'view own managed bookings')
          ->andIf(AccessResult::allowedIf($is_assigned_adviser))
          ->addCacheableDependency($entity);

      case 'update':
        // Allow if they have global edit permission.
        if ($account->hasPermission('edit booking entity')) {
          return AccessResult::allowed();
        }
        // Allow if they are the assigned adviser AND have permission.
        if ($account->hasPermission('view own managed bookings') && $is_assigned_adviser) {
          return AccessResult::allowed();
        }
        // Special case for customers using the verification code (usually handled in the form, 
        // but we allow the permission check here for API consistency).
        return AccessResult::allowedIfHasPermission($account, 'edit own booking');

      case 'delete':
        // Allow if global delete permission.
        if ($account->hasPermission('delete booking entity')) {
          return AccessResult::allowed();
        }
        // Advisers can "delete" (cancel) their own bookings if they have permission.
        return AccessResult::allowedIfHasPermission($account, 'view own managed bookings')
          ->andIf(AccessResult::allowedIf($is_assigned_adviser))
          ->addCacheableDependency($entity);
    }

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
