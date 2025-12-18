<?php

namespace Drupal\sentinel_csv_processor\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Defines the Lab Data entity.
 *
 * @ContentEntityType(
 *   id = "lab_data",
 *   label = @Translation("Lab Data"),
 *   handlers = {
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *   },
 *   base_table = "lab_data",
 *   admin_permission = "administer lab_data",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "filename",
 *     "uuid" = "uuid",
 *   },
 *   translatable = FALSE,
 * )
 */
class LabData extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Set upload date if not set.
    if ($this->isNew() && $this->get('upload_date')->isEmpty()) {
      $now = new \DateTime('now', new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
      $this->set('upload_date', $now->format('Y-m-d H:i:s'));
    }

    // Set default status to 'pending' if not set.
    if ($this->get('status')->isEmpty()) {
      $this->set('status', 'pending');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Path field - storage location of the file.
    $fields['path'] = BaseFieldDefinition::create('string')
      ->setLabel(t('File Path'))
      ->setDescription(t('The storage location where the file is saved on the server.'))
      ->setSettings([
        'max_length' => 512,
        'text_processing' => 0,
      ])
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Filename field - original name of the uploaded CSV file.
    $fields['filename'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Filename'))
      ->setDescription(t('The original name of the uploaded CSV file.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Upload date field - date and time when the file was uploaded.
    $fields['upload_date'] = BaseFieldDefinition::create('datetime')
      ->setLabel(t('Upload Date'))
      ->setDescription(t('The date and time when the file was uploaded.'))
      ->setSettings([
        'datetime_type' => 'datetime',
      ])
      ->setRequired(TRUE)
      ->setDefaultValueCallback(static::class . '::getCurrentDateTimeDefault')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Status field - current processing status.
    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setDescription(t('The current processing status of the CSV file.'))
      ->setSettings([
        'allowed_values' => [
          'pending' => t('Pending'),
          'selected' => t('Selected'),
          'processing' => t('Processing'),
          'completed' => t('Completed'),
          'failed' => t('Failed'),
        ],
      ])
      ->setRequired(TRUE)
      ->setDefaultValue('pending')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Refname field - pack reference name for tracking/grouping.
    $fields['refname'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Reference Name'))
      ->setDescription(t('A pack reference name used for tracking or grouping related files.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setRequired(FALSE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * Default value callback for datetime fields.
   */
  public static function getCurrentDateTimeDefault(): array {
    $now = new \DateTime('now', new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
    return [$now->format('Y-m-d H:i:s')];
  }

  /**
   * Get the status value.
   *
   * @return string
   *   The status value.
   */
  public function getStatus(): string {
    return $this->get('status')->value ?? 'pending';
  }

  /**
   * Set the status value.
   *
   * @param string $status
   *   The status value.
   *
   * @return $this
   */
  public function setStatus(string $status): self {
    $this->set('status', $status);
    return $this;
  }

}

