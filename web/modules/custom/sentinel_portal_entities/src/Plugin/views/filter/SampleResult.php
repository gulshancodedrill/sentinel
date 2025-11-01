<?php

namespace Drupal\sentinel_portal_entities\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Provides a Pass/Fail/Pending filter for sentinel samples.
 */
#[ViewsFilter("sentinel_sample_result")]
class SampleResult extends InOperator {

  /**
   * {@inheritdoc}
   */
  protected $valueFormType = 'select';

  /**
   * {@inheritdoc}
   */
  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueOptions = [
        'pending' => $this->t('Pending'),
        '1' => $this->t('Pass'),
        '0' => $this->t('Fail'),
      ];
    }

    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);

    if (isset($form['value'])) {
      $form['value']['#type'] = 'select';
      $form['value']['#multiple'] = FALSE;
      $form['value']['#options'] = $this->getValueOptions();

      $default = $this->value;
      if (is_array($default)) {
        $default = reset($default);
      }
      $form['value']['#default_value'] = $default;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $value = $this->value;
    if (is_array($value)) {
      $value = reset($value);
    }

    if ($value === NULL || $value === '' || $value === 'All') {
      return;
    }

    if ($value === 'pending') {
      $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", NULL, 'IS NULL');
      return;
    }

    $this->query->addWhere($this->options['group'], "$this->tableAlias.$this->realField", $value, '=');
  }

}


