<?php

namespace Drupal\sentinel_migrations\Traits;

/**
 * Trait AddressMappingTrait.
 */
trait AddressMappingTrait {

  /**
   * Returns an array of normal address fields.
   *
   * @return array
   *   An array of address fields.
   */
  public static function getNormalAddressMapping() {
    return [
      'sub_premise' => 'property_number',
      'thoroughfare' => 'Street',
      'dependent_locality' => 'ADDRESS_3',
      'sub_administrative_area' => 'ADDRESS_4',
      'locality' => 'TOWN_CITY',
      'administrative_area' => 'COUNTY',
      'postal_code' => 'POSTCODE',
    ];
  }

  /**
   * Returns an array of company address fields.
   *
   * @return array
   *   An array of company address fields.
   */
  public static function getCompanyAddressMapping() {
    return [
      'premise' => 'COMPANY_ADDRESS1',
      'thoroughfare' => 'COMPANY_ADDRESS2',
      'locality' => 'COMPANY_TOWN',
      'administrative_area' => 'COMPANY_COUNTY',
      'postal_code' => 'COMPANY_POSTCODE',
      'organization_name' => 'COMPANY_NAME',
    ];
  }

  /**
   * Returns an array of the address fields in the correct order.
   *
   * @return array
   *   An array of address fields.
   */
  public static function getAddressFieldsInOrder() {
    return [
      'sub_premise' => 'sub_premise',
      'premise' => 'premise',
      'thoroughfare' => 'thoroughfare',
      'dependent_locality' => 'dependent_locality',
      'sub_administrative_area' => 'sub_administrative_area',
      'locality' => 'locality',
      'administrative_area' => 'administrative_area',
      'postal_code' => 'postal_code',
    ];
  }

}
