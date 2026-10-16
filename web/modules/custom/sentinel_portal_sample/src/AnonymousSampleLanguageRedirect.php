<?php

namespace Drupal\sentinel_portal_sample;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;

/**
 * Preserves interface language (URL prefix) on anonymous sample flow redirects.
 */
final class AnonymousSampleLanguageRedirect {

  /**
   * Options for Url::fromRoute / FormState::setRedirect.
   */
  public static function options(?LanguageInterface $language = NULL): array {

    // If language is not explicitly passed,
    // use the language selected in the anonymous flow.
    if (!$language) {
      static $resolved = FALSE;
      static $resolved_language = NULL;
      if ($resolved) {
        $language = $resolved_language;
      }
      else {
        $session_langcode = \Drupal::request()
          ->getSession()
          ->get('sentinel_anonymous_language');

        if (!$session_langcode) {
          $prn = AnonymousSampleWizardProgress::normalizeAnonymousPrn(
            (string) \Drupal::request()->query->get('prn', '')
          );
          if ($prn !== '') {
            // Scalar SQL — avoid a full entity load just for language.
            $session_langcode = AnonymousSampleWizardProgress::fetchSampleScalarByPrn($prn, 'language');
          }
        }

        if ($session_langcode) {
          $language = \Drupal::languageManager()
            ->getLanguage($session_langcode);
        }
        $resolved_language = $language;
        $resolved = TRUE;
      }
    }

    // Final fallback to current interface language.
    $language = $language
      ?: \Drupal::languageManager()->getCurrentLanguage();

    return $language
      ? ['language' => $language]
      : [];
  }

  /**
   * Absolute URL for a route, keeping the active or given language prefix.
   */
  public static function url(
    string $route_name,
    array $route_parameters = [],
    ?LanguageInterface $language = NULL,
    array $extra_options = []
  ): Url {

    return Url::fromRoute(
      $route_name,
      $route_parameters,
      $extra_options + static::options($language)
    )->setAbsolute(TRUE);
  }

}