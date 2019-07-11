<?php

namespace Drupal\gocardless_payment;

use Drupal\little_helpers\ArrayConfig;
use Drupal\payment_context\NullPaymentContext;
use Upal\DrupalUnitTestCase;

/**
 * Test the payment form.
 */
class CustomerDataFormTest extends DrupalUnitTestCase {

  /**
   * Create a payment stub for testing.
   */
  protected function paymentStub() {
    $controller = new RedirectFlowController();
    $controller->setClient($this->createMock(ApiClient::class));
    $method = new \PaymentMethod([
      'controller' => $controller,
      'controller_data' => [],
    ]);
    ArrayConfig::mergeDefaults($method->controller_data, $controller->controller_data_defaults);
    $context = $this->createMock(NullPaymentContext::class);
    $payment = new \Payment([
      'description' => 'gocardless test payment',
      'currency_code' => 'EUR',
      'method' => $method,
      'contextObj' => $context,
    ]);
    return $payment;
  }

  /**
   * Test rendering the form with an empty context.
   */
  public function testFormEmptyContext() {
    $form = new CustomerDataForm();
    $form_state = [];
    $element = $form->form([], $form_state, $this->paymentStub());
    $this->assertNotEmpty($element['customer_data']['given_name']);
  }

}
