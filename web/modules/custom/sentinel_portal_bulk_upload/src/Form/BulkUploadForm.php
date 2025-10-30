<?php

namespace Drupal\sentinel_portal_bulk_upload\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Form for bulk uploading samples via CSV.
 */
class BulkUploadForm extends FormBase {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new BulkUploadForm.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(MessengerInterface $messenger, ModuleHandlerInterface $module_handler, AccountProxyInterface $current_user) {
    $this->messenger = $messenger;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('module_handler'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_portal_bulk_upload_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $module_path = $this->moduleHandler->getModule('sentinel_portal_bulk_upload')->getPath();
    
    $template_url = '/' . $module_path . '/includes/template.csv';
    $template_link = '<a href="' . $template_url . '" download>' . $this->t('Template') . '</a>';
    
    $guide_url = '/' . $module_path . '/includes/bulk_uploader_guide.pdf';
    $guide_link = '<a href="' . $guide_url . '" download>' . $this->t('Download guide') . '</a>';

    $form['wrapper_start'] = [
      '#markup' => '<div class="landing-well clearfix"><h2><i class="fa fa-upload"></i> &nbsp; ' . $this->t('Submit multiple packs at once') . '</h2><p>' . $this->t('Using our !template_link, upload the information for multiple SystemCheck packs at once. Guidance on correct use of the template can be found below.', ['!template_link' => $template_link]) . '</p>',
    ];

    $form['csv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('Please select your CSV file'),
      '#description' => $this->t('Please select your populated copy of the Sentinel template and then click \'Upload and Process\'.'),
    ];

    $form['header_line_present'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('First row is header'),
      '#description' => $this->t('Untick this box to treat the first row in your CSV file as data. Even if you have removed the header row from the Sentinel template, the columns must remain in the stipulated order for packs to be processed successfully.'),
      '#default_value' => 1,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload and Process'),
      '#attributes' => ['class' => ['btn', 'btn-primary']],
    ];

    $form['wrapper_end'] = [
      '#markup' => '</div>',
    ];

    $help_header = '<h3>' . $this->t('Advice on the use of this template') . '</h3><br>';
    
    // Try to get the notices route if it exists
    try {
      $notices_url = Url::fromRoute('sentinel_portal_notice.list')->toString();
      $notices_link = '<a href="' . $notices_url . '">' . $this->t('Notices') . '</a>';
    }
    catch (\Exception $e) {
      $notices_link = $this->t('Notices');
    }
    
    $help_text = '<p>' . $this->t('Any issues found during the import process will be available to view in the') . ' ' . $notices_link . ' ' . $this->t('area.') . '</p>';

    $form['help_text'] = [
      '#markup' => $help_header . $guide_link . '<br>' . $template_link . $help_text,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $validators = ['file_validate_extensions' => ['csv']];
    
    $file = file_save_upload('csv_file', $validators, FALSE, 0);
    
    if ($file) {
      $form_state->setValue('uploaded_file', $file);
    }
    else {
      $form_state->setErrorByName('csv_file', $this->t('File upload failed.'));
    }

    // Check if the user has a client ID
    $user = $this->currentUser;
    if (function_exists('sentinel_portal_entities_get_client_by_user')) {
      $client = sentinel_portal_entities_get_client_by_user($user);
      if ($client == FALSE) {
        $form_state->setErrorByName('form', $this->t('Your client ID was not found, please contact the site administrator.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = $this->currentUser;
    $file = $form_state->getValue('uploaded_file');

    if (!$file) {
      $this->messenger->addError($this->t('No file was uploaded.'));
      return;
    }

    // The destination of the file should be private://csv_uploads/username/file.csv
    $destination = 'private://csv_uploads/' . $user->getAccountName();

    // Prepare the directory
    \Drupal::service('file_system')->prepareDirectory($destination, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);

    // Move the file
    $file = file_move($file, $destination . '/' . $file->getFilename(), \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE);

    if (!$file) {
      // The file wasn't moved into the correct destination. Kill the process.
      $this->messenger->addError($this->t('The file could not be uploaded. Please contact the site administrator if the problem persists.'));
      return;
    }

    // Create a notice to tell them that their file is being processed.
    $message = $this->t('The file @filename has been uploaded and is being processed.', ['@filename' => $file->getFilename()]);
    
    if (function_exists('_sentinel_portal_entities_create_notice')) {
      _sentinel_portal_entities_create_notice($user, $this->t('CSV file import'), $message);
    }

    // Get the client
    if (function_exists('sentinel_portal_entities_get_client_by_user')) {
      $client = sentinel_portal_entities_get_client_by_user($user);
    }
    else {
      $this->messenger->addError($this->t('Client information could not be retrieved.'));
      return;
    }

    $header_line = $form_state->getValue('header_line_present') == 1 ? TRUE : FALSE;

    // Set up the batch
    $batch = [
      'operations' => [
        [
          '\Drupal\sentinel_portal_bulk_upload\BulkUploadBatch::processFile',
          [$file, $header_line, $client]
        ]
      ],
      'finished' => '\Drupal\sentinel_portal_bulk_upload\BulkUploadBatch::finished',
      'title' => $this->t('Processing'),
      'init_message' => $this->t('Initialising file processing.'),
      'progress_message' => $this->t('Processing file.'),
      'error_message' => $this->t('An error was encountered processing the file.'),
    ];
    
    batch_set($batch);
  }

}

