<?php

namespace Drupal\booking\Service;

use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\booking\Entity\BookingEntity;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Service to handle booking-related emails using Twig templates.
 */
class BookingMailService {

  use StringTranslationTrait;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new BookingMailService.
   */
  public function __construct(MailManagerInterface $mail_manager, RendererInterface $renderer, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory) {
    $this->mailManager = $mail_manager;
    $this->renderer = $renderer;
    $this->logger = $logger_factory->get('booking');
    $this->configFactory = $config_factory;
  }

  /**
   * Sends a status notification email.
   */
  public function sendStatusNotification(BookingEntity $entity) {
    if (!$this->isEnabled()) {
      return;
    }
    $to = $entity->get('booking_customer_email')->value;
    if (empty($to)) {
      return;
    }

    $build = [
      '#theme' => 'booking_email_status',
      '#reference' => $entity->get('reference')->value,
      '#status_label' => $entity->getStatusLabel(),
      '#customer_name' => $entity->get('booking_customer_name')->value,
    ];

    $params = [
      'subject' => $this->t('Mise à jour de votre réservation : @ref', ['@ref' => $build['#reference']]),
      'body' => $this->renderer->renderInIsolation($build),
    ];

    $this->sendMail('status_notification', $to, $params);
  }

  /**
   * Sends an access code email.
   */
  public function sendAccessCode(BookingEntity $entity, string $code) {
    if (!$this->isEnabled()) {
      return;
    }
    $to = $entity->get('booking_customer_email')->value;
    if (empty($to)) {
      return;
    }

    $build = [
      '#theme' => 'booking_email_access_code',
      '#reference' => $entity->get('reference')->value,
      '#code' => $code,
    ];

    $params = [
      'subject' => $this->t('Votre code de vérification pour la réservation @ref', ['@ref' => $build['#reference']]),
      'body' => $this->renderer->renderInIsolation($build),
    ];

    $this->sendMail('edit_access_code', $to, $params);
  }

  /**
   * Sends a booking confirmation email.
   */
  public function sendBookingConfirmation(BookingEntity $entity) {
    if (!$this->isEnabled()) {
      return;
    }
    $to = $entity->get('booking_customer_email')->value;
    if (empty($to)) {
      return;
    }

    $agency = $entity->get('booking_agency')->entity;
    $agency_name = $agency ? $agency->label() : $this->t('Inconnue');

    $build = [
      '#theme' => 'booking_email_confirmation',
      '#reference' => $entity->get('reference')->value,
      '#customer_name' => $entity->get('booking_customer_name')->value,
      '#booking_date' => str_replace('T', ' ', $entity->get('booking_date')->value),
      '#agency_name' => $agency_name,
    ];

    $params = [
      'subject' => $this->t('Confirmation de votre réservation : @ref', ['@ref' => $build['#reference']]),
      'body' => $this->renderer->renderInIsolation($build),
    ];

    $this->sendMail('booking_confirmation', $to, $params);
  }

  /**
   * Sends a notification email to the assigned adviser.
   */
  public function sendAdviserNotification(BookingEntity $entity, string $type = 'status_change') {
    if (!$this->isEnabled()) {
      return;
    }
    /** @var \Drupal\user\UserInterface $adviser */
    $adviser = $entity->get('booking_adviser')->entity;
    if (!$adviser || empty($adviser->getEmail())) {
      return;
    }

    $to = $adviser->getEmail();

    $build = [
      '#theme' => 'booking_email_adviser',
      '#reference' => $entity->get('reference')->value,
      '#customer_name' => $entity->get('booking_customer_name')->value,
      '#booking_date' => str_replace('T', ' ', $entity->get('booking_date')->value),
      '#status_label' => $entity->getStatusLabel(),
      '#type' => $type,
    ];

    $subject = ($type === 'new')
      ? $this->t('Nouvelle réservation assignée : @ref', ['@ref' => $build['#reference']])
      : $this->t('Mise à jour du statut : @ref', ['@ref' => $build['#reference']]);

    $params = [
      'subject' => $subject,
      'body' => $this->renderer->renderInIsolation($build),
    ];

    $this->sendMail('adviser_notification', $to, $params);
  }

  /**
   * Checks if notifications are enabled in settings.
   */
  public function isEnabled(): bool {
    return (bool) ($this->configFactory->get('booking.settings')->get('notifications_enabled') ?? TRUE);
  }

  /**
   * Internal helper to send the email.
   */
  protected function sendMail(string $key, string $to, array $params) {
    if (!$this->isEnabled()) {
      $this->logger->notice('Email notification suppressed (disabled in settings). Key: @key, To: @to', [
        '@key' => $key,
        '@to' => $to,
      ]);
      return;
    }

    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    try {
      $result = $this->mailManager->mail('booking', $key, $to, $langcode, $params);
      if (!$result['result']) {
        throw new \Exception('Mail delivery failed.');
      }
    } catch (\Exception $e) {
      $this->logger->error('Failed to send email "@key" to @to: @message', [
        '@key' => $key,
        '@to' => $to,
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
