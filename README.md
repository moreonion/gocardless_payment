[![Build Status](https://travis-ci.com/moreonion/gocardless_payment.svg?branch=7.x-1.x)](https://travis-ci.com/moreonion/gocardless_payment) [![codecov](https://codecov.io/gh/moreonion/gocardless_payment/branch/7.x-1.x/graph/badge.svg)](https://codecov.io/gh/moreonion/gocardless_payment)

# GoCardless payment

This module implements a [GoCardless](https://gocardless.com) payment method for the [payment module](https://www.drupal.org/project/payment).


## Features

* One-time payments.
* Recurring payments using [payment recurrence](https://www.drupal.org/project/payment_recurrence).


# Requirements

* PHP 7.0+
* Drupal 7
* [currency](https://www.drupal.org/project/currency)
* [little_helpers](https://www.drupal.org/project/little_helpers)
* [payment](https://www.drupal.org/project/payment)
* [payment_context](https://www.drupal.org/project/payment_context)
* [payment_controller_data](https://www.drupal.org/project/payment_controller_data)
* [payment_forms](https://www.drupal.org/project/payment_forms)
* [psr0](https://www.drupal.org/project/psr0)
* [variable](https://www.drupal.org/project/variable)

The module directly interacts with the GoCardless REST-API without requiring a PHP library.


# Usage

1. Install and enable the gocardless_payment module (ie. `drush en gocardless_payment`).
2. Create and configure a new payment method in `admin/config/services/payment`.

