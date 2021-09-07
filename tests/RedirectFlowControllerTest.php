<?php

namespace Drupal\gocardless_payment;

use Drupal\gocardless_payment\Errors\ApiError;
use Drupal\gocardless_payment\Errors\InvalidApiUsage;
use Drupal\little_helpers\Rest\HttpError;
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
      'finish_callback' => 'gocardless_payment_test_finish_callback',
    ]);
    $this->payment->setLineItem(new \PaymentLineItem([
      'name' => 'line_item_name',
      'description' => 'line item description',
      'amount' => 5,
      'quantity' => 3,
      'recurrence' => (object) [
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
    drupal_static_reset('gocardless_payment_test_watchdog');
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
      $post_data = $data['redirect_flows'];
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
      'description' => '€15.00 per month',
      'prefilled_customer' => $payment->method_data['customer_data'],
    ], $post_data);

    $this->assertEqual([
      'session_token' => $session_token,
      'redirect_flow_id' => 'RE123',
    ], $payment->gocardless);
  }

  /**
   * Test creating a redirect flow with configured creditor.
   */
  public function testExecuteWithCreditor() {
    $payment = $this->payment;
    $payment->method->controller_data['creditor'] = 'CR123';
    $client = $payment->method->controller->getClient($payment);

    $post_data = NULL;
    $client->expects($this->once())->method('post')->with('redirect_flows', [], $this->callback(function ($data) use (&$post_data) {
      $post_data = $data['redirect_flows'];
      return TRUE;
    }))->willReturn([
      'redirect_flows' => [
        'id' => 'RE123',
        'redirect_url' => 'http://gocardless/redirect',
      ],
    ]);
    $payment->method->controller->execute($payment, $client);
    $this->assertEqual('CR123', $post_data['links']['creditor']);
  }

  public function testExecuteWithError() {
    $payment = $this->payment;
    $client = $payment->method->controller->getClient($payment);

    $error = new HttpError((object) [
      'code' => 400,
      'error' => 'Bad Request',
      'data' => drupal_json_encode([
        'error' => [
          'message' => 'Invalid document structure',
          'type' => 'invalid_api_usage',
          'code' => 400,
          'errors' => [
            'reason' => 'invalid_document_structure',
            'message' => 'Invalid document structure',
          ],
        ],
      ]),
    ]);
    $e = ApiError::fromHttpError($error);
    $this->assertInstanceOf(InvalidApiUsage::class, $e);
    $client->method('post')->willThrowException($e);
    $payment->method->controller->execute($payment, $client);
    $this->assertEqual($payment->getStatus()->status, PAYMENT_STATUS_FAILED);
  }

  /**
   * Test control flow based on status items.
   */
  public function testRedirectReturn() {
    $builder = $this->getMockBuilder(RedirectFlowController::class)
      ->setMethods(['completeRedirectFlow', 'processLineItems'])
      ->disableOriginalConstructor();

    $controller = $builder->getMock();
    $controller->expects($this->never())->method('completeRedirectFlow');
    $controller->expects($this->never())->method('processLineItems');
    $controller->redirectReturn($this->payment);

    $this->payment->setStatus(new \PaymentStatusItem(PaymentStatus::REDIRECT_FLOW_CREATED));
    $controller = $builder->getMock();
    $controller->expects($this->once())->method('completeRedirectFlow');
    $controller->expects($this->once())->method('processLineItems');
    $controller->redirectReturn($this->payment);
    $this->assertEqual(PAYMENT_STATUS_SUCCESS, $this->payment->getStatus()->status);
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
  }

  /**
   * Test collecting a one-time payment.
   */
  public function testProcessLineItemsOneTime() {
    $payment = $this->payment;
    unset($payment->line_items['line_item_name']->recurrence->interval_unit);
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
  }

  /**
   * Validate a payment against its controller and get the exception thrown.
   *
   * @param \Payment $payment
   *   Payment to validate.
   *
   * @return \PaymentValidationException|null
   *   The validation exception if any was thrown or NULL.
   */
  protected function getValidationException(\Payment $payment) {
    try {
      $payment->method->controller->validate($payment, $payment->method, TRUE);
    }
    catch (\PaymentValidationException $e) {
      return $e;
    }
    return NULL;
  }

  /**
   * Test validating payment without line items.
   */
  public function testValidatePaymentWithoutLineItems() {
    $payment = $this->payment;
    unset($payment->line_items['line_item_name']);
    $e = $this->getValidationException($payment);
    $this->assertNotEmpty($e);
    $this->assertEqual('Can’t process payments without non-empty line items.', $e->getMessage());
  }

  /**
   * Test validating payment with invalid recurrence.
   */
  public function testValidatePaymentWithInvalidRecurrence() {
    $payment = $this->payment;
    $payment->line_items['line_item_name']->recurrence->interval_unit = 'invalid';
    $e = $this->getValidationException($payment);
    $this->assertNotEmpty($e);
    $this->assertEqual('Unsupported recurrence interval_unit: invalid.', $e->getMessage());
  }

  /**
   * Test validating a one-off payment when one-off payments are disabled.
   */
  public function testValidatePaymentWithOneOffDisabled() {
    $payment = $this->payment;
    $payment->line_items['line_item_name']->recurrence->interval_unit = NULL;
    $payment->method->controller_data['one_off_payments'] = FALSE;
    $e = $this->getValidationException($payment);
    $this->assertNotEmpty($e);
    $this->assertEqual('The payment method is configured to not handle one-off payments.', $e->getMessage());
  }

  /**
   * Test validating a one-off payment when one-off payments are enabled.
   */
  public function testValidatePaymentWithOneOffEnabled() {
    $payment = $this->payment;
    $payment->line_items['line_item_name']->recurrence->interval_unit = NULL;
    $e = $this->getValidationException($payment);
    $this->assertEmpty($e);
  }

  /**
   * Test processing recurrence dates.
   */
  public function testProcessDate() {
    $now = new \DateTime('2019-12-21');

    $recurrence = (object) [
      'interval_unit' => 'yearly',
      'day_of_month' => 30,
    ];
    $v = RedirectFlowController::processDate($recurrence, $now);
    // Day of month > 28 will be turned into -1 (the last day of the month).
    // Month defaults to the next month.
    $this->assertEqual(['january', -1], $v);

    $recurrence = (object) [
      'interval_unit' => 'yearly',
    ];
    $v = RedirectFlowController::processDate($recurrence, $now);
    // Null values are left as is.
    $this->assertEqual([NULL, NULL], $v);

    $recurrence = (object) [
      'interval_unit' => 'monthly',
      'day_of_month' => 11,
      'month' => 1,
    ];
    $v = RedirectFlowController::processDate($recurrence, $now);
    $this->assertEqual([NULL, 11], $v);

    $recurrence = (object) [
      'interval_unit' => 'monthly',
    ];
    $v = RedirectFlowController::processDate($recurrence, $now);
    $this->assertEqual([NULL, NULL], $v);

    $recurrence = (object) [
      'interval_unit' => 'weekly',
      'day_of_month' => 11,
      'month' => 1,
    ];
    $v = RedirectFlowController::processDate($recurrence, $now);
    $this->assertEqual([NULL, NULL], $v);
  }

}
