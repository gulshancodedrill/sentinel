<?php

namespace Drupal\sentinel_portal_sample;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;

/**
 * Shared GoAddress house number + postcode search for property addresses.
 */
trait GoAddressPropertySearchTrait {

  /**
   * Builds house number, postcode, and search button.
   *
   * @param array $parent
   *   Form element to attach children to (by reference).
   * @param string[] $parents
   *   #parents for nested values (e.g. ['job_details']).
   * @param string $wrapper_id
   *   AJAX wrapper HTML id.
   */
  protected function buildGoAddressPropertySearchElements(array &$parent, FormStateInterface $form_state, array $parents, string $wrapper_id): void {
    $parent['property_house_no'] = [
      '#type' => 'textfield',
      '#title' => $this->t('House number'),
      '#default_value' => $form_state->getValue(array_merge($parents, ['property_house_no'])) ?? '',
      '#parents' => array_merge($parents, ['property_house_no']),
      '#required' => FALSE,
      '#size' => 12,
      '#weight' => -14,
    ];

    $parent['property_postcode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postcode'),
      '#default_value' => $form_state->getValue(array_merge($parents, ['property_postcode'])) ?? '',
      '#parents' => array_merge($parents, ['property_postcode']),
      '#required' => FALSE,
      '#size' => 16,
      '#weight' => -13,
    ];

    $parent['property_address_search_btn'] = [
      '#type' => 'button',
      '#name' => 'property_address_search_btn',
      '#value' => $this->t('Search address'),
      '#executes_submit_callback' => TRUE,
      '#submit' => ['::submitGoAddressPropertySearch'],
      '#ajax' => [
        'callback' => '::ajaxGoAddressPropertySearch',
        'wrapper' => $wrapper_id,
        'progress' => ['type' => 'throbber'],
      ],
      '#limit_validation_errors' => [
        array_merge($parents, ['property_house_no']),
        array_merge($parents, ['property_postcode']),
      ],
      '#attributes' => ['class' => ['button', 'button--small']],
      '#weight' => -12,
    ];

