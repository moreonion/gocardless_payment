<?php

namespace Drupal\gocardless_payment;

/**
 * Namespace class for status name constants.
 */
class PaymentStatus {

  const REDIRECT_FLOW_CREATED = 'gocardless_payment_redirect_flow_created';
  const REDIRECT_FLOW_RETURNED = 'gocardless_payment_redirect_flow_returned';
  const MANDATE_CREATED = 'gocardless_payment_mandate_created';

}
