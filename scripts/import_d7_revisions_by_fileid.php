<?php

/**
 * Import Drupal 7 Sentinel sample revisions to Drupal 11.
 *
 * This script:
 * 1. Uses a pre-fetched in-script array of D7 revision rows (no D7 connection).
 * 2. Deletes existing revisions per PID in D11 (no full truncation).
 * 3. Inserts revisions into D11 sentinel_sample_revision.
 * 4. Updates vid in D11 sentinel_sample table.
 * 5. For fields sentinel_sample_hold_state_target_id, sentinel_company_address_target_id,
 *    and sentinel_sample_address_target_id, takes values from D11 sentinel_sample table.
 *
 * Usage:
 *   php scripts/import_d7_revisions_by_fileid.php
 */

// D11 LOCAL TARGET
$d11_host = 'localhost';
$d11_port = 3306;
$d11_username = 'sentinelportal_drupalsentinel';
$d11_password = '!b@xa=}*IL7[hU)O';
$d11_database = 'sentinelportal_drupalsentinel';

/**
 * Pre-fetched D7 revision rows.
 *
 * Fetch once from prod30 and paste here. Structure:
 * [
 *   123 => [
 *     ['pid' => 123, 'vid' => 999, 'field_a' => 'value', ...],
 *     ['pid' => 123, 'vid' => 1000, 'field_a' => 'value', ...],
 *   ],
 *   124 => [
 *     ['pid' => 124, 'vid' => 1001, 'field_a' => 'value', ...],
 *   ],
 * ]
 */
