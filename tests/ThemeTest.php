<?php

use Upal\DrupalUnitTestCase;

/**
 * Test for the theme functions.
 */
class ThemeTest extends DrupalUnitTestCase {

  public function testMultipleLineItems() {
    $payment = new \Payment(['currency_code' => 'EUR']);
    $payment->setLineItem(new \PaymentLineItem([
      'name' => 'one',
      'amount' => 3,
      'quantity' => 5,
    ]));
    $payment->setLineItem(new \PaymentLineItem([
      'name' => 'two',
      'amount' => 1,
      'quantity' => 7,
      'recurrence' => (object) [
        'interval_unit' => 'monthly',
      ],
    ]));
    $payment->setLineItem(new \PaymentLineItem([
      'name' => 'three',
      'amount' => 2,
      'quantity' => 11,
      'recurrence' => (object) [
        'interval_unit' => 'weekly',
      ],
    ]));
    $payment->setLineItem(new \PaymentLineItem([
      'name' => 'four',
      'amount' => 13,
      'quantity' => 3,
      'recurrence' => (object) [
        'interval_unit' => 'yearly',
      ],
    ]));
    $output = theme('gocardless_payment_description', ['payment' => $payment]);
    $this->assertEqual([
      'A one-off payment of €15.00',
      'Regular payment of €7.00 per month',
      'Regular payment of €22.00 per week',
      'Regular payment of €39.00 per year',
    ], explode('<br>', $output));
  }

}
