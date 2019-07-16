<?php

namespace Drupal\gocardless_payment;

use Drupal\webform_paymethod_select\PaymentRecurrentController;

/**
 * Implement the PaymentRecurentController interface for legacy support.
 */
class RedirectFlowControllerRecurrent extends RedirectFlowController implements PaymentRecurrentController {
}