$revisions_by_pid = array (
  121 => 
  array (
    0 => 
    array (
      'pid' => '121',
      'vid' => '121',
      'pack_reference_number' => '102:152110',
      'project_id' => NULL,
      'installer_name' => 'G. BOOTH',
      'installer_email' => NULL,
      'company_name' => 'BROADOAK PROPERTIES',
      'company_email' => 'NATASHA@BROADOAKPROPERTIES.COM',
      'company_address1' => 'BROADOAK FARM',
      'company_address2' => 'KINGSLEY MOOR',
      'company_town' => 'STOKE ON TRENT',
      'company_county' => NULL,
      'company_postcode' => 'ST10 2EL',
      'company_tel' => '01782550371',
      'system_location' => '28 BETCHTON ROAD, SANDBACH',
      'system_age' => NULL,
      'system_6_months' => 'MORE6',
      'uprn' => NULL,
      'property_number' => NULL,
      'street' => NULL,
      'town_city' => NULL,
      'county' => NULL,
      'postcode' => 'CW114XL',
      'landlord' => NULL,
      'boiler_manufacturer' => 'Vaillant',
      'boiler_id' => NULL,
      'boiler_type' => 'Eco-tech PRO 30',
      'engineers_code' => NULL,
      'service_call_id' => NULL,
      'date_installed' => NULL,
      'date_sent' => NULL,
      'date_booked' => '2016-08-24 12:46:00',
      'date_processed' => NULL,
      'date_reported' => '2016-08-26 15:33:00',
      'fileid' => '1056',
      'filename' => '102-152110.pdf',
      'client_id' => NULL,
      'client_name' => NULL,
      'customer_id' => NULL,
      'lab_ref' => NULL,
      'pack_type' => 'SEN',
      'card_complete' => '1',
      'on_hold' => NULL,
      'pass_fail' => '1',
      'appearance_result' => '1',
      'appearance_pass_fail' => '1',
      'mains_cond_result' => '485.0',
      'sys_cond_result' => '6130',
      'cond_pass_fail' => '1',
      'mains_cl_result' => '28.8',
      'sys_cl_result' => '72.84',
      'cl_pass_fail' => '1',
      'iron_result' => '0.0',
      'iron_pass_fail' => '1',
      'copper_result' => '0.0',
      'copper_pass_fail' => '1',
      'aluminium_result' => '0.1',
      'aluminium_pass_fail' => '1',
      'mains_calcium_result' => '84.3324',
      'sys_calcium_result' => '55.6352',
      'calcium_pass_fail' => '1',
      'ph_result' => '6.7',
      'ph_pass_fail' => '1',
      'sentinel_x100_result' => '5.61',
      'sentinel_x100_pass_fail' => '1',
      'molybdenum_result' => '500.115',
      'molybdenum_pass_fail' => NULL,
      'boron_result' => '224.411',
      'boron_pass_fail' => NULL,
      'manganese_result' => '-0.075197',
      'manganese_pass_fail' => NULL,
      'nitrate_result' => '2',
      'mob_ratio' => NULL,
      'created' => '2016-08-24 11:46:00',
      'updated' => '2016-11-23 11:03:00',
      'ucr' => '162',
      'installer_company' => NULL,
      'old_pack_reference_number' => NULL,
      'duplicate_of' => NULL,
      'legacy' => NULL,
      'api_created_by' => NULL,
    ),
    1 => 
    array (
      'pid' => '121',
      'vid' => '436351',
      'pack_reference_number' => '102:152110',
      'project_id' => NULL,
      'installer_name' => 'G. BOOTH',
      'installer_email' => NULL,
      'company_name' => 'BROADOAK PROPERTIES',
      'company_email' => 'NATASHA@BROADOAKPROPERTIES.COM',
      'company_address1' => 'BROADOAK FARM',
      'company_address2' => 'KINGSLEY MOOR',
      'company_town' => 'STOKE ON TRENT',
      'company_county' => NULL,
      'company_postcode' => 'ST10 2EL',
      'company_tel' => '01782550371',
      'system_location' => '28, BETCHTON ROAD, Sandbach, CW11 4XL',
      'system_age' => NULL,
      'system_6_months' => 'MORE6',
      'uprn' => NULL,
      'property_number' => '28',
      'street' => 'BETCHTON ROAD',
      'town_city' => NULL,
      'county' => 'Sandbach',
      'postcode' => 'CW11 4XL',
      'landlord' => NULL,
      'boiler_manufacturer' => 'Vaillant',
      'boiler_id' => NULL,
      'boiler_type' => 'Eco-tech PRO 30',
      'engineers_code' => NULL,
      'service_call_id' => NULL,
      'date_installed' => NULL,
      'date_sent' => NULL,
      'date_booked' => '2016-08-24 12:46:00',
      'date_processed' => NULL,
      'date_reported' => '2016-08-26 15:33:00',
      'fileid' => '878231',
      'filename' => '102-152110-28--BETCHTON-ROAD--Sandbach--CW11-4XL.pdf',
      'client_id' => NULL,
      'client_name' => NULL,
      'customer_id' => NULL,
      'lab_ref' => NULL,
      'pack_type' => 'SEN',
      'card_complete' => '1',
      'on_hold' => NULL,
      'pass_fail' => '1',
      'appearance_result' => '1',
      'appearance_pass_fail' => '1',
      'mains_cond_result' => '485.0',
      'sys_cond_result' => '6130',
      'cond_pass_fail' => '1',
      'mains_cl_result' => '28.8',
      'sys_cl_result' => '72.84',
      'cl_pass_fail' => '1',
      'iron_result' => '0.0',
      'iron_pass_fail' => '1',
      'copper_result' => '0.0',
      'copper_pass_fail' => '1',
      'aluminium_result' => '0.1',
      'aluminium_pass_fail' => '1',
      'mains_calcium_result' => '84.3324',
      'sys_calcium_result' => '55.6352',
      'calcium_pass_fail' => '1',
      'ph_result' => '6.7',
      'ph_pass_fail' => '1',
      'sentinel_x100_result' => '6.66',
      'sentinel_x100_pass_fail' => '1',
      'molybdenum_result' => '500.115',
      'molybdenum_pass_fail' => NULL,
      'boron_result' => '224.411',
      'boron_pass_fail' => NULL,
      'manganese_result' => '-0.075197',
      'manganese_pass_fail' => NULL,
      'nitrate_result' => '2',
      'mob_ratio' => NULL,
      'created' => '2016-08-24 11:46:00',
      'updated' => '2026-01-28 10:26:00',
      'ucr' => '162',
      'installer_company' => NULL,
      'old_pack_reference_number' => NULL,
      'duplicate_of' => NULL,
      'legacy' => NULL,
      'api_created_by' => NULL,
    ),
  ),
  757351 => 
  array (
    0 => 
    array (
      'pid' => '757351',
      'vid' => '2725256',
      'pack_reference_number' => '130:19527D2',
      'project_id' => NULL,
      'installer_name' => '',
      'installer_email' => NULL,
      'company_name' => 'SARL',
      'company_email' => 'customer.services@sentinelprotects.com;contact@gepem37.fr',
      'company_address1' => '45 Rue du Sergent Leclerc',
      'company_address2' => '',
      'company_town' => 'Tours',
      'company_county' => 'FRANCE',
      'company_postcode' => '37000',
      'company_tel' => '0676239350',
      'system_location' => '38 Grande rue
Saint Epain',
      'system_age' => NULL,
      'system_6_months' => 'LESS6',
      'uprn' => NULL,
      'property_number' => NULL,
      'street' => NULL,
      'town_city' => NULL,
      'county' => NULL,
      'postcode' => '37800',
      'landlord' => NULL,
      'boiler_manufacturer' => 'NA',
      'boiler_id' => NULL,
      'boiler_type' => 'NA',
      'engineers_code' => NULL,
      'service_call_id' => NULL,
      'date_installed' => NULL,
      'date_sent' => NULL,
      'date_booked' => '2026-01-06 05:43:00',
      'date_processed' => NULL,
      'date_reported' => '2026-01-09 14:39:00',
      'fileid' => NULL,
      'filename' => NULL,
      'client_id' => NULL,
      'client_name' => NULL,
      'customer_id' => NULL,
      'lab_ref' => NULL,
      'pack_type' => 'SEN',
      'card_complete' => '1',
      'on_hold' => '0',
      'pass_fail' => '0',
      'appearance_result' => '1',
      'appearance_pass_fail' => NULL,
      'mains_cond_result' => '1490',
      'sys_cond_result' => '2380',
      'cond_pass_fail' => NULL,
      'mains_cl_result' => '*',
      'sys_cl_result' => '*',
      'cl_pass_fail' => NULL,
      'iron_result' => '4.7501775',
      'iron_pass_fail' => NULL,
      'copper_result' => '0.0018755',
      'copper_pass_fail' => NULL,
      'aluminium_result' => '0.0097738',
      'aluminium_pass_fail' => NULL,
      'mains_calcium_result' => '94.3344749',
      'sys_calcium_result' => '74.9569067',
      'calcium_pass_fail' => NULL,
      'ph_result' => '7.2',
      'ph_pass_fail' => NULL,
      'sentinel_x100_result' => '3.00',
      'sentinel_x100_pass_fail' => NULL,
      'molybdenum_result' => '225.1215634',
      'molybdenum_pass_fail' => NULL,
      'boron_result' => '1.7437519',
      'boron_pass_fail' => NULL,
      'manganese_result' => '0.3709338',
      'manganese_pass_fail' => NULL,
      'nitrate_result' => '1',
      'mob_ratio' => NULL,
      'created' => '2026-01-09 14:39:00',
      'updated' => '2026-01-09 14:39:00',
      'ucr' => '3518',
      'installer_company' => NULL,
      'old_pack_reference_number' => NULL,
      'duplicate_of' => '729576',
      'legacy' => NULL,
      'api_created_by' => NULL,
    ),
    1 => 
    array (
      'pid' => '757351',
      'vid' => '2725261',
      'pack_reference_number' => '130:19527D2',
      'project_id' => NULL,
      'installer_name' => '',
      'installer_email' => NULL,
      'company_name' => 'SARL',
      'company_email' => 'customer.services@sentinelprotects.com;contact@gepem37.fr',
      'company_address1' => '45 Rue du Sergent Leclerc',
      'company_address2' => '',
      'company_town' => 'Tours',
      'company_county' => 'FRANCE',
      'company_postcode' => '37000',
      'company_tel' => '0676239350',
      'system_location' => '38 Grande rue
Saint Epain',
      'system_age' => NULL,
      'system_6_months' => 'LESS6',
      'uprn' => NULL,
      'property_number' => NULL,
      'street' => NULL,
      'town_city' => NULL,
      'county' => NULL,
      'postcode' => '37800',
      'landlord' => NULL,
      'boiler_manufacturer' => 'NA',
      'boiler_id' => NULL,
      'boiler_type' => 'NA',
      'engineers_code' => NULL,
      'service_call_id' => NULL,
      'date_installed' => NULL,
      'date_sent' => NULL,
      'date_booked' => '2026-01-06 05:43:00',
      'date_processed' => NULL,
      'date_reported' => '2026-01-09 14:39:00',
      'fileid' => '878266',
      'filename' => '130-19527D2-38-Grande-rue--Saint-Epain.pdf',
      'client_id' => NULL,
      'client_name' => NULL,
      'customer_id' => NULL,
      'lab_ref' => NULL,
      'pack_type' => 'SEN',
      'card_complete' => '1',
      'on_hold' => '0',
      'pass_fail' => '1',
      'appearance_result' => '1',
      'appearance_pass_fail' => '1',
      'mains_cond_result' => '1490',
      'sys_cond_result' => '2380',
      'cond_pass_fail' => '1',
      'mains_cl_result' => '*',
      'sys_cl_result' => '*',
      'cl_pass_fail' => '1',
      'iron_result' => '4.7501775',
      'iron_pass_fail' => '1',
      'copper_result' => '0.0018755',
      'copper_pass_fail' => '1',
      'aluminium_result' => '0.0097738',
      'aluminium_pass_fail' => '1',
      'mains_calcium_result' => '94.3344749',
      'sys_calcium_result' => '74.9569067',
      'calcium_pass_fail' => '1',
      'ph_result' => '7.2',
      'ph_pass_fail' => '1',
      'sentinel_x100_result' => '3.00',
      'sentinel_x100_pass_fail' => '1',
      'molybdenum_result' => '225.1215634',
      'molybdenum_pass_fail' => NULL,
      'boron_result' => '1.7437519',
      'boron_pass_fail' => NULL,
      'manganese_result' => '0.3709338',
      'manganese_pass_fail' => NULL,
      'nitrate_result' => '1',
      'mob_ratio' => NULL,
      'created' => '2026-01-09 14:39:00',
      'updated' => '2026-01-29 16:00:00',
      'ucr' => '3518',
      'installer_company' => NULL,
      'old_pack_reference_number' => NULL,
      'duplicate_of' => '729576',
      'legacy' => NULL,
      'api_created_by' => NULL,
    ),
  ),
  757356 => 
  array (
    0 => 
    array (
      'pid' => '757356',
      'vid' => '2725286',
      'pack_reference_number' => '130:19528D2',
      'project_id' => NULL,
      'installer_name' => '',
      'installer_email' => NULL,
      'company_name' => 'SARL',
      'company_email' => 'customer.services@sentinelprotects.com;contact@gepem37.fr',
      'company_address1' => '45 Rue du Sergent Leclerc',
      'company_address2' => '',
      'company_town' => 'Tours',
      'company_county' => 'FRANCE',
      'company_postcode' => '37000',
      'company_tel' => '0676239350',
      'system_location' => '56 Grande Rue
Saint Epain',
      'system_age' => NULL,
      'system_6_months' => 'LESS6',
      'uprn' => NULL,
      'property_number' => NULL,
      'street' => NULL,
      'town_city' => NULL,
      'county' => NULL,
      'postcode' => '37800',
      'landlord' => NULL,
      'boiler_manufacturer' => 'NA',
      'boiler_id' => NULL,
      'boiler_type' => 'NA',
      'engineers_code' => NULL,
      'service_call_id' => NULL,
      'date_installed' => NULL,
      'date_sent' => NULL,
      'date_booked' => '2026-01-06 05:55:00',
      'date_processed' => NULL,
      'date_reported' => '2026-01-09 14:40:00',
      'fileid' => NULL,
      'filename' => NULL,
      'client_id' => NULL,
      'client_name' => NULL,
      'customer_id' => NULL,
      'lab_ref' => NULL,
      'pack_type' => 'SEN',
      'card_complete' => '1',
      'on_hold' => '0',
      'pass_fail' => '0',
      'appearance_result' => '1',
      'appearance_pass_fail' => NULL,
      'mains_cond_result' => '1440',
      'sys_cond_result' => '2450',
      'cond_pass_fail' => NULL,
      'mains_cl_result' => '*',
      'sys_cl_result' => '*',
      'cl_pass_fail' => NULL,
      'iron_result' => '22.357862',
      'iron_pass_fail' => NULL,
      'copper_result' => '0.0053332',
      'copper_pass_fail' => NULL,
      'aluminium_result' => '-0.0070569',
      'aluminium_pass_fail' => NULL,
      'mains_calcium_result' => '103.4991085',
      'sys_calcium_result' => '75.210208',
      'calcium_pass_fail' => NULL,
      'ph_result' => '7.2',
      'ph_pass_fail' => NULL,
      'sentinel_x100_result' => '3.22',
      'sentinel_x100_pass_fail' => NULL,
      'molybdenum_result' => '241.79771',
      'molybdenum_pass_fail' => NULL,
      'boron_result' => '2.6409552',
      'boron_pass_fail' => NULL,
      'manganese_result' => '0.3796373',
      'manganese_pass_fail' => NULL,
      'nitrate_result' => '1',
      'mob_ratio' => NULL,
      'created' => '2026-01-09 14:40:00',
      'updated' => '2026-01-09 14:40:00',
      'ucr' => '3518',
      'installer_company' => NULL,
      'old_pack_reference_number' => NULL,
      'duplicate_of' => '738826',
      'legacy' => NULL,
      'api_created_by' => NULL,
    ),
    1 => 
    array (
      'pid' => '757356',
      'vid' => '2725291',
      'pack_reference_number' => '130:19528D2',
      'project_id' => NULL,
      'installer_name' => '',
      'installer_email' => NULL,
      'company_name' => 'SARL',
      'company_email' => 'customer.services@sentinelprotects.com;contact@gepem37.fr',
      'company_address1' => '45 Rue du Sergent Leclerc',
      'company_address2' => '',
      'company_town' => 'Tours',
      'company_county' => 'FRANCE',
      'company_postcode' => '37000',
      'company_tel' => '0676239350',
      'system_location' => '56 Grande Rue
Saint Epain',
      'system_age' => NULL,
      'system_6_months' => 'LESS6',
      'uprn' => NULL,
      'property_number' => NULL,
      'street' => NULL,
      'town_city' => NULL,
      'county' => NULL,
      'postcode' => '37800',
      'landlord' => NULL,
      'boiler_manufacturer' => 'NA',
      'boiler_id' => NULL,
      'boiler_type' => 'NA',
      'engineers_code' => NULL,
      'service_call_id' => NULL,
      'date_installed' => NULL,
      'date_sent' => NULL,
      'date_booked' => '2026-01-06 05:55:00',
      'date_processed' => NULL,
      'date_reported' => '2026-01-09 14:40:00',
      'fileid' => '878261',
      'filename' => '130-19528D2-56-Grande-Rue--Saint-Epain.pdf',
      'client_id' => NULL,
      'client_name' => NULL,
      'customer_id' => NULL,
      'lab_ref' => NULL,
      'pack_type' => 'SEN',
      'card_complete' => '1',
      'on_hold' => '0',
      'pass_fail' => '1',
      'appearance_result' => '1',
      'appearance_pass_fail' => '1',
      'mains_cond_result' => '1440',
      'sys_cond_result' => '2450',
      'cond_pass_fail' => '1',
      'mains_cl_result' => '*',
      'sys_cl_result' => '*',
      'cl_pass_fail' => '1',
      'iron_result' => '22.357862',
      'iron_pass_fail' => '1',
      'copper_result' => '0.0053332',
      'copper_pass_fail' => '1',
      'aluminium_result' => '-0.0070569',
      'aluminium_pass_fail' => '1',
      'mains_calcium_result' => '103.4991085',
      'sys_calcium_result' => '75.210208',
      'calcium_pass_fail' => '1',
      'ph_result' => '7.2',
      'ph_pass_fail' => '1',
      'sentinel_x100_result' => '3.22',
      'sentinel_x100_pass_fail' => '1',
      'molybdenum_result' => '241.79771',
      'molybdenum_pass_fail' => NULL,
      'boron_result' => '2.6409552',
      'boron_pass_fail' => NULL,
      'manganese_result' => '0.3796373',
      'manganese_pass_fail' => NULL,
      'nitrate_result' => '1',
      'mob_ratio' => NULL,
      'created' => '2026-01-09 14:40:00',
      'updated' => '2026-01-29 16:00:00',
      'ucr' => '3518',
      'installer_company' => NULL,
      'old_pack_reference_number' => NULL,
      'duplicate_of' => '738826',
      'legacy' => NULL,
      'api_created_by' => NULL,
    ),
  ),
  757371 => 
  array (
    0 => 
    array (
      'pid' => '757371',
      'vid' => '2725396',
      'pack_reference_number' => '130:19191D2',
      'project_id' => NULL,
      'installer_name' => '',
      'installer_email' => NULL,
      'company_name' => 'SARL',
      'company_email' => 'customer.services@sentinelprotects.com;magalie@domo24.fr',
      'company_address1' => '8 Av de la Gare',
      'company_address2' => '',
      'company_town' => 'Montignac',
      'company_county' => 'FRANCE',
      'company_postcode' => '24290',
      'company_tel' => '0553504636',
      'system_location' => '75 Chemin de Gaulejac
Montignac',
      'system_age' => NULL,
      'system_6_months' => 'MORE6',
      'uprn' => NULL,
      'property_number' => NULL,
      'street' => NULL,
      'town_city' => NULL,
      'county' => NULL,
      'postcode' => '24290',
      'landlord' => NULL,
      'boiler_manufacturer' => 'NA',
      'boiler_id' => NULL,
      'boiler_type' => 'NA',
      'engineers_code' => NULL,
      'service_call_id' => NULL,
      'date_installed' => NULL,
      'date_sent' => NULL,
      'date_booked' => '2026-01-06 06:46:00',
      'date_processed' => NULL,
      'date_reported' => '2026-01-09 14:41:00',
      'fileid' => NULL,
      'filename' => NULL,
      'client_id' => NULL,
      'client_name' => NULL,
      'customer_id' => NULL,
      'lab_ref' => NULL,
      'pack_type' => 'SEN',
      'card_complete' => '1',
      'on_hold' => '0',
      'pass_fail' => '0',
      'appearance_result' => '1',
      'appearance_pass_fail' => NULL,
      'mains_cond_result' => '544',
      'sys_cond_result' => '342',
      'cond_pass_fail' => NULL,
      'mains_cl_result' => '*',
      'sys_cl_result' => '*',
      'cl_pass_fail' => NULL,
      'iron_result' => '0.0022587',
      'iron_pass_fail' => NULL,
      'copper_result' => '10.4110909',
      'copper_pass_fail' => NULL,
      'aluminium_result' => '0.0156406',
      'aluminium_pass_fail' => NULL,
      'mains_calcium_result' => '130.8081897',
      'sys_calcium_result' => '64.060378',
      'calcium_pass_fail' => NULL,
      'ph_result' => '8.0',
      'ph_pass_fail' => NULL,
      'sentinel_x100_result' => '0.01',
      'sentinel_x100_pass_fail' => NULL,
      'molybdenum_result' => '0.8759119',
      'molybdenum_pass_fail' => NULL,
      'boron_result' => '1.6493291',
      'boron_pass_fail' => NULL,
      'manganese_result' => '0.1214613',
      'manganese_pass_fail' => NULL,
      'nitrate_result' => '1',
      'mob_ratio' => NULL,
      'created' => '2026-01-09 14:41:00',
      'updated' => '2026-01-09 14:41:00',
      'ucr' => '6494',
      'installer_company' => NULL,
      'old_pack_reference_number' => NULL,
      'duplicate_of' => '744156',
      'legacy' => NULL,
      'api_created_by' => NULL,
    ),
    1 => 
    array (
      'pid' => '757371',
      'vid' => '2725401',
      'pack_reference_number' => '130:19191D2',
      'project_id' => NULL,
      'installer_name' => '',
      'installer_email' => NULL,
      'company_name' => 'SARL',
      'company_email' => 'customer.services@sentinelprotects.com;magalie@domo24.fr',
      'company_address1' => '8 Av de la Gare',
      'company_address2' => '',
      'company_town' => 'Montignac',
      'company_county' => 'FRANCE',
      'company_postcode' => '24290',
      'company_tel' => '0553504636',
      'system_location' => '75 Chemin de Gaulejac
Montignac',
      'system_age' => NULL,
      'system_6_months' => 'MORE6',
      'uprn' => NULL,
      'property_number' => NULL,
      'street' => NULL,
      'town_city' => NULL,
      'county' => NULL,
      'postcode' => '24290',
      'landlord' => NULL,
      'boiler_manufacturer' => 'NA',
      'boiler_id' => NULL,
      'boiler_type' => 'NA',
      'engineers_code' => NULL,
      'service_call_id' => NULL,
      'date_installed' => NULL,
      'date_sent' => NULL,
      'date_booked' => '2026-01-06 06:46:00',
      'date_processed' => NULL,
      'date_reported' => '2026-01-09 14:41:00',
      'fileid' => '878251',
      'filename' => '130-19191D2-75-Chemin-de-Gaulejac--Montignac.pdf',
      'client_id' => NULL,
      'client_name' => NULL,
      'customer_id' => NULL,
      'lab_ref' => NULL,
      'pack_type' => 'SEN',
      'card_complete' => '1',
      'on_hold' => '0',
      'pass_fail' => '0',
      'appearance_result' => '1',
      'appearance_pass_fail' => '1',
      'mains_cond_result' => '544',
      'sys_cond_result' => '342',
      'cond_pass_fail' => '1',
      'mains_cl_result' => '*',
      'sys_cl_result' => '*',
      'cl_pass_fail' => '1',
      'iron_result' => '0.0022587',
      'iron_pass_fail' => '1',
      'copper_result' => '10.4110909',
      'copper_pass_fail' => '0',
      'aluminium_result' => '0.0156406',
      'aluminium_pass_fail' => '1',
      'mains_calcium_result' => '130.8081897',
      'sys_calcium_result' => '64.060378',
      'calcium_pass_fail' => '0',
      'ph_result' => '8.0',
      'ph_pass_fail' => '1',
      'sentinel_x100_result' => '0.01',
      'sentinel_x100_pass_fail' => '0',
      'molybdenum_result' => '0.8759119',
      'molybdenum_pass_fail' => NULL,
      'boron_result' => '1.6493291',
      'boron_pass_fail' => NULL,
      'manganese_result' => '0.1214613',
      'manganese_pass_fail' => NULL,
      'nitrate_result' => '1',
      'mob_ratio' => NULL,
      'created' => '2026-01-09 14:41:00',
      'updated' => '2026-01-29 15:25:00',
      'ucr' => '6494',
      'installer_company' => NULL,
      'old_pack_reference_number' => NULL,
      'duplicate_of' => '744156',
      'legacy' => NULL,
      'api_created_by' => NULL,
    ),
  ),
  757386 => 
  array (
    0 => 
    array (
      'pid' => '757386',
      'vid' => '2725746',
      'pack_reference_number' => '130:19051D2',
      'project_id' => NULL,
      'installer_name' => 'SAS',
      'installer_email' => NULL,
      'company_name' => 'SAS GEPEM (37000)',
      'company_email' => 'customer.services@sentinelprotects.com;CONTACT@GEPEM37.FR',
      'company_address1' => '45',
      'company_address2' => 'RUE DU SERGENT LECLERC',
      'company_town' => 'TOURS',
      'company_county' => 'FRANCE',
      'company_postcode' => '37000',
      'company_tel' => '0247370277',
      'system_location' => '34 GRANDE RUE LOG NO 1',
      'system_age' => NULL,
      'system_6_months' => 'LESS6',
      'uprn' => NULL,
      'property_number' => NULL,
      'street' => NULL,
      'town_city' => NULL,
      'county' => NULL,
      'postcode' => '37800 SAINT EPAIN',
      'landlord' => NULL,
      'boiler_manufacturer' => 'NA',
      'boiler_id' => NULL,
      'boiler_type' => 'NA',
      'engineers_code' => NULL,
      'service_call_id' => NULL,
      'date_installed' => NULL,
      'date_sent' => NULL,
      'date_booked' => '2026-01-06 09:04:00',
      'date_processed' => NULL,
      'date_reported' => '2026-01-09 14:44:00',
      'fileid' => NULL,
      'filename' => NULL,
      'client_id' => NULL,
      'client_name' => NULL,
      'customer_id' => NULL,
      'lab_ref' => NULL,
      'pack_type' => 'SEN',
      'card_complete' => '1',
      'on_hold' => '0',
      'pass_fail' => '0',
      'appearance_result' => '1',
      'appearance_pass_fail' => NULL,
      'mains_cond_result' => '1200',
      'sys_cond_result' => '2160',
      'cond_pass_fail' => NULL,
      'mains_cl_result' => '*',
      'sys_cl_result' => '*',
      'cl_pass_fail' => NULL,
      'iron_result' => '12.0753332',
      'iron_pass_fail' => NULL,
      'copper_result' => '-0.002348',
      'copper_pass_fail' => NULL,
      'aluminium_result' => '0.0282277',
      'aluminium_pass_fail' => NULL,
      'mains_calcium_result' => '109.0299838',
      'sys_calcium_result' => '71.8266658',
      'calcium_pass_fail' => NULL,
      'ph_result' => '7.0',
      'ph_pass_fail' => NULL,
      'sentinel_x100_result' => '2.31',
      'sentinel_x100_pass_fail' => NULL,
      'molybdenum_result' => '173.5820818',
      'molybdenum_pass_fail' => NULL,
      'boron_result' => '2.1964985',
      'boron_pass_fail' => NULL,
      'manganese_result' => '0.4267016',
      'manganese_pass_fail' => NULL,
      'nitrate_result' => '1',
      'mob_ratio' => NULL,
      'created' => '2026-01-09 14:44:00',
      'updated' => '2026-01-09 14:44:00',
      'ucr' => '3518',
      'installer_company' => NULL,
      'old_pack_reference_number' => NULL,
      'duplicate_of' => '716506',
      'legacy' => NULL,
      'api_created_by' => NULL,
    ),
    1 => 
    array (
      'pid' => '757386',
      'vid' => '2725751',
      'pack_reference_number' => '130:19051D2',
      'project_id' => NULL,
      'installer_name' => 'SAS',
      'installer_email' => NULL,
      'company_name' => 'SAS GEPEM (37000)',
      'company_email' => 'customer.services@sentinelprotects.com;CONTACT@GEPEM37.FR',
      'company_address1' => '45',
      'company_address2' => 'RUE DU SERGENT LECLERC',
      'company_town' => 'TOURS',
      'company_county' => 'FRANCE',
      'company_postcode' => '37000',
      'company_tel' => '0247370277',
      'system_location' => '34 GRANDE RUE LOG NO 1',
      'system_age' => NULL,
      'system_6_months' => 'LESS6',
      'uprn' => NULL,
      'property_number' => NULL,
      'street' => NULL,
      'town_city' => NULL,
      'county' => NULL,
      'postcode' => '37800 SAINT EPAIN',
      'landlord' => NULL,
      'boiler_manufacturer' => 'NA',
      'boiler_id' => NULL,
      'boiler_type' => 'NA',
      'engineers_code' => NULL,
      'service_call_id' => NULL,
      'date_installed' => NULL,
      'date_sent' => NULL,
      'date_booked' => '2026-01-06 09:04:00',
      'date_processed' => NULL,
      'date_reported' => '2026-01-09 14:44:00',
      'fileid' => '878271',
      'filename' => '130-19051D2-34-GRANDE-RUE-LOG-NO-1.pdf',
      'client_id' => NULL,
      'client_name' => NULL,
      'customer_id' => NULL,
      'lab_ref' => NULL,
      'pack_type' => 'SEN',
      'card_complete' => '1',
      'on_hold' => '0',
      'pass_fail' => '1',
      'appearance_result' => '1',
      'appearance_pass_fail' => '1',
      'mains_cond_result' => '1200',
      'sys_cond_result' => '2160',
      'cond_pass_fail' => '1',
      'mains_cl_result' => '*',
      'sys_cl_result' => '*',
      'cl_pass_fail' => '1',
      'iron_result' => '12.0753332',
      'iron_pass_fail' => '1',
      'copper_result' => '-0.002348',
      'copper_pass_fail' => '1',
      'aluminium_result' => '0.0282277',
      'aluminium_pass_fail' => '1',
      'mains_calcium_result' => '109.0299838',
      'sys_calcium_result' => '71.8266658',
      'calcium_pass_fail' => '1',
      'ph_result' => '7.0',
      'ph_pass_fail' => '1',
      'sentinel_x100_result' => '2.31',
      'sentinel_x100_pass_fail' => '1',
      'molybdenum_result' => '173.5820818',
      'molybdenum_pass_fail' => NULL,
      'boron_result' => '2.1964985',
      'boron_pass_fail' => NULL,
      'manganese_result' => '0.4267016',
      'manganese_pass_fail' => NULL,
      'nitrate_result' => '1',
      'mob_ratio' => NULL,
      'created' => '2026-01-09 14:44:00',
      'updated' => '2026-01-29 16:01:00',
      'ucr' => '3518',
      'installer_company' => NULL,
      'old_pack_reference_number' => NULL,
      'duplicate_of' => '716506',
      'legacy' => NULL,
      'api_created_by' => NULL,
    ),
  ),
  758116 => 
  array (
    0 => 
    array (
      'pid' => '758116',
      'vid' => '2727721',
      'pack_reference_number' => '130:19524D2',
      'project_id' => NULL,
      'installer_name' => 'not provided',
      'installer_email' => NULL,
      'company_name' => 'SARL',
      'company_email' => 'customer.services@sentinelprotects.com;m.pen@hotmail.fr',
      'company_address1' => '53 bis Rue du Bois Saussier',
      'company_address2' => '',
      'company_town' => 'Checy',
      'company_county' => 'FRANCE',
      'company_postcode' => '45430',
      'company_tel' => '0610142040',
      'system_location' => 'idem',
      'system_age' => NULL,
      'system_6_months' => 'MORE6',
      'uprn' => NULL,
      'property_number' => NULL,
      'street' => NULL,
      'town_city' => NULL,
      'county' => NULL,
      'postcode' => '45430',
      'landlord' => NULL,
      'boiler_manufacturer' => 'NA',
      'boiler_id' => NULL,
      'boiler_type' => 'NA',
      'engineers_code' => NULL,
      'service_call_id' => NULL,
      'date_installed' => NULL,
      'date_sent' => NULL,
      'date_booked' => '2026-01-08 09:30:00',
      'date_processed' => NULL,
      'date_reported' => '2026-01-16 16:28:00',
      'fileid' => NULL,
      'filename' => NULL,
      'client_id' => NULL,
      'client_name' => NULL,
      'customer_id' => NULL,
      'lab_ref' => NULL,
      'pack_type' => 'SEN',
      'card_complete' => '1',
      'on_hold' => '0',
      'pass_fail' => '0',
      'appearance_result' => '2',
      'appearance_pass_fail' => NULL,
      'mains_cond_result' => '662',
      'sys_cond_result' => '1230',
      'cond_pass_fail' => NULL,
      'mains_cl_result' => '*',
      'sys_cl_result' => '*',
      'cl_pass_fail' => NULL,
      'iron_result' => '0.3135089',
      'iron_pass_fail' => NULL,
      'copper_result' => '0.0047968',
      'copper_pass_fail' => NULL,
      'aluminium_result' => '0.0554123',
      'aluminium_pass_fail' => NULL,
      'mains_calcium_result' => '83.823132',
      'sys_calcium_result' => '101.2730433',
      'calcium_pass_fail' => NULL,
      'ph_result' => '7.3',
      'ph_pass_fail' => NULL,
      'sentinel_x100_result' => '0.75',
      'sentinel_x100_pass_fail' => NULL,
      'molybdenum_result' => '56.9009978',
      'molybdenum_pass_fail' => NULL,
      'boron_result' => '13.6573745',
      'boron_pass_fail' => NULL,
      'manganese_result' => '0.4464394',
      'manganese_pass_fail' => NULL,
      'nitrate_result' => '1',
      'mob_ratio' => NULL,
      'created' => '2026-01-16 16:28:00',
      'updated' => '2026-01-16 16:28:00',
      'ucr' => '6526',
      'installer_company' => NULL,
      'old_pack_reference_number' => NULL,
      'duplicate_of' => '726466',
      'legacy' => NULL,
      'api_created_by' => NULL,
    ),
    1 => 
    array (
      'pid' => '758116',
      'vid' => '2727726',
      'pack_reference_number' => '130:19524D2',
      'project_id' => NULL,
      'installer_name' => 'not provided',
      'installer_email' => NULL,
      'company_name' => 'SARL',
      'company_email' => 'customer.services@sentinelprotects.com;m.pen@hotmail.fr',
      'company_address1' => '53 bis Rue du Bois Saussier',
      'company_address2' => '',
      'company_town' => 'Checy',
      'company_county' => 'FRANCE',
      'company_postcode' => '45430',
      'company_tel' => '0610142040',
      'system_location' => 'idem',
      'system_age' => NULL,
      'system_6_months' => 'MORE6',
      'uprn' => NULL,
      'property_number' => NULL,
      'street' => NULL,
      'town_city' => NULL,
      'county' => NULL,
      'postcode' => '45430',
      'landlord' => NULL,
      'boiler_manufacturer' => 'NA',
      'boiler_id' => NULL,
      'boiler_type' => 'NA',
      'engineers_code' => NULL,
      'service_call_id' => NULL,
      'date_installed' => NULL,
      'date_sent' => NULL,
      'date_booked' => '2026-01-08 09:30:00',
      'date_processed' => NULL,
      'date_reported' => '2026-01-16 16:28:00',
      'fileid' => '878256',
      'filename' => '130-19524D2-idem.pdf',
      'client_id' => NULL,
      'client_name' => NULL,
      'customer_id' => NULL,
      'lab_ref' => NULL,
      'pack_type' => 'SEN',
      'card_complete' => '1',
      'on_hold' => '0',
      'pass_fail' => '0',
      'appearance_result' => '2',
      'appearance_pass_fail' => '1',
      'mains_cond_result' => '662',
      'sys_cond_result' => '1230',
      'cond_pass_fail' => '1',
      'mains_cl_result' => '*',
      'sys_cl_result' => '*',
      'cl_pass_fail' => '1',
      'iron_result' => '0.3135089',
      'iron_pass_fail' => '1',
      'copper_result' => '0.0047968',
      'copper_pass_fail' => '1',
      'aluminium_result' => '0.0554123',
      'aluminium_pass_fail' => '1',
      'mains_calcium_result' => '83.823132',
      'sys_calcium_result' => '101.2730433',
      'calcium_pass_fail' => '1',
      'ph_result' => '7.3',
      'ph_pass_fail' => '1',
      'sentinel_x100_result' => '0.75',
      'sentinel_x100_pass_fail' => '0',
      'molybdenum_result' => '56.9009978',
      'molybdenum_pass_fail' => NULL,
      'boron_result' => '13.6573745',
      'boron_pass_fail' => NULL,
      'manganese_result' => '0.4464394',
      'manganese_pass_fail' => NULL,
      'nitrate_result' => '1',
      'mob_ratio' => NULL,
      'created' => '2026-01-16 16:28:00',
      'updated' => '2026-01-29 15:55:00',
      'ucr' => '6526',
      'installer_company' => NULL,
      'old_pack_reference_number' => NULL,
      'duplicate_of' => '726466',
      'legacy' => NULL,
      'api_created_by' => NULL,
    ),
  ),
);

