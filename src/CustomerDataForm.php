<?php

namespace Drupal\gocardless_payment;

use Drupal\little_helpers\ElementTree;
use Drupal\payment_forms\PaymentFormInterface;

/**
 * Form for collecting customer data.
 */
class CustomerDataForm implements PaymentFormInterface {

  /**
   * Generate the form for gocardless payments.
   */
  public function form(array $element, array &$form_state, \Payment $payment) {
    $context = $payment->contextObj;
    $settings = $payment->method->controller_data['input_settings'];

    $data_fieldset = static::extraElements();

    // Set default values from context and remove #required.
    if ($context) {
      ElementTree::applyRecursively($data_fieldset, function (&$element, $key) use ($context, $settings) {
        if (!in_array($element['#type'], ['container', 'fieldset'])) {
          foreach ($settings[$key]['keys'] as $k) {
            if ($value = $context->value($k)) {
              $element['#default_value'] = $value;
              break;
            }
          }
        }
        $element['#controller_required'] = !empty($element['#required']);
        unset($element['#required']);
      });
    }

    $display = function ($element, $key, $mode = 'display') use ($settings) {
      $d = $settings[$key][$mode];
      return ($d == 'always') || (empty($element['#default_value']) && $d == 'ifnotset');
    };

    // Set visibility.
    ElementTree::applyRecursively($data_fieldset, function (&$element, $key, &$parent) use ($display) {
      $element += ['#access' => FALSE];
      if (!in_array($element['#type'], ['fieldset', 'container'])) {
        $element['#access'] = $display($element, $key, 'display');
      }
      // If an element is visible its parent should be visible too.
      if ($parent && $element['#access']) {
        $parent['#access'] = TRUE;
      }
    }, TRUE);
    ElementTree::applyRecursively($data_fieldset, function (&$element, $key, &$parent) use ($display) {
      if ($parent && $parent['#access']) {
        // Give child elements of visible fieldsets a chance to be displayed.
        if ($element['#type'] != 'fieldset' && !$element['#access']) {
          $element['#access'] = $display($element, $key, 'display_other');
        }
      }
    });

    $element['customer_data'] = $data_fieldset;
    $element += [
      '#description' => t('When you submit this form you will be redirected to Gocardless to make the payment.'),
    ];
    return $element;
  }

  /**
   * Validate the form and store the values in the payment’s method_data.
   */
  public function validate(array $element, array &$form_state, \Payment $payment) {
    $values = drupal_array_get_nested_value($form_state['values'], $element['#parents']);
    $pd = &$element['customer_data'];
    ElementTree::applyRecursively($pd, function (&$element, $key) {
      if (!empty($element['#controller_required']) && empty($element['#value'])) {
        if (isset($element['#title'])) {
          form_error($element, t('!name field is required.', ['!name' => $element['#title']]));
        }
        else {
          form_error($element);
        }
      }
    });
    $payment->method_data += $values;
  }

  /**
   * Get all the customer data form elements.
   */
  public static function extraElements() {
    require_once DRUPAL_ROOT . '/includes/locale.inc';
    $element = [
      '#type' => 'container',
    ];
    $element['given_name'] = [
      '#type' => 'textfield',
      '#title' => t('First name'),
    ];
    $element['family_name'] = [
      '#type' => 'textfield',
      '#title' => t('Last name'),
    ];
    $element['company_name'] = [
      '#type' => 'textfield',
      '#title' => t('Company name'),
    ];
    $element['email'] = [
      '#type' => 'textfield',
      '#title' => t('Email'),
    ];
    $element['phone'] = [
      '#type' => 'textfield',
      '#title' => t('Phone number'),
    ];
    $element['address'] = [
      '#title' => t('Address'),
      '#type' => 'fieldset',
    ];
    $element['address']['address_line1'] = [
      '#type' => 'textfield',
      '#title' => t('Address line 1'),
    ];
    $element['address']['address_line2'] = [
      '#type' => 'textfield',
      '#title' => t('Address line 2'),
    ];
    $element['address']['address_line3'] = [
      '#type' => 'textfield',
      '#title' => t('Address line 3'),
    ];
    $element['address']['city'] = [
      '#type' => 'textfield',
      '#title' => t('City'),
    ];
    $element['address']['postal_code'] = [
      '#type' => 'textfield',
      '#title' => t('Postal code'),
    ];
    $element['address']['region'] = [
      '#type' => 'textfield',
      '#title' => t('Region, county or department'),
    ];
    $element['address']['country_code'] = [
      '#type' => 'select',
      '#options' => country_get_list(),
      '#title' => t('Country'),
    ];
    return $element;
  }

}
