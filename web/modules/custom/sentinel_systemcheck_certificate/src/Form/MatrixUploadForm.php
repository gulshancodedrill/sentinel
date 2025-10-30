<?php

namespace Drupal\sentinel_systemcheck_certificate\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;

/**
 * Form for uploading matrix CSV files.
 */
class MatrixUploadForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'report_matrix_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['matrix-upload-container'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Upload Matrix'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];

    $form['matrix-upload-container']['file-upload'] = [
      '#type' => 'file',
      '#title' => $this->t('Upload your csv file'),
    ];

    $form['matrix-upload-container']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload Matrix'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $file = file_save_upload('file-upload', ['file_validate_extensions' => ['csv']], FALSE, FileSystemInterface::EXISTS_REPLACE);

    if ($file) {
      $form_state->setValue('file-upload', $file);
    } else {
      $form_state->setErrorByName('matrix-upload-container', $this->t('File could not be saved successfully please try again.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $file = $form_state->getValue('file-upload');
    $csv_contents = $this->getCsvContents($file);

    $mapped_fields = $this->mapCsvHeadingsToConditionEntityFields();

    if (!empty($csv_contents)) {
      foreach ($csv_contents as $row_number => $condition_entity_info) {
        $this->createConditionEntity($mapped_fields, $condition_entity_info);
      }
      $this->regenerateLogicFromConditionEntities();
    } else {
      \Drupal::logger('sentinel_systemcheck_certificate')->error('Could not grab the csv contents from the matrix');
    }
  }

  /**
   * Get CSV contents from uploaded file.
   *
   * @param object $file
   *   The uploaded file object.
   *
   * @return array
   *   Array of CSV data.
   */
  protected function getCsvContents($file) {
    $csv_data = [];

    if (is_object($file)) {
      ini_set('auto_detect_line_endings', TRUE);

      $filename = \Drupal::service('file_system')->realpath($file->getFileUri());
      $file_handler = fopen($filename, 'r');

      if ($file_handler !== FALSE) {
        $headings = [];
        while ($data = fgetcsv($file_handler)) {
          if (empty($headings)) {
            $headings = $data;
            continue;
          }
          $csv_data[] = array_combine($headings, $data);
        }
        fclose($file_handler);
      }
    }

    return $csv_data;
  }

  /**
   * Map CSV headings to condition entity fields.
   *
   * @return array
   *   Mapped fields array.
   */
  protected function mapCsvHeadingsToConditionEntityFields() {
    return [
      'event_number' => 'field_condition_event_number',
      'event_string' => 'field_condition_event_string',
      'pass_fail' => 'field_condition_event_result',
      'individual_comment' => 'field_event_individual_comment',
      'individual_recommendation' => 'field_individual_recommend',
      'analysis' => 'field_condition_event_element',
    ];
  }

  /**
   * Create condition entity from CSV data.
   *
   * @param array $mapped_fields
   *   Field mappings.
   * @param array $condition_entity_info
   *   Entity data from CSV.
   */
  protected function createConditionEntity($mapped_fields, $condition_entity_info) {
    $entity_data = \Drupal::entityTypeManager()->getStorage('condition_entity')->create([
      'type' => 'condition_entity',
      'uid' => \Drupal::currentUser()->id(),
      'created' => time(),
      'changed' => time(),
    ]);

    foreach ($condition_entity_info as $key => $value) {
      if (isset($mapped_fields[$key])) {
        $field_name = $mapped_fields[$key];
        $field_value = $this->getAppropriateFieldValue($value);
        $entity_data->set($field_name, $field_value);
      }
    }

    $entity_data->save();
  }

  /**
   * Get appropriate field value for entity field.
   *
   * @param string $value
   *   The value to be added to the entity field.
   *
   * @return array
   *   Drupal formatted field value.
   */
  protected function getAppropriateFieldValue($value) {
    $vocab = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadByProperties(['vid' => 'condition_event_results']);

    if (!empty($vocab)) {
      $vocabulary = reset($vocab);
      $taxonomy = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
        'name' => $value,
        'vid' => $vocabulary->id(),
      ]);

      if (!empty($taxonomy)) {
        $term = reset($taxonomy);
        return ['target_id' => $term->id()];
      } else {
        return ['value' => $value];
      }
    } else {
      $this->createVocabulariesAndTaxonomyTerms();
    }
    return [];
  }

  /**
   * Create vocabularies and taxonomy terms.
   */
  protected function createVocabulariesAndTaxonomyTerms() {
    _create_vocabularies_and_taxonomy_terms();
  }

  /**
   * Regenerate logic from condition entities.
   */
  protected function regenerateLogicFromConditionEntities() {
    _re_generate_logic_from_condition_entities();
  }

}