// Batch processing configuration
$batch_size = 10; // Process this many pids at a time
$limit_pids = NULL; // Set to a number to limit total pids (e.g., 100), or NULL to process all

// Connect to D11 database
$d11_mysqli = new mysqli($d11_host, $d11_username, $d11_password, $d11_database, $d11_port);
if ($d11_mysqli->connect_error) {
  fwrite(STDERR, "D11 Database connection failed: {$d11_mysqli->connect_error}\n");
  exit(1);
}
$d11_mysqli->set_charset('utf8mb4');

print "Starting revision import process...\n";
print "Batch size: {$batch_size} pids per batch\n";
if ($limit_pids !== NULL) {
  print "Total pids limit: {$limit_pids}\n";
} else {
  print "Processing all pids from in-script data.\n";
}

// Step 1: Get total count of pids to process
print "Step 1: Getting total count of pids to process...\n";
$all_pids = array_keys($revisions_by_pid);
sort($all_pids, SORT_NUMERIC);
if ($limit_pids !== NULL) {
  $all_pids = array_slice($all_pids, 0, $limit_pids);
}
$total_pids = count($all_pids);

if ($total_pids == 0) {
  print "No pids found to process.\n";
  $d11_mysqli->close();
  exit(0);
}

$total_batches = ceil($total_pids / $batch_size);
print "Found {$total_pids} total pids to process in {$total_batches} batches.\n";

