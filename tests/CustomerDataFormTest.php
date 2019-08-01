<?php

namespace Drupal\gocardless_payment;

use Drupal\little_helpers\ArrayConfig;
use Drupal\little_helpers\ElementTree;
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
    $payment = $this->paymentStub();
    $form = $payment->method->controller->paymentForm();
    $form_state = [];
    $element = $form->form([], $form_state, $this->paymentStub());
    $this->assertNotEmpty($element['customer_data']['given_name']);
  }

  /**
   * Test rendering the form with an empty context.
   */
  public function testValidateEmptyContextWithoutChanges() {
    $form = new CustomerDataForm();
    $form_state = ['values' => []];
    $payment = $this->paymentStub();
    $element = $form->form([], $form_state, $payment) + [
      '#type' => 'container',
      '#parents' => [],
    ];
    unset($element['redirect_message']);
    ElementTree::applyRecursively($element, function (&$element, $key, &$parent) use (&$form_state) {
      if ($parent) {
        $element['#parents'] = array_merge($parent['#parents'], [$key]);
      }
      if (!in_array($element['#type'], ['fieldset', 'container'])) {
        $value = isset($element['#default_value']) ? $element['#default_value'] : '';
        drupal_array_set_nested_value($form_state['values'], $element['#parents'], $value);
      }
    });
    $form->validate($element, $form_state, $payment);
    $this->assertEqual('', $payment->method_data['customer_data']['address_line1']);
  }

}
