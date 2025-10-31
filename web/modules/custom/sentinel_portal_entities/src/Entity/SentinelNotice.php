<?php

namespace Drupal\sentinel_portal_entities\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the Sentinel Notice entity.
 *
 * @ContentEntityType(
 *   id = "sentinel_notice",
 *   label = @Translation("Sentinel Notice"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\sentinel_portal_entities\SentinelNoticeListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "add" = "Drupal\Core\Entity\ContentEntityForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\sentinel_portal_entities\SentinelNoticeHtmlRouteProvider",
 *     },
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *   },
 *   base_table = "sentinel_notice",
 *   admin_permission = "administer sentinel_notice",
 *   entity_keys = {
 *     "id" = "nid",
 *     "label" = "title",
 *   },
 *   links = {
 *     "canonical" = "/portal/admin/notice/{sentinel_notice}",
 *     "delete-form" = "/portal/admin/notice/{sentinel_notice}/delete",
 *     "collection" = "/portal/admin/notice",
 *   },
 *   field_ui_base_route = "entity.sentinel_notice.collection",
 * )
 */
class SentinelNotice extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['nid'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Notice ID'))
      ->setDescription(t('Primary Key: The notice entity ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE)
      ->setRequired(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user ID of the notice owner.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The title of the notice.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['notice'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notice'))
      ->setDescription(t('The notice message.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['notice_read'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Read'))
      ->setDescription(t('If this notice has been read.'))
      ->setDefaultValue(FALSE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('When this record was created.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}