// Step 2: No global truncation; delete and replace per PID batch.
print "Step 2: Using per-PID delete/replace (no full truncation).\n";

// Step 3: Determine column list from in-script data
print "Step 3: Determining column list from in-script data...\n";
$d7_columns = [];
$exclude_columns = ['vid']; // vid is auto_increment in D11, so we exclude it from insert
foreach ($revisions_by_pid as $pid => $rows) {
  foreach ($rows as $row) {
    foreach (array_keys($row) as $field) {
      if (!in_array($field, $exclude_columns) && !in_array($field, $d7_columns)) {
        $d7_columns[] = $field;
      }
    }
  }
}
if (empty($d7_columns)) {
  fwrite(STDERR, "No revision columns found in in-script data.\n");
  $d11_mysqli->close();
  exit(1);
}

// Add the three special fields if they don't exist in D7 but exist in D11
$special_fields = ['sentinel_sample_hold_state_target_id', 'sentinel_company_address_target_id', 'sentinel_sample_address_target_id'];
foreach ($special_fields as $field) {
  if (!in_array($field, $d7_columns)) {
    $d7_columns[] = $field;
    print "Added special field {$field} to column list (not in D7 revision table).\n";
  }
}

print "Found " . count($d7_columns) . " columns to migrate.\n";

