<?php

namespace Drupal\composer_authors\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\Role;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ComposerAuthorsForm.
 */
class ComposerAuthorsForm extends ConfigFormBase {

  /**
   * Drupal\user\UserAuthInterface definition.
   *
   * @var \Drupal\user\UserAuthInterface
   */
  protected $userAuth;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->userAuth = $container->get('user.auth');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'composer_authors.composerauthors',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'composer_authors_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('composer_authors.composerauthors');
    $composerJsonFile = '../composer.json';
    if (file_exists($composerJsonFile)) {
      $composerJson = Json::decode(file_get_contents($composerJsonFile));
      if (isset($composerJson['authors'])) {
        $authors = $composerJson['authors'];
        $composerEmails = array_unique(array_column($authors, 'email'));
        $composerEmails = array_merge( ['any' => 'Any'], array_combine($composerEmails, $composerEmails));
        $composerRoles = array_unique(array_column($authors, 'role'));
        $composerRoles = array_merge(['any' => 'Any'], array_combine($composerRoles, $composerRoles));
        // // Only needed if we want to have standardized keys.
        // $composerRoles = array_combine(array_map('strtolower', str_replace(' ', '_', $composerRoles)), $composerRoles);
        $composerNames = array_unique(array_column($authors, 'name'));
        $composerNames = array_combine($composerNames, $composerNames);
        // // Only needed if we want to have standardized keys.
        // $composerNames = array_combine(array_map('strtolower', str_replace(' ', '_', $composerNames)), $composerNames);
        $drupalRoleObjects = Role::loadMultiple();
        $drupalRoles = array_combine(array_keys($drupalRoleObjects), array_map(function($a){ return $a->label();}, $drupalRoleObjects));
      }
    }
    $savedEnvironments = (array) $this->config('composer_authors.composerauthors')->get('environments');
    $environments = [
      'all' => 'All environments',
    ];
    foreach ($savedEnvironments as $savedEnvironment) {
      $environments[$savedEnvironment['variable_value']] = $savedEnvironment['label'];
    }
    $form['add_environment'] = array(
      '#type' => 'details',
      '#title' => $this->t('Add environment'),
      '#open' => false,
      '#tree' => TRUE,
    );
    $form['add_environment']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Environment label'),
      '#description' => $this->t('The readable name of the environment.'),
    ];
    $form['add_environment']['variable_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Variable name'),
      '#description' => $this->t('The name of the environment variable that decides what environment you are on.'),
    ];
    $form['add_environment']['variable_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Variable value'),
      '#description' => $this->t('The value of the environment variable that decides what environment you are on.'),
    ];
    $form['add_environment']['add_environment'] = array(
      '#type' => 'submit',
      '#value' => 'Add environment',
      '#submit' => array([$this, 'addEnvironment']),
    );
    $form['add_mapping'] = array(
      '#type' => 'details',
      '#title' => $this->t('Add mapping'),
      '#tree' => true,
      '#open' => false,
    );
    $form['add_mapping']['composer_role'] = [
      '#type' => 'select',
      '#title' => $this->t('Composer role'),
      '#description' => $this->t('Select the Composer author role.'),
      '#options' => $composerRoles,
    ];
    $form['add_mapping']['composer_email'] = [
      '#type' => 'select',
      '#title' => $this->t('Composer email'),
      '#description' => $this->t('Select the Composer author email.'),
      '#options' => $composerEmails,
    ];
    $form['add_mapping']['drupal_role'] = [
      '#type' => 'select',
      '#title' => $this->t('Drupal role'),
      '#description' => $this->t('Select the Drupal role that you wish to map to the Composer role.'),
      '#options' => $drupalRoles,
    ];
    $form['add_mapping']['environment'] = [
      '#type' => 'select',
      '#title' => $this->t('Environment'),
      '#description' => $this->t('Select the environment for which you wish to map the Composer role.'),
      '#options' => $environments,
    ];
    $form['add_mapping']['add_mapping'] = array(
      '#type' => 'submit',
      '#value' => 'Add mapping',
      '#submit' => array([$this, 'addMapping']),
    );
    $header = [
     'composer_email' => t('Composer email'),
     'composer_role' => t('Composer role'),
     'drupal_role' => t('Drupal role'),
    ];
    $form['environments'] = array(
      '#type' => 'vertical_tabs',
      '#title' => t('Mappings per environment'),
      '#tree' => true,
    );
    $form['mappings']['#tree'] = TRUE;
    foreach ($environments as $key => $value) {
      $form['mappings'][$key] = array(
        '#type' => 'details',
        '#title' => $value,
        '#open' => TRUE,
        '#group' => 'environments'
      );
      $form['mappings'][$key]['mapping'] = [
       '#type' => 'tableselect',
       '#header' => $header,
       '#options' => $this->config('composer_authors.composerauthors')->get('mappings')[$key],
       '#empty' => t('No mappings found'),
      ];
      if (!empty($form['mappings'][$key]['mapping']['#options'])) {
        $form['mappings'][$key]['remove_mapping'] = array(
          '#type' => 'submit',
          '#value' => 'Remove mapping',
          '#submit' => array([$this, 'removeMapping']),
        );
      }
      else {
        $form['mappings'][$key]['remove_environment'] = array(
          '#type' => 'submit',
          '#value' => 'Remove environment',
          '#submit' => array([$this, 'removeEnvironment']),
        );
      }
    }
    unset($form['submit']);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function addEnvironment(array &$form, FormStateInterface $form_state) {
    $environment = $form_state->cleanValues()->getValue('add_environment');
    $environments = (array) $this->config('composer_authors.composerauthors')->get('environments');
    $environments[strtolower($environment['variable_value'])] = [
      'variable_name' => $environment['variable_name'],
      'variable_value' => $environment['variable_value'],
      'label' => $environment['label'],
    ];
    $this->config('composer_authors.composerauthors')
      ->set('environments', $environments)
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function addMapping(array &$form, FormStateInterface $form_state) {
    // parent::submitForm($form, $form_state);
    $mappings = (array) $this->config('composer_authors.composerauthors')->get('mappings');
    $mapping = $form_state->cleanValues()->getValue('add_mapping');
    $environment = $mapping['environment'];
    unset($mapping['environment']);
    $mappings[$environment][] = $mapping;
    array_unshift($mappings[$environment], "phoney");
    unset($mappings[$environment][0]);
    // drupal_set_message('<pre>' . print_r($mappings, true) . '</pre>');
    $this->config('composer_authors.composerauthors')
      ->set('mappings', $mappings)
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function removeMapping(array &$form, FormStateInterface $form_state) {
    // parent::submitForm($form, $form_state);
    $values = $form_state->cleanValues()->getValues()['mappings'];
    $mappings = (array) $this->config('composer_authors.composerauthors')->get('mappings');
    foreach ($mappings as $environment => $mapping) {
      $newMapping = array_diff_key($mappings[$environment], array_filter($values[$environment]['mapping']));
      $newMapping = array_combine(range(1, count($newMapping)), array_values($newMapping));
      $newMappings[$environment] = $newMapping;
    }
    $this->config('composer_authors.composerauthors')
      ->set('mappings', $newMappings)
      ->save();
  }

}
