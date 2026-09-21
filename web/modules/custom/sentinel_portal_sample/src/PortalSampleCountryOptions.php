<?php

namespace Drupal\sentinel_portal_sample;

/**
 * ISO country select options shared with the portal sample submission form.
 */
final class PortalSampleCountryOptions {

  /**
   * Raw English labels keyed by ISO 3166-1 alpha-2 code.
   *
   * @return array<string, string>
   */
  public static function isoLabels(): array {
    /** @var array<string, string> $labels */
    $labels = require __DIR__ . '/../resources/portal_country_iso_labels.php';
    return $labels;
  }

  /**
   * Builds translated #options for a country select.
   *
   * @param callable(string): mixed $translate
   *   Callback that translates a raw English label.
   *
   * @return array<string, mixed>
   */
  public static function options(callable $translate): array {
    $out = [];
    foreach (static::isoLabels() as $code => $label) {
      $out[$code] = $translate($label);
    }
    return $out;
  }

}