// Step 5: Get column types from D11 (once, before batch loop)
print "Step 5: Getting column types from D11...\n";
$d11_columns_result = $d11_mysqli->query("SHOW COLUMNS FROM sentinel_sample_revision");
if ($d11_columns_result === false) {
  fwrite(STDERR, "Error getting D11 columns: {$d11_mysqli->error}\n");
  $d11_mysqli->close();
  exit(1);
}

$column_types = [];
while ($row = $d11_columns_result->fetch_assoc()) {
  $column_types[$row['Field']] = $row['Type'];
}
$d11_columns_result->close();

// Initialize batch processing variables
$total_inserted = 0;
$total_updated = 0;
$d11_columns = $d7_columns; // Use same columns

// Helper function to escape and format value for SQL
function formatValueForSql($value, $column_type, $mysqli) {
  if (is_null($value)) {
    return 'NULL';
  }

  $column_type_lower = strtolower($column_type);

  // For numeric types, return as-is (but validate)
  if (strpos($column_type_lower, 'int') !== false ||
      strpos($column_type_lower, 'tinyint') !== false ||
      strpos($column_type_lower, 'float') !== false ||
      strpos($column_type_lower, 'double') !== false ||
      strpos($column_type_lower, 'decimal') !== false) {
    if (is_numeric($value)) {
      return $value;
    }
    return 'NULL';
  }

  // For datetime/date types
  if (strpos($column_type_lower, 'datetime') !== false ||
      strpos($column_type_lower, 'date') !== false ||
      strpos($column_type_lower, 'timestamp') !== false) {
    if (empty($value) || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
      return 'NULL';
    }
    return "'" . $mysqli->real_escape_string($value) . "'";
  }

  // For string types, escape and quote
  return "'" . $mysqli->real_escape_string($value) . "'";
}

