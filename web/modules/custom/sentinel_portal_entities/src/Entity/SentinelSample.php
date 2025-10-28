<?php

namespace Drupal\sentinel_portal_entities\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the Sentinel Sample entity.
 *
 * @ContentEntityType(
 *   id = "sentinel_sample",
 *   label = @Translation("Sentinel Sample"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\sentinel_portal_entities\SentinelSampleListBuilder",
 *     "views_data" = "Drupal\sentinel_portal_entities\SentinelSampleViewsData",
 *     "form" = {
 *       "default" = "Drupal\sentinel_portal_entities\Form\SentinelSampleForm",
 *       "add" = "Drupal\sentinel_portal_entities\Form\SentinelSampleForm",
 *       "edit" = "Drupal\sentinel_portal_entities\Form\SentinelSampleForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\sentinel_portal_entities\SentinelSampleAccessControlHandler",
 *   },
 *   base_table = "sentinel_sample",
 *   admin_permission = "administer sentinel_sample",
 *   entity_keys = {
 *     "id" = "sid",
 *     "label" = "pack_reference_number",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "canonical" = "/portal/admin/samples/manage/{sentinel_sample}/view",
 *     "add-form" = "/portal/admin/samples/add",
 *     "edit-form" = "/portal/admin/samples/manage/{sentinel_sample}/edit",
 *     "delete-form" = "/portal/admin/samples/manage/{sentinel_sample}/delete",
 *     "collection" = "/portal/admin/samples",
 *   },
 *   field_ui_base_route = "entity.sentinel_sample.collection",
 * )
 */
class SentinelSample extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['pack_reference_number'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Pack Reference Number'))
      ->setDescription(t('The pack reference number.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['ucr'] = BaseFieldDefinition::create('string')
      ->setLabel(t('UCR'))
      ->setDescription(t('The UCR number.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['system_location'] = BaseFieldDefinition::create('string')
      ->setLabel(t('System Location'))
      ->setDescription(t('The system location.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['pass_fail'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Pass/Fail'))
      ->setDescription(t('Whether the sample passed or failed.'))
      ->setDefaultValue(NULL)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'boolean',
        'weight' => -1,
      ])
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

}