    $message = $form_state->get('goaddress_property_search_message');
    if (is_string($message) && $message !== '') {
      $parent['goaddress_search_status'] = [
        '#markup' => '<p class="goaddress-search-status messages messages--warning">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>',
        '#weight' => -10,
      ];
    }
  }

  /**
   * Submit handler: call GoAddress API and store results in form state.
   */
  public function submitGoAddressPropertySearch(array &$form, FormStateInterface $form_state): void {
    $parents = $form_state->get('goaddress_property_parents') ?? [];
    if (!is_array($parents)) {
      $parents = [];
    }

    $house_no = trim((string) $form_state->getValue(array_merge($parents, ['property_house_no'])));
    $postcode = trim((string) $form_state->getValue(array_merge($parents, ['property_postcode'])));

    $form_state->set('goaddress_property_search_message', NULL);
    $form_state->set('goaddress_property_results', []);
    $form_state->set('goaddress_property_selected_id', NULL);
    $form_state->set('property_address_prefill', []);

    if ($house_no === '' || $postcode === '') {
      $form_state->set('goaddress_property_search_message', (string) $this->t('Please enter both house number and postcode.'));
      $form_state->setRebuild(TRUE);
      return;
    }

    $results = GoAddressClient::search(\Drupal::httpClient(), $house_no, $postcode);
    if ($results === []) {
      $form_state->set('goaddress_property_search_message', (string) $this->t('No addresses found for that house number and postcode.'));
    }
    else {
      $form_state->set('goaddress_property_results', $results);
      $id = (string) array_key_first($results);
      $form_state->set('goaddress_property_selected_id', $id);
      $this->applyGoAddressPropertySelection($form_state, $id, $parents);
      $this->onGoAddressPropertySelected($form_state);
    }

    $form_state->setRebuild(TRUE);
  }

  /**
   * Hook for forms to react when a GoAddress property has been selected.
   */
  protected function onGoAddressPropertySelected(FormStateInterface $form_state): void {
  }

  /**
   * AJAX callback after search.
   */
  public function ajaxGoAddressPropertySearch(array &$form, FormStateInterface $form_state) {
    if ($this->goAddressPropertySearchUsesInvokeOnlyAjax()) {
      $response = new AjaxResponse();
      $message = $form_state->get('goaddress_property_search_message');
      if (is_string($message) && $message !== '') {
        $response->addCommand(new MessageCommand($message, NULL, ['type' => 'warning']));
      }
      $prefill = $form_state->get('property_address_prefill');
      if (is_array($prefill) && trim((string) ($prefill['address_1'] ?? '')) !== '') {
        $this->addPortalGoAddressFieldCommands($response, $prefill);
        $this->syncPortalAddressFieldsUserInput($form_state, $prefill);
      }
      return $response;
    }

    return $this->goAddressPropertySearchAjaxElement($form, $form_state);
  }

  /**
   * Portal forms: only invoke address fields (do not replace the search wrapper).
   */
  protected function goAddressPropertySearchUsesInvokeOnlyAjax(): bool {
    return FALSE;
  }

  /**
   * Returns the form element replaced by the GoAddress search AJAX wrapper.
   */
  protected function goAddressPropertySearchAjaxElement(array &$form, FormStateInterface $form_state) {
    return NULL;
  }

  /**
   * Whether a GoAddress result has been chosen and mapped to prefill.
   */
  protected function goAddressPropertySearchHasSelection(FormStateInterface $form_state): bool {
    $prefill = $form_state->get('property_address_prefill');
    return is_array($prefill)
      && trim((string) ($prefill['address_1'] ?? '')) !== ''
      && trim((string) ($prefill['postcode'] ?? '')) !== '';
  }

  /**
   * Applies a GoAddress selection to property_address_prefill in form state.
   */
  protected function applyGoAddressPropertySelection(FormStateInterface $form_state, string $address_id, array $parents = []): void {
    $results = $form_state->get('goaddress_property_results');
    if (!is_array($results) || !isset($results[$address_id])) {
      return;
    }
    $fields = $results[$address_id]['fields'] ?? [];
    if (!is_array($fields)) {
      return;
    }
    unset($fields['goaddress_id']);
    $form_state->set('property_address_prefill', $fields);
    $form_state->set('goaddress_property_selected_id', $address_id);
  }

  /**
   * Keeps portal job_details address field user input in sync after AJAX search.
   */
  protected function syncPortalAddressFieldsUserInput(FormStateInterface $form_state, array $prefill): void {
    $input = $form_state->getUserInput();
    if (!isset($input['job_details']) || !is_array($input['job_details'])) {
      $input['job_details'] = [];
    }
    if (!isset($input['job_details']['address_fields']) || !is_array($input['job_details']['address_fields'])) {
      $input['job_details']['address_fields'] = [];
    }
    $input['job_details']['address_fields']['country'] = $prefill['country'] ?? 'GB';
    $input['job_details']['address_fields']['address_1'] = $prefill['address_1'] ?? '';
    $input['job_details']['address_fields']['town_city'] = $prefill['town_city'] ?? '';
    $input['job_details']['address_fields']['postcode'] = $prefill['postcode'] ?? '';
    $form_state->setUserInput($input);
  }

  /**
   * Populates portal sample address fields via AJAX invoke commands.
   */
  protected function addPortalGoAddressFieldCommands(AjaxResponse $response, array $fields): void {
    $country = strtoupper(trim((string) ($fields['country'] ?? ''))) ?: 'GB';
    $response->addCommand(new InvokeCommand('select[name="country"]', 'val', [$country]));
    $response->addCommand(new InvokeCommand('input[name="address_1"]', 'val', [$fields['address_1'] ?? '']));
    $response->addCommand(new InvokeCommand('input[name="town_city"]', 'val', [$fields['town_city'] ?? '']));
    $response->addCommand(new InvokeCommand('input[name="postcode"]', 'val', [$fields['postcode'] ?? '']));
    $response->addCommand(new InvokeCommand('.sample-address-fields', 'slideDown'));
    $response->addCommand(new InvokeCommand('.sample-address-add-button', 'slideUp'));
  }

}
