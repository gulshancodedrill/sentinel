<?php

namespace Drupal\sentinel_portal_sample;

/**
 * File-based translations for anonymous sample form flow.
 */
final class AnonymousSampleFormTranslations {

  /**
   * Supported language codes for anonymous form dictionaries.
   */
  public const SUPPORTED_LANGUAGES = ['en', 'fr', 'de', 'it'];

  /**
   * Cached translation maps keyed by langcode.
   *
   * @var array<string, array<string, string>>
   */
  protected static $maps = [];

  /**
   * Translate a source string with fallback to English/source.
   */
  public static function translate(string $source, array $args = [], ?string $langcode = NULL): string {
    $lang = static::normalizeLangcode($langcode ?? AnonymousSampleWizardProgress::flowLanguageCode());
    $translated = static::mapFor($lang)[$source] ?? static::mapFor('en')[$source] ?? $source;

    if ($args !== []) {
      return strtr($translated, $args);
    }

    return $translated;
  }

  /**
   * Selector options with localized labels for the current flow language.
   *
   * @return array<string, string>
   *   Language code => localized label.
   */
  public static function languageOptions(?string $langcode = NULL): array {
    $lang = static::normalizeLangcode($langcode ?? AnonymousSampleWizardProgress::flowLanguageCode());
    return [
      'en' => static::translate('English', [], $lang),
      'fr' => static::translate('French', [], $lang),
      'de' => static::translate('German', [], $lang),
      'it' => static::translate('Italian', [], $lang),
    ];
  }

  /**
   * Normalize requested language to a supported one.
   */
  public static function normalizeLangcode(string $langcode): string {
    $lang = strtolower(trim($langcode));
    return in_array($lang, static::SUPPORTED_LANGUAGES, TRUE) ? $lang : 'en';
  }

  /**
   * Load and cache a language map.
   *
   * @return array<string, string>
   *   Translation map keyed by English source string.
   */
  protected static function mapFor(string $langcode): array {
    if (!isset(static::$maps[$langcode])) {
      $path = dirname(__DIR__) . '/translations/anonymous_form.' . $langcode . '.php';
      $map = is_file($path) ? include $path : [];
      static::$maps[$langcode] = is_array($map) ? $map : [];
    }
    return static::$maps[$langcode];
  }

}

