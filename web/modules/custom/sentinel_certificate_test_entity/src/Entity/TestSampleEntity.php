<?php

namespace Drupal\sentinel_certificate_test_entity\Entity;

use Drupal\eck\Entity\EckEntity;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Class TestSampleEntity.
 *
 * Custom entity class for test entities that extends ECK entity.
 * Note: This extends EckEntity if test_entity is an ECK entity.
 * If it's a custom entity, extend the appropriate base class.
 */
class TestSampleEntity extends EckEntity {

  /**
   * Get the sample type from the current sample information.
   *
   * @return string
   *   The type of sample.
   */
  public function getSampleType() {
    $type = 'standard';

    // Get pack reference number.
    $pack_ref = '';
    if ($this->hasField('field_test_pack_reference_number') && !$this->get('field_test_pack_reference_number')->isEmpty()) {
      $pack_ref = $this->get('field_test_pack_reference_number')->value;
    }

    if (empty($pack_ref)) {
      return $type;
    }

    // We use the pack reference number to see what type of sample we have.
    switch (substr($pack_ref, 0, 3)) {
      case '102':
        // Standard Systemcheck Pack.
        $type = 'standard';

        /* If we have the following fields present then we *assume* that we have
         * a Vaillant sample present.
         * - Customer ID (customer_id).
         * - Project ID (project_id).
         * - Boiler ID (boiler_id).
         */
        $customer_id = $this->hasField('customer_id') && !$this->get('customer_id')->isEmpty() ? $this->get('customer_id')->value : NULL;
        $project_id = $this->hasField('project_id') && !$this->get('project_id')->isEmpty() ? $this->get('project_id')->value : NULL;
        $boiler_id = $this->hasField('boiler_id') && !$this->get('boiler_id')->isEmpty() ? $this->get('boiler_id')->value : NULL;

        if (!empty($customer_id) && !empty($project_id) && !empty($boiler_id)) {
          $type = 'vaillant';
        }

        break;

      case '001':
        // Vaillant Systemcheck Pack.
        $type = 'vaillant';
        break;

      case '005':
        // Worcester Bosch Contract Form.
        $type = 'worcesterbosch_contract';
        break;

      case '006':
        // Worcester Bosch Service Form.
        $type = 'worcesterbosch_service';
        break;
    }

    return $type;
  }

  /**
   * Calculate the value of the x100 in the sample, based on the boron.
   */
  public function calculateX100() {
    // Get molybdenum result.
    $molybdenum_result = NULL;
    if ($this->hasField('field_test_molybdenum_result') && !$this->get('field_test_molybdenum_result')->isEmpty()) {
      $molybdenum_result = $this->get('field_test_molybdenum_result')->value;
    }

    if (isset($molybdenum_result) && !is_null($molybdenum_result) && !$this->isLegacy()) {
      if ($molybdenum_result > 0) {
        /*
         * If we have a boron result then we can work out the x100 result.
         *
         * - Boron divided by 40 = % of X100
         * - Basically 1% of X100 should contain 40ppm of boron.
         *
         * The units should be in % of x100 in the sample.
         *
         * To 2 decimal places.
         */
        $x100_result = number_format(floor(($molybdenum_result / 75) * 100) / 100, 2);

        if ($this->hasField('sentinel_x100_result')) {
          $this->set('sentinel_x100_result', $x100_result);
        }
      }
      else {
        // Set a default result of 0.
        if ($this->hasField('sentinel_x100_result')) {
          $this->set('sentinel_x100_result', 0);
        }
      }
    }
  }

  /**
   * Return the country code for the sample, based on the pack reference number.
   *
   * The country code will conform to the "ISO 3166-1 alpha-2" standard.
   *
   * @return string
   *   The country code.
   */
  public function getSampleCountry() {
    // Get pack reference number.
    $pack_ref = '';
    if ($this->hasField('field_test_pack_reference_number') && !$this->get('field_test_pack_reference_number')->isEmpty()) {
      $pack_ref = $this->get('field_test_pack_reference_number')->value;
    }

    if (empty($pack_ref)) {
      return 'gb';
    }

    switch (substr($pack_ref, 0, 3)) {
      case '120':
        // Italian.
        return 'it';

      case '210':
      case '110':
        // German.
        return 'de';

      case '130':
        // French.
        return 'fr';

      case '001':
        // United Kingdom of Great Britain and Northern Ireland.
        // Deliberate fall through.
      case '005':
        // Deliberate fall through.
      case '006':
        // Deliberate fall through.
      case '102':
        // Deliberate fall through.
      default:
        return 'gb';
    }
  }

  /**
   * Check if this is a legacy entity.
   *
   * @return bool
   *   Always returns FALSE for D11.
   */
  public static function isLegacy() {
    return FALSE;
  }

}
