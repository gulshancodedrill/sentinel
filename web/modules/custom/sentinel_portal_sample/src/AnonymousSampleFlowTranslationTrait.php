<?php

namespace Drupal\sentinel_portal_sample;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Uses the anonymous flow session language for UI strings (not only URL prefix).
 */
trait AnonymousSampleFlowTranslationTrait {

  /**
   * Translates a string using the flow language when set on the session.
   */
  protected function tFlow(string $string, array $args = []): TranslatableMarkup {
    $translated = AnonymousSampleFormTranslations::translate(
      $string,
      [],
      AnonymousSampleWizardProgress::flowLanguageCode()
    );
    return new TranslatableMarkup(
      $translated,
      $args,
      []
    );
  }

}
