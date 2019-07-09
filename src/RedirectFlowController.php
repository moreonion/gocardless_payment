<?php

namespace Drupal\gocardless_payment;

/**
 * Payment controller for gocardless redirect flow.
 */
class RedirectFlowController extends \PaymentMethodController {

  public $controller_data_defaults = [
    'testmode' => FALSE,
    'token' => '',
    'creditor' => '',
    'input_settings' => [
      'given_name' => [
        'display' => 'hidden',
        'keys' => ['first_name', 'given_name'],
        'required' => FALSE,
        'display_other' => 'hidden',
      ],
      'family_name' => [
        'display' => 'hidden',
        'keys' => ['last_name', 'family_name'],
        'required' => FALSE,
        'display_other' => 'hidden',
      ],
      'company_name' => [
        'display' => 'hidden',
        'keys' => ['company_name'],
        'required' => FALSE,
        'display_other' => 'hidden',
      ],
      'email' => [
        'display' => 'hidden',
        'keys' => ['email'],
        'required' => FALSE,
        'display_other' => 'hidden',
      ],
      'phone' => [
        'display' => 'hidden',
        'keys' => ['phone_number', 'mobile_number'],
        'required' => FALSE,
        'display_other' => 'hidden',
      ],
      'address_line1' => [
        'display' => 'hidden',
        'keys' => ['street_address', 'address_line1'],
        'required' => FALSE,
        'display_other' => 'always',
      ],
      'address_line2' => [
        'display' => 'hidden',
        'keys' => ['street_address2', 'address_line2'],
        'required' => FALSE,
        'display_other' => 'always',
      ],
      'address_line3' => [
        'display' => 'hidden',
        'keys' => ['street_address3', 'address_line3'],
        'required' => FALSE,
        'display_other' => 'hidden',
      ],
      'city' => [
        'display' => 'hidden',
        'keys' => ['city'],
        'required' => FALSE,
        'display_other' => 'always',
      ],
      'postal_code' => [
        'display' => 'hidden',
        'keys' => ['postcode', 'zip_code', 'postal_code'],
        'required' => FALSE,
        'display_other' => 'always',
      ],
      'region' => [
        'display' => 'hidden',
        'keys' => ['state', 'region'],
        'required' => FALSE,
        'display_other' => 'always',
      ],
      'country_code' => [
        'display' => 'hidden',
        'keys' => ['country', 'country_code'],
        'required' => FALSE,
        'display_other' => 'always',
      ],
    ],
  ];

  /**
   * Create a new controller instance.
   */
  public function __construct() {
    $this->title = t('Gocardless redirect flow');

    $this->payment_configuration_form_elements_callback = 'payment_forms_payment_form';
    $this->payment_method_configuration_form_elements_callback = 'payment_forms_method_configuration_form';
  }

  /**
   * Get a form for when a payment is being made.
   */
  public function paymentForm() {
    return new CustomerDataForm();
  }

  /**
   * Get fhe form for configuring the payment method.
   */
  public function configurationForm() {
    return new RedirectFlowConfigurationForm();
  }

  /**
   * Get API-client based on the controller settings.
   *
   * @return \Drupal\gocardless_payment\ApiClient
   */
  public function getClient(\Payment $payment) {
    return ApiClient::fromConfig($payment->method->controller_data);
  }

  /**
   * Executes a transaction.
   */
  public function execute(\Payment $payment, ApiClient $client = NULL) {
    $client = $client ?? $this->getClient($payment);
    entity_save('payment', $payment);
    $data['session_token'] = drupal_random_key(8);
    $signature = gocardless_payment_signature($payment->pid);
    $data['success_redirect_url'] = url("gocardless_payment/return/{$payment->pid}/$signature", ['absolute' => TRUE]);
    $data['description'] = t($payment->description, $payment->description_arguments);
    $data['prefilled_customer'] = $payment->method_data['customer_data'];
    if ($payment->method->controller_data['creditor']) {
      $data['links']['creditor'] = '';
    }
    $response = $client->post('redirect_flows', [], $data);
    $payment->gocardless = [
      'redirect_flow_id' => $response['redirect_flows']['id'],
      'session_token' => $data['session_token'],
    ];
    $payment->setStatus(new \PaymentStatusItem(PaymentStatus::REDIRECT_FLOW_CREATED));
    entity_save('payment', $payment);
    $payment->contextObj->redirect($response['redirect_flows']['redirect_url'], []);
  }

  /**
   * Complete the redirect flow in order to create the customer and mandate.
   */
  public function completeRedirectFlow(\Payment $payment, ApiClient $client = NULL) {
    $flow_id = $payment->gocardless['redirect_flow_id'];
    $data['data']['session_token'] = $payment->gocardless['session_token'];
    $response = $client->post("redirect_flows/$flow_id/actions/complete", [], $data);
    $payment->gocardless['mandate_id'] = $response['redirect_flows']['links']['mandate'];
    $payment->gocardless['customer_id'] = $response['redirect_flows']['links']['customer'];
    $payment->setStatus(new \PaymentStatusItem(PaymentStatus::MANDATE_CREATED));
  }

}
