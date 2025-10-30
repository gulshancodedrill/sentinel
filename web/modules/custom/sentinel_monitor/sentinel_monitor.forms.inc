<?php

/**
 * @file
 * Sentinel Monitor form alteration and validation.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter().
 */
function sentinel_monitor_form_sentinel_monitor_sentinel_monitor_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  $current_user = \Drupal::currentUser();
  $user = \Drupal\user\Entity\User::load($current_user->id());
  $roles = $user->getRoles();

  // Set the pilot field to hidden for regular users.
  if (isset($form['field_monitor_pilot_unit'])) {
    $form['field_monitor_pilot_unit']['#access'] = FALSE;
  }
  if (isset($form['field_monitor_text_log'])) {
    $form['field_monitor_text_log']['#access'] = FALSE;
  }
  if (isset($form['field_monitor_last_known_status'])) {
    $form['field_monitor_last_known_status']['#access'] = FALSE;
  }
  if (isset($form['field_date_of_last_known_status'])) {
    $form['field_date_of_last_known_status']['#access'] = FALSE;
  }

  // Add serial number check field.
  $form['field_monitor_serial_number_check'] = [
    '#type' => 'textfield',
    '#title' => t('Re-enter your monitor serial number'),
    '#required' => TRUE,
    '#weight' => 1.5,
  ];

  // Show admin fields for privileged users.
  if (in_array('administrator', $roles) || in_array('technical', $roles) || in_array('sample administrator', $roles) || $current_user->id() == 1) {
    if (isset($form['field_monitor_pilot_unit'])) {
      $form['field_monitor_pilot_unit']['#access'] = TRUE;
    }
    if (isset($form['field_monitor_text_log'])) {
      $form['field_monitor_text_log']['#access'] = TRUE;
    }
    if (isset($form['field_monitor_last_known_status'])) {
      $form['field_monitor_last_known_status']['#access'] = TRUE;
    }
    if (isset($form['field_date_of_last_known_status'])) {
      $form['field_date_of_last_known_status']['#access'] = TRUE;
    }
  }

  // Add validation callback.
  $form['#validate'][] = 'sentinel_monitor_serial_number_validate';
}

/**
 * Validation callback for serial number field.
 *
 * @param array $form
 *   The form.
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 *   The form state.
 */
function sentinel_monitor_serial_number_validate(array &$form, FormStateInterface $form_state) {
  $serial_number = $form_state->getValue(['field_monitor_serial_number', 0, 'value']);
  $serial_check = $form_state->getValue('field_monitor_serial_number_check');

  if (!sentinel_monitor_is_valid_serial_number($serial_number)) {
    $form_state->setError($form['field_monitor_serial_number'], t('Serial number needs to be in the following format 300:NNNNNN (N numeric)'));
    return;
  }

  if ($serial_check != $serial_number) {
    $form_state->setError($form['field_monitor_serial_number_check'], t('You have not entered the same serial number'));
    return;
  }
}

/**
 * Check to make sure the serial number is valid.
 *
 * @param string $number
 *   The serial number to validate.
 *
 * @return bool
 *   TRUE if valid, FALSE otherwise.
 */
function sentinel_monitor_is_valid_serial_number($number) {
  $pattern = '/^([0-9]{3})[:\s-]?([0-9]{6}?)$/';
  $matches = [];
  preg_match_all($pattern, trim($number), $matches);

  if ((isset($matches[1][0]) || isset($matches[2][0])) && strpos($number, ':') == 3) {
    // Glue the two serial parts back together
    return TRUE;
  }
  else {
    return FALSE;
  }
}
