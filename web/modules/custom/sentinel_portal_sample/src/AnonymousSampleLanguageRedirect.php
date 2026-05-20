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

      $session_langcode = \Drupal::request()
        ->getSession()
        ->get('sentinel_anonymous_language');

      if (!$session_langcode) {
        $sample_id = \Drupal::routeMatch()->getParameter('sample_id');
        if ($sample_id) {
          $sample = \Drupal::entityTypeManager()
            ->getStorage('sentinel_sample')
            ->load($sample_id);
          if ($sample && $sample->hasField('language') && !$sample->get('language')->isEmpty()) {
            $session_langcode = (string) $sample->get('language')->value;
          }
        }
      }

      if ($session_langcode) {

        $language = \Drupal::languageManager()
          ->getLanguage($session_langcode);
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