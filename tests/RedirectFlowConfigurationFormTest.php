<?php

namespace Drupal\gocardless_payment;

use Drupal\little_helpers\ArrayConfig;
use Upal\DrupalUnitTestCase;

/**
 * Test the controller config form.
 */
class RedirectFlowControllerFormTest extends DrupalUnitTestCase {

  /**
   * Create payment method stub.
   */
  protected function methodStub() {
    $controller = new RedirectFlowController();
    $controller->setClient($this->createMock(ApiClient::class));
    $method = new \PaymentMethod([
      'controller' => $controller,
      'controller_data' => [],
    ]);
    ArrayConfig::mergeDefaults($method->controller_data, $controller->controller_data_defaults);
    return $method;
  }

  /**
   * Test rendering the form with the default config.
   */
  public function testFormDefaultConfig() {
    $method = $this->methodStub();
    $form = $method->controller->configurationForm();
    $form_state = [];
    $element = $form->form([], $form_state, $this->methodStub());
    $this->assertNotEmpty($element['input_settings']['given_name']);
  }

}
