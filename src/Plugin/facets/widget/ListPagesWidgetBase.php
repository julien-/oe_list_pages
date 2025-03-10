<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Widget\WidgetPluginBase;
use Drupal\oe_list_pages\FacetManipulationTrait;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListSourceInterface;

/**
 * Base class for facet widgets.
 */
class ListPagesWidgetBase extends WidgetPluginBase implements ListPagesWidgetInterface {

  use FacetManipulationTrait;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The facet manager wrapper.
   *
   * @var \Drupal\oe_list_pages\ListFacetManagerWrapper
   */
  protected $facetManager;

  /**
   * {@inheritdoc}
   */
  public function prepareValueForUrl(FacetInterface $facet, array &$form, FormStateInterface $form_state): array {
    $value = $form_state->getValue($facet->id());
    if (!$value) {
      return [];
    }

    return is_array($value) ? array_values($value) : [$value];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareDefaultFilterValue(FacetInterface $facet, array $form, FormStateInterface $form_state): array {
    return [
      'operator' => ListPresetFilter::OR_OPERATOR,
      'values' => $this->prepareValueForUrl($facet, $form, $form_state),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValuesLabel(FacetInterface $facet, ListSourceInterface $list_source, ListPresetFilter $filter): string {
    return $this->getDefaultFilterValuesLabel($facet, $filter);
  }

  /**
   * {@inheritdoc}
   */
  public function buildDefaultValueForm(array $form, FormStateInterface $form_state, FacetInterface $facet, ?ListPresetFilter $preset_filter = NULL): array {
    return $this->build($facet);
  }

  /**
   * {@inheritdoc}
   */
  public function getValueFromActiveFilters(FacetInterface $facet, string $key): ?string {
    $active_filters = $facet->getActiveItems();
    return $active_filters[$key] ?? NULL;
  }

}
