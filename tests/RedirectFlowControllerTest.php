<?php

namespace Drupal\gocardless_payment;

use Drupal\payment_context\NullPaymentContext;
use Upal\DrupalUnitTestCase;

/**
 * Test the redirect flow payment controller.
 */
class RedirectFlowControllerTest extends DrupalUnitTestCase {

  /**
   * Create a test payment.
   */
  public function setUp() {
    parent::setUp();
    $controller = new RedirectFlowController();
    $controller->setClient($this->createMock(ApiClient::class));
    $method = new \PaymentMethod([
      'controller' => $controller,
      'controller_data' => [
        'testmode' => 1,
        'token' => 'testtoken',
        'creditor' => '',
      ],
    ]);
    $context = $this->createMock(NullPaymentContext::class);
    $this->payment = new \Payment([
      'description' => 'gocardless test payment',
      'currency_code' => 'EUR',
      'method' => $method,
      'method_data' => [
        'customer_data' => [
          'given_name' => 'First',
          'last_name' => 'Last',
          'email' => 'test@example.com',
        ],
      ],
      'contextObj' => $context,
    ]);
    $this->payment->setLineItem(new \PaymentLineItem([
      'name' => 'line_item_name',
      'description' => 'line item description',
      'amount' => 5,
      'quantity' => 3,
      'recurrence' => [
        'interval_unit' => 'monthly',
        'day_of_month' => 4,
      ],
    ]));
  }

  /**
   * Remove the test payment.
   */
  public function tearDown() {
    if ($this->payment->pid) {
      entity_delete('payment', $this->payment->pid);
    }
    parent::tearDown();
  }

  /**
   * Test creating a redirect flow and redirecting the user.
   */
  public function testExecute() {
    $payment = $this->payment;
    $client = $payment->method->controller->getClient($payment);

    $post_data = NULL;
    $client->expects($this->once())->method('post')->with('redirect_flows', [], $this->callback(function ($data) use (&$post_data) {
      $post_data = $data;
      return TRUE;
    }))->willReturn([
      'redirect_flows' => [
        'id' => 'RE123',
        'redirect_url' => 'http://gocardless/redirect',
      ],
    ]);
    $payment->contextObj->expects($this->once())->method('redirect')
      ->with('http://gocardless/redirect');

    $payment->method->controller->execute($payment, $client);

    $session_token = $post_data['session_token'];
    $this->assertGreaterThan(8, strlen($session_token));
    unset($post_data['session_token']);
    $signature = gocardless_payment_signature($payment->pid);
    $this->assertStringEndsWith("/gocardless_payment/return/{$payment->pid}/$signature", $post_data['success_redirect_url']);
    unset($post_data['success_redirect_url']);
    $this->assertEqual([
      'description' => 'gocardless test payment',
      'prefilled_customer' => $payment->method_data['customer_data'],
    ], $post_data);

    $this->assertEqual([
      'session_token' => $session_token,
      'redirect_flow_id' => 'RE123',
    ], $payment->gocardless);
  }

  /**
   * Test completing a redirect flow.
   */
  public function testCompleteRedirectFlow() {
    $payment = $this->payment;
    $payment->gocardless['redirect_flow_id'] = 'RE123';
    $payment->gocardless['session_token'] = 'test session token';

    $client = $payment->method->controller->getClient($payment);
    $client->expects($this->once())->method('post')->with("redirect_flows/{$payment->gocardless['redirect_flow_id']}/actions/complete", [], [
      'data' => ['session_token' => 'test session token'],
    ])->willReturn([
      'redirect_flows' => [
        'id' => 'RE123',
        'links' => [
          'creditor' => 'CR123',
          'mandate' => 'MD123',
          'customer' => 'CU123',
          'customer_bank_account' => 'BA123',
        ],
      ],
    ]);

    $payment->method->controller->completeRedirectFlow($payment, $client);

    $this->assertEqual([
      'redirect_flow_id' => 'RE123',
      'session_token' => 'test session token',
      'mandate_id' => 'MD123',
      'customer_id' => 'CU123',
    ], $payment->gocardless);
    $this->assertEqual(PaymentStatus::MANDATE_CREATED, $payment->getStatus()->status);
  }

  /**
   * Test creating a monthly subscription.
   */
  public function testProcessLineItemsMonthly() {
    $payment = $this->payment;
    $payment->gocardless = [
      'redirect_flow_id' => 'RE123',
      'session_token' => 'test session token',
      'mandate_id' => 'MD123',
      'customer_id' => 'CU123',
    ];

    $client = $payment->method->controller->getClient($payment);
    $post_data['subscriptions'] = [
      'amount' => 1500,
      'currency' => 'EUR',
      'name' => 'line item description',
      'interval_unit' => 'monthly',
      'interval' => 1,
      'day_of_month' => 4,
      'metadata' => [
        'pid' => $payment->pid,
        'name' => 'line_item_name',
      ],
      'links' => ['mandate' => $payment->gocardless['mandate_id']],
    ];
    $response_data['subscriptions'] = [];
    $client->expects($this->once())->method('post')
      ->with('subscriptions', [], $post_data)->willReturn($response_data);

    $payment->method->controller->processLineItems($payment, $client);
    $this->assertEqual(PAYMENT_STATUS_SUCCESS, $payment->getStatus()->status);
  }

  /**
   * Test collecting a one-time payment.
   */
  public function testProcessLineItemsOneTime() {
    $payment = $this->payment;
    unset($payment->line_items['line_item_name']->recurrence['interval_unit']);
    $payment->gocardless = [
      'redirect_flow_id' => 'RE123',
      'session_token' => 'test session token',
      'mandate_id' => 'MD123',
      'customer_id' => 'CU123',
    ];

    $client = $payment->method->controller->getClient($payment);
    $post_data['payments'] = [
      'amount' => 1500,
      'currency' => 'EUR',
      'description' => 'line item description',
      'metadata' => [
        'pid' => $payment->pid,
        'name' => 'line_item_name',
      ],
      'links' => ['mandate' => $payment->gocardless['mandate_id']],
    ];
    $response_data['payments'] = [];
    $client->expects($this->once())->method('post')
      ->with('payments', [], $post_data)->willReturn($response_data);

    $payment->method->controller->processLineItems($payment, $client);
    $this->assertEqual(PAYMENT_STATUS_SUCCESS, $payment->getStatus()->status);
  }

  /**
   * Test validaing payment
   */
  public function testValidatePaymentWithoutLineItems() {
    $payment = $this->payment;
    unset($payment->line_items['line_item_name']);
    $this->expectException(\PaymentValidationException::class);
    $payment->method->controller->validate($payment, $payment->method, TRUE);
  }

}
