<?php

namespace Drupal\sentinel_portal_entities\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the Sentinel Sample entity.
 *
 * @ContentEntityType(
 *   id = "sentinel_sample",
 *   label = @Translation("Sentinel Sample"),
 *   handlers = {
 *     "storage" = "Drupal\sentinel_portal_entities\SentinelSampleStorage",
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
 *       "html" = "Drupal\sentinel_portal_entities\SentinelSampleHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *   },
 *   base_table = "sentinel_sample",
 *   admin_permission = "administer sentinel_sample",
 *   entity_keys = {
 *     "id" = "pid",
 *     "label" = "pack_reference_number",
 *     "uuid" = "uuid",
 *   },
 *   translatable = FALSE,
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

    $fields['pid'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('Pack ID'))
            ->setDescription(t('Primary Key: The pack entity ID.'))
            ->setReadOnly(TRUE)
            ->setSetting('unsigned', TRUE)
            ->setRequired(TRUE)
            ->setDisplayConfigurable('view', FALSE)
            ->setDisplayConfigurable('form', FALSE);


        $fields['pack_reference_number'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Pack Reference Number'))
            ->setDescription(t('The pack reference number. This can be found at the top of the insert provided with your pack.'))
            ->setSettings([
                'max_length' => 30,
                'text_processing' => 0,
            ])
            ->setRequired(TRUE)
            ->setDefaultValue('')
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);

        $fields['installer_name'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Installer Name'))
            ->setDescription(t('Name of the individual engineer who conducted the work and subsequent SystemCheck.'))
            ->setSettings(['max_length' => 255])
            ->setRequired(TRUE)
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);

        $fields['installer_email'] = BaseFieldDefinition::create('email')
            ->setLabel(t('Installer Email'))
            ->setDescription(t('The email address of the installer who conducted the work and subsequent SystemCheck.'))
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);

        // Fields 2-3: Company Name and Email (Required)
        $fields['company_name'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Company Name'))
            ->setDescription(t('Name of the company managing installation/maintenance for the system.'))
            ->setSettings(['max_length' => 255])
            ->setRequired(TRUE)
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);

        $fields['company_email'] = BaseFieldDefinition::create('email')
            ->setLabel(t('Company Email'))
            ->setDescription(t('Email address of the company managing installation/maintenance. A copy of the SystemCheck report will be made available to this email address.'))
            ->setRequired(TRUE)
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);


        // Fields 4-5: Company Address Fields
        $fields['company_address1'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Company Address 1'))
            ->setDescription(t('The first line of the address of the company managing installation/maintenance for the system.'))
            ->setSettings(['max_length' => 255])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);




        // Project and installer fields
        $fields['project_id'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Project ID'))
            ->setDescription(t('The project ID. Required for claiming boiler manufacturer contract support.'))
            ->setSettings(['max_length' => 255])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);


        $fields['company_address2'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Company Address 2'))
            ->setDescription(t('The second line of the address of the company managing installation/maintenance for the system.'))
            ->setSettings(['max_length' => 255])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);


        // Fields 6-8: Company Location Fields
        $fields['company_town'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Company Town'))
            ->setDescription(t('The town of the company managing installation/maintenance for the system.'))
            ->setSettings(['max_length' => 255])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);

        $fields['company_county'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Company County'))
            ->setDescription(t('The county of the company managing installation/maintenance for the system.'))
            ->setSettings(['max_length' => 255])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);

        $fields['company_postcode'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Company Postcode'))
            ->setDescription(t('Postcode of the company managing installation/maintenance for the system.'))
            ->setSettings(['max_length' => 255])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);

        // Fields 9-10: Company Contact Fields
        $fields['company_tel'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Company Telephone'))
            ->setDescription(t('Telephone number of the company managing installation/maintenance for the system.'))
            ->setSettings(['max_length' => 255])
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

        $fields['system_age'] = BaseFieldDefinition::create('string')
            ->setLabel(t('System Age'))
            ->setDescription(t('The age of the system in months.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(FALSE)
            ->setDefaultValue('')
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 6,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 6,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);

        // System older than 6 months?
        $fields['system_6_months'] = BaseFieldDefinition::create('string')
            ->setLabel(t('System > 6 Months Old?'))
            ->setDescription(t('Is the system older than 6 months?'))
            ->setSettings([
                'max_length' => 10,
                'text_processing' => 0,
            ])
            ->setDefaultValue('')
            ->setRequired(FALSE)
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 1,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 1,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);

        // Unique Property Reference Number (UPRN)
        $fields['uprn'] = BaseFieldDefinition::create('string')
            ->setLabel(t('UPRN'))
            ->setDescription(t('Unique Property Reference Number for the system location. Required for claiming contract support and asset register compatibility.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(FALSE)
            ->setDefaultValue('')
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 9,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 9,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);

        // Property Number
        $fields['property_number'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Property Number'))
            ->setDescription(t('The property number of where the system is located.'))
            ->setSettings([
                'max_length' => 100,
                'text_processing' => 0,
            ])
            ->setRequired(FALSE)
            ->setDefaultValue('')
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 2,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 2,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);


        // Street
        $fields['street'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Street'))
            ->setDescription(t('Street of the property where the system is located.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(TRUE)
            ->setDefaultValue('')
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 3,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 3,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);
        // @deprecated Field retained for backward compatibility.
        // @todo Remove after confirming data migration completion.


        // Town / City
        $fields['town_city'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Town/City'))
            ->setDescription(t('Town or city of the property where the system is located.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(TRUE)
            ->setDefaultValue('')
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 4,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 4,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);
        // @deprecated Field retained for backward compatibility.


        // County
        $fields['county'] = BaseFieldDefinition::create('string')
            ->setLabel(t('County'))
            ->setDescription(t('County of the property where the system is located.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(TRUE)
            ->setDefaultValue('')
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 5,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 5,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);
        // @deprecated Field retained for backward compatibility.


        // Postcode
        $fields['postcode'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Postcode'))
            ->setDescription(t('Postcode of the property where the system is located.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(TRUE)
            ->setDefaultValue('')
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 6,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 6,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);
        // @deprecated Field retained for backward compatibility.


        // Landlord
        $fields['landlord'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Landlord'))
            ->setDescription(t('Name of the landlord/owner of the property. This may be an organisation or an individual.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(TRUE)
            ->setDefaultValue('')
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 7,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 7,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);

        // Boiler Manufacturer
        $fields['boiler_manufacturer'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Boiler Manufacturer'))
            ->setDescription(t('Manufacturer of the boiler.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(TRUE)
            ->setDefaultValue('')
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 5,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 5,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);


        // Boiler ID
        $fields['boiler_id'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Boiler ID'))
            ->setDescription(t('Boiler ID number as provided by the boiler manufacturer.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(FALSE)
            ->setDefaultValue('')
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 9,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 9,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);


        // Boiler Type
        $fields['boiler_type'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Boiler Type'))
            ->setDescription(t('The type of boiler fitted (i.e. combi, system).'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(FALSE)
            ->setDefaultValue('')
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 7,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 7,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);


        // Engineers Code
        $fields['engineers_code'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Engineers Code'))
            ->setDescription(t('The engineers code of the boiler.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(FALSE)
            ->setDefaultValue('')
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 1,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 1,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);


        // Service Call ID
        $fields['service_call_id'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Service Call ID'))
            ->setDescription(t('The service call ID of the boiler.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(FALSE)
            ->setDefaultValue('')
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 1,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 1,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);


        // Date Installed
        $fields['date_installed'] = BaseFieldDefinition::create('datetime')
            ->setLabel(t('Date Installed'))
            ->setDescription(t('Date that the boiler was installed.'))
            ->setRequired(TRUE)
            ->setDisplayOptions('form', [
                'type' => 'datetime_default',
                'weight' => 10,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'datetime_default',
                'weight' => 10,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);


        // Date Sent
        $fields['date_sent'] = BaseFieldDefinition::create('datetime')
            ->setLabel(t('Date Sent'))
            ->setDescription(t('Date that the water sample was sent to Sentinel.'))
            ->setRequired(FALSE)
            ->setDisplayOptions('form', [
                'type' => 'datetime_default',
                'weight' => 8,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'datetime_default',
                'weight' => 8,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);


        // Date Booked In
        $fields['date_booked'] = BaseFieldDefinition::create('datetime')
            ->setLabel(t('Date Booked In'))
            ->setDescription(t('The date the sample was booked in at the test facility.'))
            ->setRequired(FALSE)
            ->setDisplayOptions('form', [
                'type' => 'datetime_default',
                'weight' => 1,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'datetime_default',
                'weight' => 1,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);


        // Date Processed
        $fields['date_processed'] = BaseFieldDefinition::create('datetime')
            ->setLabel(t('Date Processed'))
            ->setDescription(t('The date the sample was processed.'))
            ->setRequired(FALSE)
            ->setDisplayOptions('form', [
                'type' => 'datetime_default',
                'weight' => 1,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'datetime_default',
                'weight' => 1,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);


        // Date Reported
        $fields['date_reported'] = BaseFieldDefinition::create('datetime')
            ->setLabel(t('Date Reported'))
            ->setDescription(t('The date the results were reported.'))
            ->setRequired(FALSE)
            ->setDisplayOptions('form', [
                'type' => 'datetime_default',
                'weight' => 1,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'datetime_default',
                'weight' => 1,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);


        // File ID
        $fields['fileid'] = BaseFieldDefinition::create('string')
            ->setLabel(t('File ID'))
            ->setDescription(t('The file ID of the results file.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(FALSE)
            ->setDefaultValue('')
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 1,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 1,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);


        // Filename
        $fields['filename'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Filename'))
            ->setDescription(t('The filename of the results file.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(FALSE)
            ->setDefaultValue('')
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 1,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 1,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);

        // Client ID.
        $fields['client_id'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('The Client ID'))
            ->setDescription(t('The ID of the client (used internally).'))
            ->setSettings([
                'unsigned' => TRUE,
            ])
            ->setRequired(FALSE)
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE)
            ->setDisplayOptions('form', [
                'type' => 'number',
                'weight' => 1,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'weight' => 1,
            ]);

        // Client Name.
        $fields['client_name'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Client Name'))
            ->setDescription(t('The name of the client (added for legacy purposes).'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(FALSE)
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE)
            ->setReadOnly(TRUE)
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 1,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'weight' => 1,
            ]);

        // Customer ID.
        $fields['customer_id'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Sentinel Customer ID'))
            ->setDescription(t('Your Sentinel Unique Customer Reference number (UCR). This can be found in your account settings.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(FALSE)
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE)
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 4,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'weight' => 4,
            ]);

        // Lab Reference.
        $fields['lab_ref'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Lab Ref'))
            ->setDescription(t('The lab reference of the sample (used by testing lab).'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(FALSE)
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);

        // Pack Type.
        $fields['pack_type'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Pack Type'))
            ->setDescription(t('The type of the pack (dictates the type of test being run).'))
            ->setSettings([
                'max_length' => 10,
                'text_processing' => 0,
            ])
            ->setRequired(FALSE)
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);

        // Card Complete.
        $fields['card_complete'] = BaseFieldDefinition::create('boolean')
            ->setLabel(t('Card Complete'))
            ->setDescription(t('If the card is complete.'))
            ->setRequired(FALSE)
            ->setReadOnly(TRUE)
            ->setDisplayConfigurable('view', TRUE);

        // On Hold.
        $fields['on_hold'] = BaseFieldDefinition::create('boolean')
            ->setLabel(t('On Hold'))
            ->setDescription(t('If the sample is on hold.'))
            ->setRequired(FALSE)
            ->setReadOnly(TRUE)
            ->setDisplayConfigurable('view', TRUE);

        // Hold state legacy reference (taxonomy term ID).
        $fields['sentinel_sample_hold_state_target_id'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('Hold State Target ID'))
            ->setDescription(t('Legacy hold state reference (taxonomy term ID).'))
            ->setSettings([
                'unsigned' => TRUE,
            ])
            ->setRequired(FALSE)
            ->setDisplayConfigurable('form', FALSE)
            ->setDisplayConfigurable('view', FALSE);

        // Company address legacy reference (address entity ID).
        $fields['sentinel_company_address_target_id'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('Company Address Target ID'))
            ->setDescription(t('Legacy company address entity reference ID.'))
            ->setSettings([
                'unsigned' => TRUE,
            ])
            ->setRequired(FALSE)
            ->setDisplayConfigurable('form', FALSE)
            ->setDisplayConfigurable('view', FALSE);

        // Sample address legacy reference (address entity ID).
        $fields['sentinel_sample_address_target_id'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('Sample Address Target ID'))
            ->setDescription(t('Legacy sample address entity reference ID.'))
            ->setSettings([
                'unsigned' => TRUE,
            ])
            ->setRequired(FALSE)
            ->setDisplayConfigurable('form', FALSE)
            ->setDisplayConfigurable('view', FALSE);

        // Pass/Fail.
        $fields['pass_fail'] = BaseFieldDefinition::create('boolean')
            ->setLabel(t('Overall Pass/Fail'))
            ->setDescription(t('The overall pass or fail mark of the sample.'))
            ->setRequired(FALSE)
            ->setReadOnly(TRUE)
            ->setDisplayConfigurable('view', TRUE);

        // Appearance Result.
        $fields['appearance_result'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Appearance Result'))
            ->setDescription(t('The result of the appearance test.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setRequired(FALSE)
            ->setReadOnly(TRUE)
            ->setDisplayConfigurable('view', TRUE);

        // Appearance Pass/Fail.
        $fields['appearance_pass_fail'] = BaseFieldDefinition::create('boolean')
            ->setLabel(t('Appearance Pass/Fail'))
            ->setDescription(t('The pass and fail mark for the appearance test.'))
            ->setRequired(FALSE)
            ->setReadOnly(TRUE)
            ->setDisplayConfigurable('view', TRUE);


        // Mains Conductivity Result.
        $fields['mains_cond_result'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Mains Conductivity Result'))
            ->setDescription(t('The result of the mains conductivity test.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setReadOnly(TRUE)
            ->setRequired(FALSE)
            ->setDisplayConfigurable('view', TRUE)
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 1,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'weight' => 1,
            ]);

        // System Conductivity Result.
        $fields['sys_cond_result'] = BaseFieldDefinition::create('string')
            ->setLabel(t('System Conductivity Result'))
            ->setDescription(t('The result of the system conductivity test.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setReadOnly(TRUE)
            ->setRequired(FALSE)
            ->setDisplayConfigurable('view', TRUE);

        // Conductivity Pass/Fail.
        $fields['cond_pass_fail'] = BaseFieldDefinition::create('boolean')
            ->setLabel(t('Conductivity Pass/Fail'))
            ->setDescription(t('The pass and fail mark for the conductivity test.'))
            ->setReadOnly(TRUE)
            ->setRequired(FALSE)
            ->setDisplayConfigurable('view', TRUE);

        // Mains Chlorine Result.
        $fields['mains_cl_result'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Mains Chlorine Result'))
            ->setDescription(t('The result of the chlorine test.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setReadOnly(TRUE)
            ->setRequired(FALSE)
            ->setDisplayConfigurable('view', TRUE);

        // System Chlorine Result.
        $fields['sys_cl_result'] = BaseFieldDefinition::create('string')
            ->setLabel(t('System Chlorine Result'))
            ->setDescription(t('The result of the chlorine test.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setReadOnly(TRUE)
            ->setRequired(FALSE)
            ->setDisplayConfigurable('view', TRUE);

        // Chlorine Pass/Fail.
        $fields['cl_pass_fail'] = BaseFieldDefinition::create('boolean')
            ->setLabel(t('Chlorine Pass/Fail'))
            ->setDescription(t('The pass and fail mark for the chlorine test.'))
            ->setReadOnly(TRUE)
            ->setRequired(FALSE)
            ->setDisplayConfigurable('view', TRUE);

        // Iron Result.
        $fields['iron_result'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Iron Result'))
            ->setDescription(t('The result of the iron test.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setReadOnly(TRUE)
            ->setRequired(FALSE)
            ->setDisplayConfigurable('view', TRUE);

        // Iron Pass/Fail.
        $fields['iron_pass_fail'] = BaseFieldDefinition::create('boolean')
            ->setLabel(t('Iron Pass/Fail'))
            ->setDescription(t('The pass and fail mark for the iron test.'))
            ->setReadOnly(TRUE)
            ->setRequired(FALSE)
            ->setDisplayConfigurable('view', TRUE);

        // Copper Result.
        $fields['copper_result'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Copper Result'))
            ->setDescription(t('The result of the copper test.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setReadOnly(TRUE)
            ->setRequired(FALSE)
            ->setDisplayConfigurable('view', TRUE);

        // Copper Pass/Fail.
        $fields['copper_pass_fail'] = BaseFieldDefinition::create('boolean')
            ->setLabel(t('Copper Pass/Fail'))
            ->setDescription(t('The pass and fail mark for the copper test.'))
            ->setReadOnly(TRUE)
            ->setRequired(FALSE)
            ->setDisplayConfigurable('view', TRUE);

        // Aluminium Result.
        $fields['aluminium_result'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Aluminium Result'))
            ->setDescription(t('The result of the aluminium test.'))
            ->setSettings([
                'max_length' => 255,
                'text_processing' => 0,
            ])
            ->setReadOnly(TRUE)
            ->setRequired(FALSE)
            ->setDisplayConfigurable('view', TRUE);


        $fields['aluminium_pass_fail'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('Aluminium Pass/Fail'))
            ->setDescription(t('The pass and fail mark for the aluminium test.'))
            ->setSetting('unsigned', TRUE)
            ->setRequired(FALSE)
            ->setDisplayOptions('form', [
                'type' => 'number',
                'weight' => 1,
                'region' => 'result_details',
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE)
            ->setSetting('size', 'tiny')
            ->setSetting('disabled', TRUE)
            ->setSetting('portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                ],
                'form_section' => 'result_details',
                'weight' => 1,
                'required' => FALSE,
                'title' => t('Aluminium Pass/Fail'),
                'value_callback' => 'sentinel_portal_entities_print_pass_fail',
            ]);

        $fields['mains_calcium_result'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Mains Calcium Result'))
            ->setDescription(t('The result of the mains calcium test.'))
            ->setSettings([
                'max_length' => 255,
            ])
            ->setRequired(FALSE)
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 1,
                'region' => 'result_details',
            ])
            ->setSetting('disabled', TRUE)
            ->setSetting('portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                    'sample_results_required' => TRUE,
                ],
                'required_results_field' => [
                    'standard' => TRUE,
                    'vaillant' => FALSE,
                ],
                'form_section' => 'result_details',
                'weight' => 1,
                'title' => t('Mains Calcium Result'),
            ]);

        $fields['sys_calcium_result'] = BaseFieldDefinition::create('string')
            ->setLabel(t('System Calcium Result'))
            ->setDescription(t('The result of the system calcium test.'))
            ->setSettings([
                'max_length' => 255,
            ])
            ->setRequired(FALSE)
            ->setSetting('disabled', TRUE)
            ->setSetting('portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                    'sample_results_required' => TRUE,
                ],
                'required_results_field' => [
                    'standard' => TRUE,
                    'vaillant' => FALSE,
                ],
                'form_section' => 'result_details',
                'weight' => 1,
                'title' => t('System Calcium Result'),
            ]);

        $fields['calcium_pass_fail'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('Calcium Pass/Fail'))
            ->setDescription(t('The pass and fail mark for the calcium test.'))
            ->setSetting('unsigned', TRUE)
            ->setSetting('size', 'tiny')
            ->setRequired(FALSE)
            ->setSetting('disabled', TRUE)
            ->setSetting('portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                ],
                'form_section' => 'result_details',
                'weight' => 1,
                'title' => t('Calcium Pass/Fail'),
                'value_callback' => 'sentinel_portal_entities_print_pass_fail',
            ]);

        $fields['ph_result'] = BaseFieldDefinition::create('string')
            ->setLabel(t('pH Result'))
            ->setDescription(t('The result of the pH test.'))
            ->setSettings(['max_length' => 255])
            ->setSetting('disabled', TRUE)
            ->setSetting('portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                    'sample_results_required' => TRUE,
                ],
                'required_results_field' => [
                    'standard' => TRUE,
                    'vaillant' => TRUE,
                ],
                'form_section' => 'result_details',
                'weight' => 1,
                'title' => t('pH Result'),
            ]);

        $fields['ph_pass_fail'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('pH Pass/Fail'))
            ->setDescription(t('The pass and fail mark for the pH test.'))
            ->setSetting('unsigned', TRUE)
            ->setSetting('size', 'tiny')
            ->setRequired(FALSE)
            ->setSetting('disabled', TRUE)
            ->setSetting('portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                ],
                'form_section' => 'result_details',
                'weight' => 1,
                'title' => t('pH Pass/Fail'),
                'value_callback' => 'sentinel_portal_entities_print_pass_fail',
            ]);

        $fields['sentinel_x100_result'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Inhibitor Result'))
            ->setDescription(t('The result of the Inhibitor test.'))
            ->setSettings(['max_length' => 255])
            ->setSetting('disabled', TRUE)
            ->setSetting('portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                    'sample_results_required' => TRUE,
                ],
                'form_section' => 'result_details',
                'weight' => 1,
                'title' => t('Inhibitor Result'),
            ]);

        $fields['sentinel_x100_pass_fail'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('Inhibitor Pass/Fail'))
            ->setDescription(t('The pass and fail mark for the Inhibitor test.'))
            ->setSetting('unsigned', TRUE)
            ->setSetting('size', 'tiny')
            ->setRequired(FALSE)
            ->setSetting('disabled', TRUE)
            ->setSetting('portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                ],
                'form_section' => 'result_details',
                'weight' => 1,
                'title' => t('Inhibitor Pass/Fail'),
                'value_callback' => 'sentinel_portal_entities_print_pass_fail',
            ]);

        $fields['molybdenum_result'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Molybdenum Result'))
            ->setDescription(t('The result of the Molybdenum test.'))
            ->setSettings(['max_length' => 255])
            ->setSetting('disabled', TRUE)
            ->setSetting('portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                    'sample_results_required' => TRUE,
                ],
                'required_results_field' => [
                    'standard' => TRUE,
                    'vaillant' => TRUE,
                ],
                'form_section' => 'result_details',
                'weight' => 1,
                'title' => t('Molybdenum Result'),
            ]);

        $fields['molybdenum_pass_fail'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('Molybdenum Pass/Fail'))
            ->setDescription(t('The pass and fail mark for the Molybdenum test.'))
            ->setSetting('unsigned', TRUE)
            ->setSetting('size', 'tiny')
            ->setRequired(FALSE)
            ->setSetting('disabled', TRUE)
            ->setSetting('portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                ],
                'form_section' => 'result_details',
                'weight' => 1,
                'title' => t('Molybdenum Pass/Fail'),
                'value_callback' => 'sentinel_portal_entities_print_pass_fail',
            ]);

        $fields['boron_result'] = BaseFieldDefinition::create('string')
            ->setLabel(t('XXX Result'))
            ->setDescription(t('The result of the XXX test.'))
            ->setSettings(['max_length' => 255])
            ->setSetting('disabled', TRUE)
            ->setSetting('portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                    'sample_results_required' => TRUE,
                ],
                'required_results_field' => [
                    'standard' => TRUE,
                    'vaillant' => TRUE,
                ],
                'form_section' => 'result_details',
                'weight' => 1,
                'title' => t('XXX Result'),
            ]);

        $fields['boron_pass_fail'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('XXX Pass/Fail'))
            ->setDescription(t('The pass and fail mark for the XXX test.'))
            ->setSetting('unsigned', TRUE)
            ->setSetting('size', 'tiny')
            ->setRequired(FALSE)
            ->setSetting('disabled', TRUE)
            ->setSetting('portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                ],
                'form_section' => 'result_details',
                'weight' => 1,
                'title' => t('XXX Pass/Fail'),
                'value_callback' => 'sentinel_portal_entities_print_pass_fail',
            ]);

        $fields['manganese_result'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Manganese Result'))
            ->setDescription(t('The result of the Manganese test.'))
            ->setSettings(['max_length' => 255])
            ->setSetting('disabled', TRUE)
            ->setSetting('portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                    'sample_results_required' => TRUE,
                ],
                'required_results_field' => [
                    'standard' => TRUE,
                    'vaillant' => TRUE,
                ],
                'form_section' => 'result_details',
                'weight' => 1,
                'title' => t('Manganese Result'),
            ]);

        $fields['manganese_pass_fail'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('Manganese Pass/Fail'))
            ->setDescription(t('The pass and fail mark for the Manganese test.'))
            ->setSetting('unsigned', TRUE)
            ->setSetting('size', 'tiny')
            ->setRequired(FALSE)
            ->setSetting('disabled', TRUE)
            ->setSetting('portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                ],
                'form_section' => 'result_details',
                'weight' => 1,
                'title' => t('Manganese Pass/Fail'),
                'value_callback' => 'sentinel_portal_entities_print_pass_fail',
            ]);


        // Nitrate Result field.
        $fields['nitrate_result'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Nitrate Result'))
            ->setDescription(t('The result of the Nitrate test.'))
            ->setSettings([
                'max_length' => 255,
            ])
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 1,
                'region' => 'result_details',
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 1,
                'region' => 'result_details',
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE)
            ->setRequired(FALSE)
            ->setReadOnly(TRUE)
            ->setCustomStorage(TRUE)
            ->setPropertyConstraints('value', [
                // Example: you can implement validation here.
            ])
            ->setSetting('custom_portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                    'sample_results_required' => TRUE,
                ],
                'required_results_field' => [
                    'standard' => TRUE,
                    'vaillant' => TRUE,
                ],
                'disabled' => TRUE,
                'form_section' => 'result_details',
                'weight' => 1,
                'required' => FALSE,
                'value_callback' => 'sentinel_portal_entities_nitrate_result',
            ]);

        // Molybdenum and XXX Ratio field.
        $fields['mob_ratio'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Molybdenum and XXX Ratio'))
            ->setDescription(t('The ratio of the Molybdenum and XXX concentrations.'))
            ->setSettings([
                'max_length' => 255,
            ])
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 1,
                'region' => 'result_details',
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 1,
                'region' => 'result_details',
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE)
            ->setRequired(FALSE)
            ->setReadOnly(TRUE)
            ->setSetting('custom_portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                    'sample_results_required' => TRUE,
                ],
                'disabled' => TRUE,
                'form_section' => 'result_details',
                'weight' => 1,
                'required' => FALSE,
            ]);

        // Updated field.
        $fields['updated'] = BaseFieldDefinition::create('datetime')
            ->setLabel(t('Updated'))
            ->setDescription(t('When this record was last updated.'))
            ->setSetting('datetime_type', 'datetime')
            ->setRequired(FALSE)
            ->setReadOnly(TRUE)
            ->setDisplayOptions('form', [
                'type' => 'datetime_default',
                'weight' => 1,
            ])
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'datetime_default',
                'weight' => 1,
            ])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE)
            ->setSetting('custom_portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                ],
                'disabled' => TRUE,
                'form_section' => FALSE,
                'weight' => 1,
                'required' => FALSE,
                'title' => t('Updated'),
            ]);




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


        $fields['installer_company'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Installer Company'))
            ->setDescription(t('Please provide the name of the company managing installation/maintenance on this system.'))
            ->setSettings(['max_length' => 255])
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);



        // Old Pack Reference Number.
        $fields['old_pack_reference_number'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Old Pack Reference Number'))
            ->setDescription(t('The old pack reference number. Used for duplication fault state emails.'))
            ->setSettings([
                'max_length' => 30,
            ])
            ->setRequired(FALSE)
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => -49,
            ])
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => -49,
            ])
            ->setDisplayConfigurable('view', TRUE)
            ->setDisplayConfigurable('form', TRUE)
            ->setSetting('custom_portal_config', [
                'access' => [], // No access given.
                'form_section' => FALSE,
                'weight' => -49,
                'required' => FALSE,
                'title' => t('The old pack reference number'),
            ]);

        // Duplicate Of.
        $fields['duplicate_of'] = BaseFieldDefinition::create('string')
            ->setLabel(t('Duplicate Of'))
            ->setDescription(t('This sample is a duplicate of another sample.'))
            ->setSettings([
                'max_length' => 255,
            ])
            ->setRequired(FALSE)
            ->setReadOnly(TRUE) // Disabled = read-only in Drupal 11.
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'string',
                'weight' => 1,
            ])
            ->setDisplayOptions('form', [
                'type' => 'string_textfield',
                'weight' => 1,
            ])
            ->setDisplayConfigurable('view', TRUE)
            ->setDisplayConfigurable('form', TRUE)
            ->setSetting('custom_portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                ],
                'disabled' => TRUE,
                'form_section' => 'result_details',
                'weight' => 1,
                'required' => FALSE,
                'title' => t('Duplicate Of'),
            ]);

        // Legacy Sample.
        $fields['legacy'] = BaseFieldDefinition::create('boolean')
            ->setLabel(t('Legacy Sample'))
            ->setDescription(t('Indicates if this is a legacy sample.'))
            ->setRequired(FALSE)
            ->setReadOnly(TRUE)
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'boolean',
                'weight' => 1,
            ])
            ->setDisplayOptions('form', [
                'type' => 'boolean_checkbox',
                'weight' => 1,
            ])
            ->setDisplayConfigurable('view', TRUE)
            ->setDisplayConfigurable('form', TRUE)
            ->setSetting('custom_portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                ],
                'disabled' => TRUE,
                'form_section' => 'result_details',
                'weight' => 1,
                'required' => FALSE,
                'title' => t('Legacy Sample'),
            ]);

        // API Created By.
        $fields['api_created_by'] = BaseFieldDefinition::create('integer')
            ->setLabel(t('API Created By'))
            ->setDescription(t('API user who created this sample.'))
            ->setRequired(FALSE)
            ->setReadOnly(TRUE)
            ->setDisplayOptions('view', [
                'label' => 'above',
                'type' => 'number_integer',
                'weight' => 1,
            ])
            ->setDisplayOptions('form', [
                'type' => 'number',
                'weight' => 1,
            ])
            ->setDisplayConfigurable('view', TRUE)
            ->setDisplayConfigurable('form', TRUE)
            ->setSetting('custom_portal_config', [
                'access' => [
                    'data' => 'admin',
                    'view' => 'admin',
                    'create' => 'admin',
                    'edit' => 'admin',
                ],
                'disabled' => TRUE,
                'form_section' => 'result_details',
                'weight' => 1,
                'required' => FALSE,
                'title' => t('API Created By'),
            ]);

        $fields['created'] = BaseFieldDefinition::create('datetime')
            ->setLabel(t('Created'))
            ->setDescription(t('The time that the entity was created.'))
            ->setSetting('datetime_type', 'datetime')
            ->setRequired(FALSE)
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);

        $fields['changed'] = BaseFieldDefinition::create('datetime')
            ->setLabel(t('Changed'))
            ->setDescription(t('The time that the entity was last edited.'))
            ->setSetting('datetime_type', 'datetime')
            ->setRequired(FALSE)
            ->setDisplayConfigurable('form', TRUE)
            ->setDisplayConfigurable('view', TRUE);

        return $fields;
    }

    /**
     * Validate a sample based on the type of sample that it is.
     *
     * Matches D7 SentinelSampleEntity::validateSample().
     *
     * @return array
     *   An array of error messages for the fields that failed validation.
     *   Keyed by field name, value is error message.
     */
    public function validateSample() {
        $data = [];
        
        // Convert entity to array format for validation
        $field_definitions = $this->getFieldDefinitions();
        foreach ($field_definitions as $field_name => $field_definition) {
            if ($this->hasField($field_name) && !$this->get($field_name)->isEmpty()) {
                $value = $this->get($field_name)->value;
                if ($value !== NULL && $value !== '') {
                    $data[$field_name] = $value;
                }
            }
        }
        
        // Get values from entity properties
        $properties = ['pack_reference_number', 'customer_id', 'project_id', 'boiler_id', 
                       'company_name', 'company_tel', 'installer_email', 'property_number', 
                       'street', 'town_city', 'county', 'postcode', 'date_installed'];
        foreach ($properties as $prop) {
            if (property_exists($this, $prop) && isset($this->{$prop}) && $this->{$prop} !== '' && $this->{$prop} !== NULL) {
                $data[$prop] = $this->{$prop};
            }
        }
        
        // Use validation service (matches D7 SentinelSampleEntityValidation::validateSample)
        return \Drupal\sentinel_portal_entities\Service\SentinelSampleValidation::validateSample($data);
    }

  /**
   * Get the country code for the sample based on pack reference number.
   *
   * Returns the country code conforming to ISO 3166-1 alpha-2 standard.
   *
   * @return string
   *   The country code (gb, de, fr, it).
   */
  public function getSampleCountry() {
    return self::packGetCountryType($this->pack_reference_number->value);
  }

  /**
   * Get country type from pack reference number.
   *
   * @param string $pack_reference_number
   *   The pack reference number.
   *
   * @return string
   *   The country code.
   */
  public static function packGetCountryType($pack_reference_number = NULL) {
    if (empty($pack_reference_number)) {
      return 'gb';
    }

    switch (substr($pack_reference_number, 0, 3)) {
      case '120':
        // Italian.
        return 'it';

      case '210':
        // Deliberate fall through.
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
   * Get the sample type based on pack reference number.
   *
   * @return string
   *   The sample type (vaillant, standard, worcesterbosch_contract, worcesterbosch_service).
   */
  public function getSampleType() {
    $data = [
      'pack_reference_number' => $this->get('pack_reference_number')->value,
      'customer_id' => $this->get('customer_id')->value ?? '',
      'project_id' => $this->get('project_id')->value ?? '',
      'boiler_id' => $this->get('boiler_id')->value ?? '',
    ];
    return self::getPackType($data);
  }

  /**
   * Get pack type from pack reference number.
   *
   * Sample types:
   * - Standard Systemcheck Pack: 102 (returns 'standard')
   * - Vaillant Systemcheck Pack: 001 (returns 'vaillant')
   * - Worcester Bosch Contract Form: 005 (returns 'worcesterbosch_contract')
   * - Worcester Bosch Service Form: 006 (returns 'worcesterbosch_service')
   *
   * @param array $data
   *   Array with keys: pack_reference_number, customer_id, project_id, boiler_id.
   *
   * @return string
   *   The pack type.
   */
  public static function getPackType($data) {
    switch (substr($data['pack_reference_number'], 0, 3)) {
      case '001':
        // Vaillant Systemcheck Pack.
        return 'vaillant';

      case '005':
        // Worcester Bosch Contract Form.
        return 'worcesterbosch_contract';

      case '006':
        // Worcester Bosch Service Form.
        return 'worcesterbosch_service';

      case '102':
        // Deliberate fall through.
      default:
        // Standard Systemcheck Pack.
        return 'standard';
    }
  }
}
