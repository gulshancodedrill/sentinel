<?php

namespace Drupal\sentinel_systemcheck_certificate\TwigExtension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension for certificate translations.
 */
class CertificateTwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new TwigFilter('t_lang', [$this, 'translateWithLanguage']),
    ];
  }

  /**
   * Translate a string with a specific language code.
   *
   * @param string $string
   *   The string to translate.
   * @param string $langcode
   *   The language code (gb, de, fr, it).
   *
   * @return string
   *   The translated string.
   */
  public function translateWithLanguage($string, $langcode = 'gb') {
    // Map language codes: gb -> en, de -> de, fr -> fr, it -> it
    $lang_map = [
      'gb' => 'en',
      'de' => 'de',
      'fr' => 'fr',
      'it' => 'it',
    ];
    $drupal_lang_code = $lang_map[$langcode] ?? 'en';

    // Get the translation service
    $translation = \Drupal::translation();
    
    // Translate with the specific language code
    return $translation->translate($string, [], ['langcode' => $drupal_lang_code]);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'certificate_twig_extension';
  }

}

