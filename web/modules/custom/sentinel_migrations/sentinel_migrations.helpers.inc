<?php

/**
 * @file
 * Helper functions for sentinel migrations.
 */

/**
 * Check if a string is a valid UK postcode.
 *
 * @param string $to_check
 *   The postcode to check. This is formatted in-situ.
 *
 * @return bool
 *   TRUE if the postcode validates.
 */
function sentinel_migrations_postcode_check(&$to_check) {
  // Permitted letters depend upon their position in the postcode.
  // Character 1.
  $alpha1 = "[abcdefghijklmnoprstuwyz]";
  // Character 2.
  $alpha2 = "[abcdefghklmnopqrstuvwxy]";
  // Character 3.
  $alpha3 = "[abcdefghjkstuw]";
  // Character 4.
  $alpha4 = "[abehmnprvwxy]";
  // Character 5.
  $alpha5 = "[abdefghjlnpqrstuwxyz]";

  // Expression for postcodes: AN NAA, ANN NAA, AAN NAA, and AANN NAA with a space.
  // Or AN, ANN, AAN, AANN with no whitespace.
  $pcexp[0] = '^(' . $alpha1 . '{1}' . $alpha2 . '{0,1}[0-9]{1,2})([[:space:]]{0,})([0-9]{1}' . $alpha5 . '{2})?$';

  // Expression for postcodes: ANA NAA
  $pcexp[1] = '^(' . $alpha1 . '{1}[0-9]{1}' . $alpha3 . '{1})([[:space:]]{0,})([0-9]{1}' . $alpha5 . '{2})?$';

  // Expression for postcodes: AANA NAA
  $pcexp[2] = '^(' . $alpha1 . '{1}' . $alpha2 . '[0-9]{1}' . $alpha4 . ')([[:space:]]{0,})([0-9]{1}' . $alpha5 . '{2})?$';

  // Exception for the special postcode GIR 0AA
  $pcexp[3] = '^(gir)([[:space:]]{0,})?(0aa)?$';

  // Standard BFPO numbers.
  $pcexp[4] = '^(bfpo)([[:space:]]{0,})([0-9]{1,4})$';

  // c/o BFPO numbers.
  $pcexp[5] = '^(bfpo)([[:space:]]{0,})(c\/o([[:space:]]{0,})[0-9]{1,3})$';

  // Overseas Territories.
  $pcexp[6] = '^([a-z]{4})([[:space:]]{0,})(1zz)$';

  // Anquilla.
  $pcexp[7] = '^(ai\-2640)$';

  // Load up the string to check, converting into lowercase.
  $postcode = strtolower($to_check);

  // Assume we are not going to find a valid postcode.
  $valid = FALSE;

  // Check the string against the types of postcodes.
  foreach ($pcexp as $regexp) {
    if (preg_match('/' . $regexp . '/i', $postcode, $matches)) {
      // Load new postcode back into the form element.
      $postcode = strtoupper($matches[1]);
      if (isset($matches[3])) {
        $postcode .= ' ' . strtoupper($matches[3]);
      }

      // Take account of the special BFPO c/o format.
      $postcode = preg_replace('/C\/O/', 'c/o ', $postcode);

      // Remember that we have found that the code is valid and break from loop.
      $valid = TRUE;
      break;
    }
  }

  // Return with the reformatted valid postcode in uppercase if the postcode was valid.
  if ($valid) {
    $to_check = $postcode;
    return TRUE;
  }
  else {
    return FALSE;
  }
}
