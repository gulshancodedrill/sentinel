<?php

namespace Drupal\sentinel_portal_user_map\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form for updating existing users' location access.
 */
class UpdateUserForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_portal_user_map_update_user_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['help_text'] = [
      '#markup' => $this->t('Search for users using the autocomplete text field. Then select the areas you wish to grant them access to.') . '<br>',
      '#weight' => -50,
    ];

    $form['add_user'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User'),
      '#required' => TRUE,
      '#description' => $this->t('Begin to type the users name'),
      '#autocomplete_route_name' => 'sentinel_portal_user_map.autocomplete',
    ];

    $form['add_location'] = [
      '#type' => 'select',
      '#title' => $this->t('Location'),
      '#required' => TRUE,
      '#options' => sentinel_portal_user_map_selectfield(),
      '#empty_option' => '--Select Location--',
      '#description' => $this->t('Select the location you would like to grant the user access to'),
    ];

    // If the location has been passed (and looks like a number) then set the default value.
    $request = $this->getRequest();
    if ($request->query->has('location') && is_numeric($request->query->get('location'))) {
      $form['add_location']['#default_value'] = $request->query->get('location');
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#attributes' => ['class' => ['btn-primary']],
      '#weight' => 50,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getValue('add_user')) {
      // No user information added.
      $form_state->setErrorByName('add_user', $this->t('You must enter a user'));
      return;
    }

    if (!$form_state->getValue('add_location')) {
      // No location selected.
      $form_state->setErrorByName('add_location', $this->t('You must select a location'));
      return;
    }

    // Explode the add_user field in order to get the email, uid, cid.
    // The user will be in the form of
    // <email address> | <uid> | <cid>
    $user_data = explode(' | ', $form_state->getValue('add_user'));

    if (count($user_data) != 3) {
      // The array
      $form_state->setErrorByName('add_user', $this->t('You must enter a user'));
      return;
    }

    if ((isset($user_data[1]) && !is_numeric($user_data[1])) || (isset($user_data[2]) && !is_numeric($user_data[2]))) {
      // One of the values we are expecting to be numeric isn't numeric.
      $form_state->setErrorByName('add_user', $this->t('You must enter a user'));
      return;
    }

    // Check if the user exists in the users table by matching their email,
    // user id and client id.
    $query_user = \Drupal::database()->select('users_field_data', 'u');
    $query_user->fields('u', ['uid', 'mail']);
    $query_user->condition('mail', $user_data[0], '=');
    $query_user->condition('u.uid', $user_data[1], '=');
    $query_user->join('sentinel_client', 'c', 'c.uid = u.uid');
    $query_user->condition('c.cid', $user_data[2], '=');
    $result_user = $query_user->execute()->fetchAll();

    if (!$result_user) {
      // User checks did not work out.
      $form_state->setErrorByName('add_user', $this->t('The user "@user" does not exist', ['@user' => $form_state->getValue('add_user')]));
      return;
    }

    // Check if the entry already exists in field_data_field_user_cohorts.
    $query_fdfuc = \Drupal::database()->select('field_data_field_user_cohorts', 'uc');
    $query_fdfuc->fields('uc', ['entity_id', 'field_user_cohorts_tid']);
    $query_fdfuc->condition('uc.field_user_cohorts_tid', $form_state->getValue('add_location'), '=');
    $query_fdfuc->condition('entity_id', $user_data[2], '=');

    $result_fdfuc = $query_fdfuc->execute()->fetchAll();

    if ($result_fdfuc) {
      $form_state->setErrorByName('add_user', $this->t('The user "@user" is already related to that location. No need to add.', ['@user' => $form_state->getValue('add_user')]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // The user will be in the form of
    // <email address> | <uid> | <cid>
    $user_data = explode(' | ', $form_state->getValue('add_user'));

    // Get the cid from user array.
    $cid = $user_data[2];

    // Extract the location from the form.
    $new_location_tid = $form_state->getValue('add_location');

    // Load the user and extract the location list.
    $client = sentinel_portal_entities_get_client_by_user($user_data[1]);
    
    if ($client) {
      $location_list = $client->get('field_user_cohorts')->getValue();

      // Extract all existing tids from the client object.
      $user_location_tids = [];
      foreach ($location_list as $existing_location_tid) {
        $user_location_tids[$existing_location_tid['target_id']] = $existing_location_tid['target_id'];
      }

      // Add the new one.
      $user_location_tids[$new_location_tid] = $new_location_tid;

      // Add the field data back into the client entity.
      $client->set('field_user_cohorts', []);
      foreach ($user_location_tids as $location_tid) {
        $client->get('field_user_cohorts')->appendItem(['target_id' => $location_tid]);
      }

      // Save it!
      $client->save();
    }

    // Send a response message to the user.
    $this->messenger()->addStatus($this->t('Success: User added to location'));

    // Redirect the user (back) to the user-list.
    $form_state->setRedirect('sentinel_portal_user_map.user_list');
  }

}


