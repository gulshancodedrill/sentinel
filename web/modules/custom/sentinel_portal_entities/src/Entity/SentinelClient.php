<?php

namespace Drupal\sentinel_portal_entities\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the Sentinel Client entity.
 *
 * @ContentEntityType(
 *   id = "sentinel_client",
 *   label = @Translation("Sentinel Client"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\sentinel_portal_entities\SentinelClientListBuilder",
 *     "views_data" = "Drupal\sentinel_portal_entities\SentinelClientViewsData",
 *     "form" = {
 *       "default" = "Drupal\sentinel_portal_entities\Form\SentinelClientForm",
 *       "add" = "Drupal\sentinel_portal_entities\Form\SentinelClientForm",
 *       "edit" = "Drupal\sentinel_portal_entities\Form\SentinelClientForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\sentinel_portal_entities\SentinelClientHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *   },
 *   base_table = "sentinel_client",
 *   admin_permission = "administer sentinel_client",
 *   entity_keys = {
 *     "id" = "cid",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "canonical" = "/portal/admin/clients/manage/{sentinel_client}/view",
 *     "add-form" = "/portal/admin/clients/add",
 *     "edit-form" = "/portal/admin/clients/manage/{sentinel_client}/edit",
 *     "delete-form" = "/portal/admin/clients/manage/{sentinel_client}/delete",
 *     "collection" = "/portal/admin/clients",
 *   },
 *   field_ui_base_route = "entity.sentinel_client.collection",
 * )
 */
class SentinelClient extends ContentEntityBase implements ContentEntityInterface {

  /**
   * Generate a luhn number for a UCR.
   *
   * @param int $number
   *   The number to generate the luhn number with.
   *
   * @return integer
   *   The calculated luhn number.
   */
  public function generateUcr($number) {
    // Force this number to be a string so we can work with it like a string.
    $number = (string) $number;

    // Set some initial values up.
    $length = strlen($number);
    $sum = 0;
    $flip = 1;

    $sumTable = [
      [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
      [0, 2, 4, 6, 8, 1, 3, 5, 7, 9]
    ];

    // Sum digits (last one is check digit, which is not in parameter)
    for ($i = $length - 1; $i >= 0; --$i) {
      $sum += $sumTable[$flip++ & 0x1][$number[$i]];
    }

    // Multiply by 9
    $sum *= 9;

    // Last digit of sum is check digit
    return (int) ($number . substr($sum, -1, 1));
  }

  /**
   * Get the UCR.
   *
   * The number returned will always be the luhn algorithm number.
   * This matches Drupal 7 behavior exactly.
   *
   * @return int
   *   The generated ucr number with the luhn checksum.
   */
  public function getUcr() {
    $ucr = $this->getRealUcr();
    return $this->generateUcr($ucr);
  }

  /**
   * Get the ucr from the sentinel_client entity.
   *
   * If the ucr property is not set then set it.
   * This matches Drupal 7 behavior exactly.
   *
   * @return int
   *   The ucr (without any checksum) number.
   */
  public function getRealUcr() {
    $current_ucr = $this->get('ucr')->value;

    if (!isset($current_ucr) || !$current_ucr) {
      // Find the current max number.
      $database = \Drupal::database();
      $last_ucr = $database->query('SELECT MAX(ucr) AS last_ucr FROM {sentinel_client} LIMIT 1')->fetchField();

      // Increment by one.
      ++$last_ucr;

      // Save this number back into the database.
      $this->set('ucr', $last_ucr);
      $this->save();

      return $last_ucr;
    }

    return $current_ucr;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['cid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Client ID'))
      ->setDescription(t('Primary Key: The client entity ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE)
      ->setRequired(TRUE)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayConfigurable('form', FALSE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Client Name'))
      ->setDescription(t('The client name.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setRequired(TRUE)
      ->setDefaultValue('')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['email'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Client Email'))
      ->setDescription(t('The client email.'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('User ID'))
      ->setDescription(t('The Drupal user ID. Changing this can have unforeseen consequences.'))
      ->setSetting('unsigned', TRUE)
      ->setRequired(FALSE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['api_key'] = BaseFieldDefinition::create('string')
      ->setLabel(t('API Key'))
      ->setDescription(t('The client API key. Leave empty to disable API key access.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setRequired(FALSE)
      ->setDefaultValue('')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['global_access'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Global Access'))
      ->setDescription(t('Should this client get global access to all samples? This permission applies to the API interface.'))
      ->setDefaultValue(FALSE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['send_pending'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Send Pending'))
      ->setDescription(t('Whether pending statuses should be sent back via an API call. This should be coupled with an API interface.'))
      ->setDefaultValue(FALSE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['ucr'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('UCR'))
      ->setDescription(t('The UCR number. This number is auto generated and can not be saved or altered.'))
      ->setSetting('unsigned', TRUE)
      ->setReadOnly(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['company'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Company'))
      ->setDescription(t('The client company.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setRequired(FALSE)
      ->setDefaultValue('')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('When this record was created.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['updated'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Updated'))
      ->setDescription(t('When this record was last updated.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
