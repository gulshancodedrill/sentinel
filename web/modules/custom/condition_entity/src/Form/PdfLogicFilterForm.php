<?php

namespace Drupal\condition_entity\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Filter form for the PDF logic listing.
 */
class PdfLogicFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'condition_entity_pdf_logic_filter';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $request = \Drupal::request();
    $event_number = $request->query->get('event_number', '');

    $event_element = $request->query->get('event_element', '');
    $event_string = $request->query->get('event_string', '');
    $event_string_and = $request->query->get('event_string_and', '');

    $form['#method'] = 'get';
    $form['#action'] = Url::fromRoute('condition_entity.pdf_logic')->toString();
    $form['#attributes']['class'][] = 'pdf-logic-filter-form';
    $form['#token'] = FALSE;

    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pdf-logic-filter-grid']],
    ];

    $form['filters']['event_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event Number'),
      '#default_value' => $event_number,
      '#size' => 20,
    ];

    $form['filters']['event_element'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event Element Search'),
      '#default_value' => $event_element,
      '#size' => 30,
    ];

    $form['filters']['event_string'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event String Search'),
      '#default_value' => $event_string,
      '#size' => 40,
    ];

    $form['filters']['event_string_and'] = [
      '#type' => 'textfield',
      '#title' => $this->t('And'),
      '#default_value' => $event_string_and,
      '#size' => 40,
      '#attributes' => ['class' => ['pdf-logic-filter-and']],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#button_type' => 'primary',
    ];

    $form['actions']['reset'] = [
      '#type' => 'link',
      '#title' => $this->t('Reset'),
      '#url' => Url::fromRoute('condition_entity.pdf_logic'),
      '#attributes' => [
        'class' => ['button', 'button--secondary'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $event_number = trim((string) $form_state->getValue('event_number'));
    $event_element = trim((string) $form_state->getValue('event_element'));
    $event_string = trim((string) $form_state->getValue('event_string'));
    $event_string_and = trim((string) $form_state->getValue('event_string_and'));
    $query = [];
    if ($event_number !== '') {
      $query['event_number'] = $event_number;
    }
    if ($event_element !== '') {
      $query['event_element'] = $event_element;
    }
    if ($event_string !== '') {
      $query['event_string'] = $event_string;
    }
    if ($event_string_and !== '') {
      $query['event_string_and'] = $event_string_and;
    }
    $form_state->setRedirect('condition_entity.pdf_logic', [], ['query' => $query]);
  }

}


