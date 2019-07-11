<?php

namespace Drupal\gocardless_payment;

/**
 * Test module level functionality.
 */
class ModelTest extends \DrupalUnitTestCase {

  /**
   * Test that all our custom statuses are registered and are ‘pending’.
   */
  public function testPaymentStatusesRegistered() {
    $info = payment_statuses_info();
    $statuses = [
      PaymentStatus::REDIRECT_FLOW_CREATED,
      PaymentStatus::REDIRECT_FLOW_RETURNED,
      PaymentStatus::MANDATE_CREATED,
    ];
    foreach ($statuses as $status) {
      $this->assertArrayHasKey($status, $info);
      payment_status_is_or_has_ancestor($status, PAYMENT_STATUS_PENDING);
    }
  }

}
