<?php

/**
 * @file
 * Default theme function implementations.
 */

/**
 * Theme function for the payment page description.
 */
function theme_gocardless_payment_description($variables) {
  $lines = [];
  $payment = $variables['payment'];
  $currency = currency_load($payment->currency_code);

  foreach ($payment->line_items as $name => $line_item) {
    $amount = $currency->format($line_item->totalAmount(TRUE));
    if (empty($line_item->recurrence->interval_unit)) {
      array_unshift($lines, t('!amount only once', ['!amount' => $amount]));
    }
    else {
      $interval_unit = $line_item->recurrence->interval_unit;
      $interval_value = $line_item->recurrence->interval_value ?? 1;

      switch ($interval_unit) {
        case 'weekly':
          $lines[] = format_plural(
            $interval_value,
            '!amount per week',
            '!amount every @count weeks',
            ['!amount' => $amount]
          );
          break;

        case 'monthly':
          $lines[] = format_plural(
            $interval_value,
            '!amount per month',
            '!amount every @count months',
            ['!amount' => $amount]
          );
          break;

        case 'yearly':
          $lines[] = format_plural(
            $interval_value,
            '!amount per year',
            '!amount every @count years',
            ['!amount' => $amount]
          );
          break;
      }
    }
  }
  return implode(' '. t('plus additional') . ' ', $lines);
}
