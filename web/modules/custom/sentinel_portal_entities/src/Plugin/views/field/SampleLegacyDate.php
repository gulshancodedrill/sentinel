<?php

namespace Drupal\sentinel_portal_entities\Plugin\views\field;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Renders Sentinel Sample dates that may use legacy formats.
 *
 * @ViewsField("sentinel_sample_legacy_date")
 */
class SampleLegacyDate extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['format_type'] = ['default' => 'custom'];
    $options['custom_format'] = ['default' => 'd/m/Y'];
    $options['timezone'] = ['default' => ''];
    $options['empty_text'] = ['default' => '-'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $date_types = \Drupal::service('date.formatter')->getFormatOptions();
    $form['format_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Date format'),
      '#options' => $date_types,
      '#default_value' => $this->options['format_type'] ?? 'custom',
      '#description' => $this->t('Choose a configured date format. Set to "Custom" to use a specific PHP/Intl format below.'),
    ];

    $form['custom_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom format'),
      '#default_value' => $this->options['custom_format'] ?? 'd/m/Y',
      '#description' => $this->t('Optional PHP date format string (e.g. d/m/Y). Leave empty to use the selected date format above.'),
    ];

    $form['timezone'] = [
      '#type' => 'select',
      '#title' => $this->t('Timezone override'),
      '#options' => ['' => $this->t('- Site default -')] + system_time_zones(),
      '#default_value' => $this->options['timezone'] ?? '',
    ];

    $form['empty_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Empty text'),
      '#default_value' => $this->options['empty_text'] ?? '-',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $raw_value = $this->getValue($values);
    $timestamp = $this->normalizeLegacyDate($raw_value);

    if ($timestamp === NULL) {
      $empty = $this->options['empty_text'] ?? '-';
      return ['#markup' => $empty === '' ? '' : $empty];
    }

    $formatter = \Drupal::service('date.formatter');
    $timezone = $this->options['timezone'] ?? '';
    $custom_format = trim($this->options['custom_format'] ?? '');
    $format_type = $this->options['format_type'] ?? 'short';

    if ($custom_format !== '') {
      $formatted = $formatter->format($timestamp, 'custom', $custom_format, $timezone ?: NULL);
    }
    else {
      $formatted = $formatter->format($timestamp, $format_type, '', $timezone ?: NULL);
    }

    return ['#markup' => $formatted];
  }

  /**
   * Converts stored legacy strings to a timestamp.
   */
  protected function normalizeLegacyDate($value): ?int {
    if ($value === NULL || $value === '') {
      return NULL;
    }

    $value = trim((string) $value);
    if ($value === '') {
      return NULL;
    }

    // Replace legacy space separator with ISO "T".
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
      $value = str_replace(' ', 'T', $value);
    }

    // Append midnight if only a date was stored.
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
      $value .= 'T00:00:00';
    }

    try {
      $datetime = new DrupalDateTime($value, new \DateTimeZone('UTC'));
      if ($datetime->getTimestamp()) {
        return $datetime->getTimestamp();
      }
    }
    catch (\Exception $e) {
      // Fall through.
    }

    $fallback = strtotime($value);
    return $fallback ?: NULL;
  }

}

