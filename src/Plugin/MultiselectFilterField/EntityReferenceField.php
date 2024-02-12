<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages\Plugin\MultiselectFilterField;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\MultiSelectFilterFieldPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the entity field type multiselect filter plugin.
 *
 * @MultiselectFilterField(
 *   id = "entity_reference",
 *   label = @Translation("Entity reference field"),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions",
 *     "skos_concept_entity_reference",
 *   },
 *   weight = 100
 * )
 */
class EntityReferenceField extends MultiSelectFilterFieldPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager, EntityRepositoryInterface $entity_repository, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager);
    $this->entityRepository = $entity_repository;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('entity.repository'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValues(): array {
    $field_definition = $this->getFacetFieldDefinition($this->configuration['facet'], $this->configuration['list_source']);
    if (empty($field_definition)) {
      return [];
    }

    $entity_storage = $this->entityTypeManager->getStorage($field_definition->getSetting('target_type'));
    $default_value = [];
    $filter_values = parent::getDefaultValues();
    foreach ($filter_values as $filter_value) {
      $default_value[] = $entity_storage->load($filter_value);
    }

    return $default_value;
  }

  /**
   * {@inheritdoc}
   */
  public function buildDefaultValueForm(array &$form = [], FormStateInterface $form_state = NULL, ListPresetFilter $preset_filter = NULL): array {
    $field_definition = $this->getFacetFieldDefinition($this->configuration['facet'], $this->configuration['list_source']);
    if (empty($field_definition)) {
      return [];
    }

    $selection_settings = [
      'match_operator' => 'CONTAINS',
      'match_limit' => 10,
    ] + $field_definition->getSettings()['handler_settings'];

    return [
      '#type' => 'entity_autocomplete',
      '#required_error' => t('@facet field is required.', ['@facet' => $this->configuration['facet']->label()]),
      '#maxlength' => 1024,
      '#target_type' => $field_definition->getSetting('target_type'),
      '#selection_handler' => $field_definition->getSetting('handler'),
      '#selection_settings' => $selection_settings,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareDefaultFilterValues(array $values, array $form, FormStateInterface $form_state): array {
    // For the entity autocomplete element, we need to extract the IDs from the
    // submitted #value element which contains also the title and the
    // parenthesis, etc.
    $prepared_values = [];
    foreach ($values as $value) {
      $prepared_values[] = EntityAutocomplete::extractEntityIdFromAutocompleteInput($value);
    }

    return $prepared_values;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValuesLabel(): string {
    $field_definition = $this->getFacetFieldDefinition($this->configuration['facet'], $this->configuration['list_source']);
    if (empty($field_definition)) {
      return '';
    }

    $filter_values = parent::getDefaultValues();
    $entity_storage = $this->entityTypeManager->getStorage($field_definition->getSetting('target_type'));
    $values = [];
    foreach ($filter_values as $filter_value) {
      if (!$filter_value) {
        continue;
      }
      $entity = $entity_storage->load($filter_value);
      if (!$entity) {
        continue;
      }
      $entity = $this->entityRepository->getTranslationFromContext($entity);

      $values[] = $entity->label();
    }

    return implode(', ', $values);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldValues(FieldItemListInterface $items): array {
    $values = $items->getValue();
    return array_column($values, 'target_id');
  }

}
