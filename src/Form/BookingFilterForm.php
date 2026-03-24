<?php

namespace Drupal\booking\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\booking\Entity\Enums\BookingStatus;

/**
 * Provides an exposed filter form for the admin bookings list.
 */
class BookingFilterForm extends FormBase {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('request_stack')
    );
  }

  public function getFormId() {
    return 'booking_filter_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = $this->requestStack->getCurrentRequest();
    
    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['form--inline', 'clearfix']],
    ];

    $form['filters']['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#default_value' => $request->query->get('search'),
      '#size' => 30,
      '#placeholder' => $this->t('Ref or Customer...'),
    ];

    $form['filters']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => ['' => $this->t('- All -')] + BookingStatus::labels(),
      '#default_value' => $request->query->get('status'),
    ];

    $agencies = $this->entityTypeManager->getStorage('agency')->loadMultiple();
    $agency_options = ['' => $this->t('- All -')];
    foreach ($agencies as $agency) {
      $agency_options[$agency->id()] = $agency->label();
    }

    $form['filters']['agency'] = [
      '#type' => 'select',
      '#title' => $this->t('Agency'),
      '#options' => $agency_options,
      '#default_value' => $request->query->get('agency'),
    ];

    $form['filters']['actions'] = [
      '#type' => 'actions',
    ];
    $form['filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];
    
    // Only show "Reset" link if there are active query parameters
    if ($request->query->has('search') || $request->query->has('status') || $request->query->has('agency')) {
      $form['filters']['actions']['reset'] = [
        '#type' => 'link',
        '#title' => $this->t('Reset'),
        '#url' => Url::fromRoute('entity.booking.collection'),
        '#attributes' => ['class' => ['button']],
      ];
    }

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $query = [];
    if ($search = $form_state->getValue('search')) {
      $query['search'] = $search;
    }
    if ($status = $form_state->getValue('status')) {
      $query['status'] = $status;
    }
    if ($agency = $form_state->getValue('agency')) {
      $query['agency'] = $agency;
    }

    // Redirect back to the collection with query parameters appended
    $form_state->setRedirect('entity.booking.collection', [], ['query' => $query]);
  }
}
