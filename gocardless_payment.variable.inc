<?php

/**
 * @file
 * Information about configuration variables defined by this module.
 */

/**
 * Implements hook_variable_info().
 */
function gocardless_payment_variable_info($options) {
  $v['gocardless_payment_redirect_message'] = [
    'title' => t('Gocardless redirect message', [], $options),
    'description' => t('Message shown to the user that tells them that they will be redirected to GoCardless after submitting the form.', [], $options),
    'type' => 'text_format',
    'default' => [
      'value' => t('When you submit this form you will be redirected to GoCardless.', [], $options),
      'format' => filter_fallback_format(),
    ],
    'localize' => TRUE,
  ];
  return $v;
}
