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
    static $labels = NULL;
    if ($labels === NULL) {
      /** @var array<string, string> $loaded */
      $loaded = require __DIR__ . '/../resources/portal_country_iso_labels.php';
      $labels = $loaded;
    }
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

  /**
   * Country options translated once per language for the anonymous flow.
   *
   * Labels are plain strings so Form API does not look each country up in
   * the locale tables again while rendering the select.
   *
   * @return array<string, string>
   */
  public static function anonymousOptions(?string $langcode = NULL): array {
    $lang = AnonymousSampleFormTranslations::normalizeLangcode(
      $langcode ?? AnonymousSampleWizardProgress::flowLanguageCode()
    );
    static $cache = [];
    if (!isset($cache[$lang])) {
      $cache[$lang] = static::options(static function (string $label) use ($lang): string {
        return AnonymousSampleFormTranslations::translate($label, [], $lang);
      });
    }
    return $cache[$lang];
  }

}