// Step 4: Process pids in batches
print "\nStep 4: Starting batch processing...\n";
$offset = 0;

while ($offset < $total_pids) {
  $current_batch = floor($offset / $batch_size) + 1;
  print "\n--- Processing Batch {$current_batch}/{$total_batches} (offset: {$offset}) ---\n";

  // Get pids for this batch
  $batch_pids = array_slice($all_pids, $offset, $batch_size);

  if (empty($batch_pids)) {
    print "No more pids to process.\n";
    break;
  }

  print "Processing " . count($batch_pids) . " pids in this batch: " . implode(', ', array_slice($batch_pids, 0, 10)) . (count($batch_pids) > 10 ? '...' : '') . "\n";

  // Delete existing revisions for these pids (both revision tables) before re-inserting.
  $pids_placeholders_d11 = implode(',', $batch_pids);
  if (!$d11_mysqli->query("DELETE FROM sentinel_sample_field_revision WHERE pid IN ({$pids_placeholders_d11})")) {
    fwrite(STDERR, "Error deleting field revisions for batch: {$d11_mysqli->error}\n");
    $offset += $batch_size;
    continue;
  }
  if (!$d11_mysqli->query("DELETE FROM sentinel_sample_revision WHERE pid IN ({$pids_placeholders_d11})")) {
    fwrite(STDERR, "Error deleting revisions for batch: {$d11_mysqli->error}\n");
    $offset += $batch_size;
    continue;
  }

  // Get revision data from in-script array for this batch
  $revisions = [];
  foreach ($batch_pids as $pid) {
    if (!empty($revisions_by_pid[$pid])) {
      foreach ($revisions_by_pid[$pid] as $row) {
        $revisions[] = $row;
      }
    }
  }

  print "Found " . count($revisions) . " revisions to import for this batch.\n";

  // Get current values from sentinel_sample table for the three special fields
  $sample_values = [];
  $sample_query = "SELECT pid, sentinel_sample_hold_state_target_id, sentinel_company_address_target_id, sentinel_sample_address_target_id
                   FROM sentinel_sample
                   WHERE pid IN ({$pids_placeholders_d11})";
  $sample_result = $d11_mysqli->query($sample_query);
  if ($sample_result === false) {
    fwrite(STDERR, "Error fetching sample values: {$d11_mysqli->error}\n");
    $offset += $batch_size;
    continue;
  }

  while ($row = $sample_result->fetch_assoc()) {
    $pid_key = (int) $row['pid'];
    $sample_values[$pid_key] = [
      'sentinel_sample_hold_state_target_id' => $row['sentinel_sample_hold_state_target_id'] ?? NULL,
      'sentinel_company_address_target_id' => $row['sentinel_company_address_target_id'] ?? NULL,
      'sentinel_sample_address_target_id' => $row['sentinel_sample_address_target_id'] ?? NULL,
    ];
  }
  $sample_result->close();

  // Insert revisions for this batch
  print "Inserting revisions for this batch...\n";
  $inserted_count = 0;
  $new_vids = []; // Track new vid for each pid in this batch

  foreach ($revisions as $revision) {
    $pid = (int) $revision['pid'];
    $old_vid = (int) $revision['vid'];

    // Prepare values array
    $values = [];

    foreach ($d11_columns as $col) {
      // For the three special fields, use value from sentinel_sample table (D11)
      if (in_array($col, ['sentinel_sample_hold_state_target_id', 'sentinel_company_address_target_id', 'sentinel_sample_address_target_id'])) {
        if (isset($sample_values[$pid]) && isset($sample_values[$pid][$col])) {
          $raw_value = $sample_values[$pid][$col];
          // Convert empty string or null to NULL, otherwise use the value
          if ($raw_value === '' || $raw_value === null) {
            $value = NULL;
          } else {
            $value = $raw_value; // Keep as-is, formatValueForSql will handle it
          }
        } else {
          $value = NULL;
        }
      } else {
        // For regular fields, get from revision data (from D7)
        $value = $revision[$col] ?? NULL;
      }

      $column_type = $column_types[$col] ?? 'varchar(255)';
      $formatted_value = formatValueForSql($value, $column_type, $d11_mysqli);
      $values[] = $formatted_value;
    }

    // Build and execute INSERT statement
    $columns_sql = implode(', ', array_map(function ($col) {
      return "`{$col}`";
    }, $d11_columns));

    $values_sql = implode(', ', $values);

    $insert_sql = "INSERT INTO sentinel_sample_revision ({$columns_sql}) VALUES ({$values_sql})";

    if (!$d11_mysqli->query($insert_sql)) {
      fwrite(STDERR, "Error inserting revision (pid: {$pid}, old_vid: {$old_vid}): {$d11_mysqli->error}\n");
      continue;
    }

    $new_vid = $d11_mysqli->insert_id;
    $inserted_count++;

    // Track the latest vid for each pid
    if (!isset($new_vids[$pid]) || $new_vid > $new_vids[$pid]) {
      $new_vids[$pid] = $new_vid;
    }

    if ($inserted_count % 500 == 0) {
      print "  Inserted {$inserted_count} revisions in this batch...\n";
    }
  }

  print "Inserted {$inserted_count} revisions for this batch.\n";
  $total_inserted += $inserted_count;

  // Update vid in sentinel_sample table for this batch
  print "Updating vid in sentinel_sample table for this batch...\n";
  $update_vid_stmt = $d11_mysqli->prepare("UPDATE sentinel_sample SET vid = ? WHERE pid = ?");
  if ($update_vid_stmt === false) {
    fwrite(STDERR, "Error preparing update vid statement: {$d11_mysqli->error}\n");
    $offset += $batch_size;
    continue;
  }

  $updated_count = 0;
  foreach ($new_vids as $pid => $vid) {
    $update_vid_stmt->bind_param('ii', $vid, $pid);
    if ($update_vid_stmt->execute()) {
      $updated_count++;
    } else {
      fwrite(STDERR, "Error updating vid for pid {$pid}: {$update_vid_stmt->error}\n");
    }
  }
  $update_vid_stmt->close();
  print "Updated vid for {$updated_count} samples in this batch.\n";
  $total_updated += $updated_count;

  // Move to next batch
  $offset += $batch_size;

  // Progress update
  $processed_pids = min($offset, $total_pids);
  $progress = min(100, round(($processed_pids / $total_pids) * 100, 2));
  print "Batch {$current_batch} completed. Overall progress: {$progress}% ({$processed_pids}/{$total_pids} pids)\n";
}

// Summary
print "\n=== Final Summary ===\n";
print "Total batches processed: {$total_batches}\n";
print "Total pids processed: {$total_pids}\n";
print "Revisions replaced per PID batch (no full truncation).\n";
print "Total revisions imported: {$total_inserted}\n";
print "Total vids updated: {$total_updated}\n";
print "\nImport completed successfully!\n";

// Close connections
$d11_mysqli->close();
