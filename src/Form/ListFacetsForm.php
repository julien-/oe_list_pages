<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages\Form;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\facets\Exception\InvalidQueryTypeException;
use Drupal\facets\FacetInterface;
use Drupal\facets\Utility\FacetsUrlGenerator;
use Drupal\oe_list_pages\ListFacetManagerWrapper;
use Drupal\oe_list_pages\ListSourceInterface;
use Drupal\oe_list_pages\Plugin\facets\widget\ListPagesWidgetInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposes the facets of a given search for a list page.
 */
class ListFacetsForm extends FormBase {

  /**
   * The facets manager.
   *
   * @var \Drupal\oe_list_pages\ListFacetManagerWrapper
   */
  protected $facetsManager;

  /**
   * The facets url generator.
   *
   * @var \Drupal\facets\Utility\FacetsUrlGenerator
   */
  protected $facetsUrlGenerator;

  /**
   * Constructs an instance of ListFacetsForm.
   *
   * @param \Drupal\oe_list_pages\ListFacetManagerWrapper $facets_manager
   *   The facets manager.
   * @param \Drupal\facets\Utility\FacetsUrlGenerator $facets_url_generator
   *   The facets url generator.
   */
  public function __construct(ListFacetManagerWrapper $facets_manager, FacetsUrlGenerator $facets_url_generator) {
    $this->facetsManager = $facets_manager;
    $this->facetsUrlGenerator = $facets_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_list_pages.list_facet_manager_wrapper'),
      $container->get('facets.utility.url_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_list_pages_facets_form';
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ListSourceInterface $list_source = NULL, array $ignored_filters = []) {
    if (!$list_source) {
      return [];
    }

    $cache = new CacheableMetadata();
    $cache->addCacheTags(['config:facets_facet_list']);

    $source_id = $list_source->getSearchId();

    /** @var \Drupal\facets\FacetInterface[] $facets */
    $facets = $this->facetsManager->getFacetsByFacetSourceId($source_id, $list_source->getIndex());
    if (!$facets) {
      $cache->applyTo($form);
      return $form;
    }

    // Sort facets by weight.
    uasort($facets, function (FacetInterface $a, FacetInterface $b) {
      if ($a->getWeight() == $b->getWeight()) {
        return 0;
      }
      return ($a->getWeight() < $b->getWeight()) ? -1 : 1;
    });

    foreach ($facets as $facet) {
      try {
        // Check that we are able to determine the query type and not crash
        // the application if we cannot. Just skip it.
        $facet->getQueryType();
      }
      catch (InvalidQueryTypeException $exception) {
        continue;
      }

      $widget = $facet->getWidgetInstance();

      // If facet id should be ignored due to query configuration.
      if (in_array($facet->id(), $ignored_filters)) {
        continue;
      }

      if ($widget instanceof ListPagesWidgetInterface) {
        $form['facets'][$facet->id()] = $this->facetsManager->getFacetManager()->build($facet);
        $cache->addCacheTags($facet->getCacheTags());
      }
    }

    if (!isset($form['facets'])) {
      $cache->applyTo($form);
      return $form;
    }

    $form_state->set('source_id', $source_id);

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#op' => 'submit',
    ];

    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear filters'),
      '#op' => 'reset',
    ];

    $cache->applyTo($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $current_url = Url::fromRoute('<current>');
    $source_id = $form_state->get('source_id');
    $facets = $this->facetsManager->getFacetsByFacetSourceId($source_id);

    // Facets that expose a configured default value (e.g. an upcoming/past
    // status default). When such a facet ends up without a value we must mark
    // it as explicitly cleared in the URL, otherwise its default would be
    // silently re-applied on the resulting clean URL.
    $default_status_facets = [];
    foreach ($facets as $facet) {
      if ($this->facetHasDefaultStatusConfigured($facet)) {
        $default_status_facets[$facet->id()] = $facet->id();
      }
    }

    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element['#op'] === 'reset') {
      // Clearing all filters must also override any exposed default value, so
      // the default is not re-applied on the resulting clean URL.
      if ($default_status_facets) {
        $current_url->setOption('query', ['cleared' => array_values($default_status_facets)]);
      }
      $form_state->setRedirectUrl($current_url);
      return;
    }

    $active_filters = [];
    /** @var \Drupal\facets\FacetInterface $facet */
    foreach ($facets as $facet) {
      $widget = $facet->getWidgetInstance();
      if ($widget instanceof ListPagesWidgetInterface) {
        $active_filters[$facet->id()] = $widget->prepareValueForUrl($facet, $form, $form_state);
      }
    }

    $active_filters = array_filter($active_filters);

    // A default-status facet submitted without a value has been explicitly
    // cleared by the user and must be flagged as such.
    $cleared = [];
    foreach ($default_status_facets as $facet_id) {
      if (empty($active_filters[$facet_id])) {
        $cleared[] = $facet_id;
      }
    }

    if ($active_filters) {
      $url = $this->facetsUrlGenerator->getUrl($active_filters, FALSE);
      if ($cleared) {
        $query = $url->getOption('query') ?? [];
        $query['cleared'] = $cleared;
        $url->setOption('query', $query);
      }
      $form_state->setRedirectUrl($url);
      return;
    }

    // If there are no active filters, we redirect to the current URL, flagging
    // any exposed default as cleared so it is not re-applied.
    if ($cleared) {
      $current_url->setOption('query', ['cleared' => $cleared]);
    }
    $form_state->setRedirectUrl($current_url);
  }

  /**
   * Checks whether a facet exposes a configured default value.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   *
   * @return bool
   *   TRUE if the facet has a non-empty default status configured.
   */
  protected function facetHasDefaultStatusConfigured(FacetInterface $facet): bool {
    foreach ($facet->getProcessorConfigs() as $config) {
      if (!empty($config['settings']['default_status'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
