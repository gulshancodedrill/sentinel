<?php

namespace Drupal\sentinel_portal_user_map\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\user\Entity\Role;

/**
 * Form for adding new users to the portal.
 */
class AddUserForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sentinel_portal_user_map_add_user_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['add_user_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Sub contractor email'),
      '#required' => TRUE,
    ];

    $form['add_user_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sub contractor name'),
      '#required' => TRUE,
    ];

    $form['add_user_organisation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sub contractor organisation'),
      '#required' => TRUE,
    ];

    $form['add_location'] = [
      '#type' => 'select',
      '#title' => $this->t('Location'),
      '#required' => TRUE,
      '#options' => sentinel_portal_user_map_selectfield(),
      '#empty_option' => '--Select Location--',
      '#description' => $this->t('Select the location you would like to grant the user access to'),
    ];

    // If the location has been passed (and looks like a number) then set the
    // default value.
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
    $email = $form_state->getValue('add_user_email');

    if (!\Drupal::service('email.validator')->isValid($email)) {
      $form_state->setErrorByName('add_user_email', $this->t('Please enter a valid email address.'));
    }

    // Check if this user doesn't already exist.
    $user = user_load_by_mail($email);

    if ($user) {
      $form_state->setErrorByName('add_user_email', $this->t('That email is already registered.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue('add_user_email');
    $name = $form_state->getValue('add_user_name');
    $organisation = $form_state->getValue('add_user_organisation');

    // Extract the location from the form.
    $new_location_tid = $form_state->getValue('add_location');

    $portal_role = Role::load('portal_user');

    $new_name = preg_replace('/@.*$/', '', $email);
    // Clean up the username.
    $new_name = sentinel_portal_user_map_cleanup_username($new_name);
    $new_name = sentinel_portal_user_map_unique_username($new_name);

    // Create new user account.
    $account = User::create();
    $account->setUsername($new_name);
    $account->setEmail($email);
    $account->setPassword('idsuyfkasjd' . time());
    $account->activate();
    $account->addRole('portal_user');
    $account->save();

    $client = sentinel_portal_entities_get_client_by_user($account->id());

    if ($client) {
      // Re-add actual form input values on client entity
      $client->name = $name;
      $client->company = $organisation;

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

      $client->save();
    }

    // Send an email.
    // @todo : add this back in.
    // _user_mail_notify('register_no_approval_required', $account);
    $this->messenger()->addWarning($this->t('Email not sent. Reminder that this needs to be turned back on.'));

    $this->messenger()->addStatus($this->t('User @email created.', ['@email' => $email]));

    // Redirect back to the usual page.
    $form_state->setRedirect('sentinel_portal_user_map.user_list');
  }

}


