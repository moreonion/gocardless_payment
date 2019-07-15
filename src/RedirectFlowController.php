<?php

namespace Drupal\gocardless_payment;

use Drupal\gocardless_payment\Errors\ApiError;

/**
 * Payment controller for gocardless redirect flow.
 */
class RedirectFlowController extends \PaymentMethodController {

  const SUPPORTED_CURRENCIES = ['GBP', 'EUR', 'SEK', 'DKK', 'AUD', 'NZD', 'CAD'];
  protected $client = NULL;

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
      'phone_number' => [
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
   * Set the client.
   *
   * @param \Drupal\gocardless_payment\ApiClient $client
   *   The API-client to use for this controller.
   */
  public function setClient(ApiClient $client) {
    $this->client = $client;
  }

  /**
   * Get API-client based on the controller settings.
   *
   * @param \Payment $payment
   *   The payment to get the API-client for.
   *
   * @return \Drupal\gocardless_payment\ApiClient
   *   The API-client to use for this payment.
   */
  public function getClient(\Payment $payment) {
    if (!$this->client) {
      $this->client = ApiClient::fromConfig($payment->method->controller_data);
    }
    return $this->client;
  }

  /**
   * Check if the payment can be processed by this payment method.
   */
  public function validate(\Payment $payment, \PaymentMethod $method, $strict) {
    parent::validate($payment, $method, $strict);

    if ($strict) {
      if (!in_array($payment->currency_code, static::SUPPORTED_CURRENCIES)) {
        throw new \PaymentValidationException(t('Unsupported currency for gocardless: @code', ['@code' => $payment->currency_code]));
      }

      $at_least_one_line_item = FALSE;
      foreach ($payment->line_items as $line_item) {
        if ($line_item->quantity > 0) {
          $at_least_one_line_item = TRUE;
        }
        if (!empty($line_item->recurrence->interval_unit)) {
          if (!in_array($line_item->recurrence->interval_unit, ['yearly', 'monthly', 'weekly'])) {
            throw new \PaymentValidationException(t('Unsupported recurrence interval_unit: @unit.', ['@unit' => $line_item->recurrence->interval_unit]));
          }
        }
      }
      if (!$at_least_one_line_item) {
        throw new \PaymentValidationException(t('Can’t process payments without non-empty line items.'));
      }
    }
  }

  /**
   * Executes a transaction.
   */
  public function execute(\Payment $payment) {
    entity_save('payment', $payment);
    $data['session_token'] = drupal_random_key(8);
    $signature = gocardless_payment_signature($payment->pid);
    $data['success_redirect_url'] = url("gocardless_payment/return/{$payment->pid}/$signature", ['absolute' => TRUE]);
    $data['description'] = format_string($payment->description, $payment->description_arguments);
    $data['prefilled_customer'] = $payment->method_data['customer_data'];
    if ($creditor = $payment->method->controller_data['creditor']) {
      $data['links']['creditor'] = $creditor;
    }
    try {
      $response = $this->getClient($payment)->post('redirect_flows', [], ['redirect_flows' => $data]);
      $payment->gocardless = [
        'redirect_flow_id' => $response['redirect_flows']['id'],
        'session_token' => $data['session_token'],
      ];
      $payment->setStatus(new \PaymentStatusItem(PaymentStatus::REDIRECT_FLOW_CREATED));
      entity_save('payment', $payment);
      $payment->contextObj->redirect($response['redirect_flows']['redirect_url'], []);
    }
    catch (ApiError $e) {
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_FAILED));
    }
  }

  /**
   * Callback for when the user returned from gocardless’ payment pages.
   */
  public function redirectReturn(\Payment $payment) {
    if ($payment->getStatus()->status == PaymentStatus::REDIRECT_FLOW_CREATED) {
      $payment->setStatus(new \PaymentStatusItem(PaymentStatus::REDIRECT_FLOW_RETURNED));
    }
    try {
      if ($payment->getStatus()->status == PaymentStatus::REDIRECT_FLOW_RETURNED) {
        $this->completeRedirectFlow($payment);
        $payment->setStatus(new \PaymentStatusItem(PaymentStatus::MANDATE_CREATED));
      }
      if ($payment->getStatus()->status == PaymentStatus::MANDATE_CREATED) {
        $this->processLineItems($payment);
        $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_SUCCESS));
      }
    }
    catch (ApiError $e) {
      $payment->setStatus(new \PaymentStatusItem(PAYMENT_STATUS_FAILED));
    }
    $payment->finish();
  }

  /**
   * Complete the redirect flow in order to create the customer and mandate.
   */
  public function completeRedirectFlow(\Payment $payment) {
    $flow_id = $payment->gocardless['redirect_flow_id'];
    $data['data']['session_token'] = $payment->gocardless['session_token'];
    $response = $this->getClient($payment)
      ->post("redirect_flows/$flow_id/actions/complete", [], $data);
    $payment->gocardless['mandate_id'] = $response['redirect_flows']['links']['mandate'];
    $payment->gocardless['customer_id'] = $response['redirect_flows']['links']['customer'];
  }

  /**
   * Create payments and subscriptions based on the line-items.
   */
  public function processLineItems(\Payment $payment) {
    $client = $this->getClient($payment);
    $currency = currency_load($payment->currency_code);
    foreach ($payment->line_items as $name => $line_item) {
      if ($line_item->quantity == 0) {
        continue;
      }
      $data = [
        'currency' => $payment->currency_code,
        'amount' => (int) round($line_item->totalAmount(TRUE) * $currency->subunits),
      ];
      $data['metadata'] = [
        'pid' => (string) $payment->pid,
        'name' => $name,
      ];
      $data['links']['mandate'] = $payment->gocardless['mandate_id'];
      if (!empty($line_item->recurrence->interval_unit)) {
        $recurrence = $line_item->recurrence;
        $data += array_filter([
          'name' => $line_item->description,
          'interval_unit' => $recurrence->interval_unit,
          'interval' => $recurrence->interval_value ?? 1,
          'day_of_month' => $recurrence->day_of_month ?? NULL,
          'count' => $recurrence->count ?? NULL,
        ]);
        $data = ['subscriptions' => $data];
        $response = $client->post('subscriptions', [], $data);
      }
      else {
        $data['description'] = $line_item->description;
        $data = ['payments' => $data];
        $response = $client->post('payments', [], $data);
      }
    }
  }

}
