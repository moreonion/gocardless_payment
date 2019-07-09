<?php

namespace Drupal\gocardless_payment;

use Drupal\little_helpers\ElementTree;
use Drupal\payment_forms\MethodFormInterface;

/**
 * Configuration form for the redirect flow payment method controller.
 */
class RedirectFlowConfigurationForm implements MethodFormInterface {

  /**
   * Get display options for a customer data field.
   */
  public static function displayOptions($required) {
    $display_options = [
      'ifnotset' => t('Show field if it is not available from the context.'),
      'always' => t('Always show the field - prefill with context values.'),
    ];
    if (!$required) {
      $display_options['hidden'] = t("Don't display, use values from context if available.");
    }
    return $display_options;
  }

  /**
   * Add form elements to the $element Form-API array.
   */
  public function form(array $form, array &$form_state, \PaymentMethod $method) {
    $controller_data = $method->controller_data;
    $form['#tree'] = TRUE;
    $form['testmode'] = [
      '#type' => 'checkbox',
      '#title' => t('test mode'),
      '#default_value' => $controller_data['testmode'],
    ];
    $form['token'] = [
      '#type' => 'textfield',
      '#title' => t('Access token'),
      '#description' => t('This needs to be an access token with read-write permissions.'),
      '#required' => TRUE,
      '#default_value' => $controller_data['token'],
    ];
    $form['creditor'] = [
      '#type' => 'textfield',
      '#title' => t('Creditor'),
      '#description' => t('Specify a creditor if your gocardless account manages multiple creditors.'),
      '#default_value' => $controller_data['creditor'],
    ];

    $form['input_settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Input settings'),
      '#description' => t('Configure how data can be mapped from the payment context.'),
    ];

    $extra = CustomerDataForm::extraElements();
    $stored = $controller_data['input_settings'];
    ElementTree::applyRecursively($extra, function ($element, $key) use (&$form, $stored) {
      if (in_array($element['#type'], ['container', 'fieldset'])) {
        return;
      }
      $defaults = $stored[$key];
      $fieldset = [
        '#type' => 'fieldset',
        '#title' => $element['#title'],
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
      ];
      $required = !empty($element['#required']);
      $defaults['required'] = $defaults['required'] || $required;
      $id = drupal_html_id('controller_data_' . $key);
      $fieldset['display'] = [
        '#type' => 'radios',
        '#title' => t('Display'),
        '#options' => RedirectFlowConfigurationForm::displayOptions($required),
        '#default_value' => $defaults['display'],
        '#id' => $id,
      ];
      $fieldset['display_other'] = [
        '#type' => 'radios',
        '#title' => t('Display when other fields in the same fieldset are visible.'),
        '#options' => RedirectFlowConfigurationForm::displayOptions($required),
        '#default_value' => $defaults['display_other'],
        '#states' => ['invisible' => ["#$id" => ['value' => 'always']]],
      ];
      $fieldset['required'] = array(
        '#type' => 'checkbox',
        '#title' => t('Required'),
        '#states' => ['disabled' => ["#$id" => ['value' => 'hidden']]],
        '#default_value' => $defaults['required'],
        '#access' => !$required,
      );
      $fieldset['keys'] = array(
        '#type' => 'textfield',
        '#title' => t('Context keys'),
        '#description' => t('When building the form these (comma separated) keys are used to ask the Payment Context for a (default) value for this field.'),
        '#default_value' => implode(', ', $defaults['keys']),
      );
      $form['input_settings'][$key] = $fieldset;
    });
    return $form;
  }

  /**
   * Validate the submitted values and put them in the method data.
   */
  public function validate(array $element, array &$form_state, \PaymentMethod $method) {
    $values = drupal_array_get_nested_value($form_state['values'], $element['#parents']);
    foreach ($values['input_settings'] as &$settings) {
      $settings['keys'] = array_map('trim', explode(',', $settings['keys']));
    }
    $method->controller_data = $values;
  }

}
