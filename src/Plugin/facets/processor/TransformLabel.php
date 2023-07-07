<?php
namespace Drupal\oe_list_pages\Plugin\facets\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\facets\Exception\InvalidProcessorException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Transforms the results to show the label as a field value.
*
* @FacetsProcessor(
*  id = "transform_label",
*  label = @Translation("Transform entity ID label to field value."),
*  description = @Translation("Transform the label of a facet item to a field value."),
*  stages = {
*    "build" = 40
*  }
* )
*/

class TransformLabel extends ProcessorPluginBase implements BuildProcessorInterface, ContainerFactoryPluginInterface {

/**
* The language manager.
*
* @var \Drupal\Core\Language\LanguageManagerInterface
*/
protected $languageManager;

/**
* The entity type manager.
*
* @var \Drupal\Core\Entity\EntityTypeManagerInterface
*/
protected $entityTypeManager;

/**
* Constructs a new object.
*
* @param array $configuration
*   A configuration array containing information about the plugin instance.
* @param string $plugin_id
*   The plugin_id for the plugin instance.
* @param mixed $plugin_definition
*   The plugin implementation definition.
* @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
*   The language manager.
* @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
*   The entity type manager.
*/
public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $language_manager, EntityTypeManagerInterface $entity_type_manager) {
parent::__construct($configuration, $plugin_id, $plugin_definition);

$this->languageManager = $language_manager;
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
      $container->get('language_manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
  * {@inheritdoc}
  */
  public function build(FacetInterface $facet, array $results) {
//    kint($results);
    $language_interface = $this->languageManager->getCurrentLanguage();

    /** @var \Drupal\Core\TypedData\DataDefinitionInterface $data_definition */
    $data_definition = $facet->getDataDefinition();

    $processors = $facet->getProcessors();

    /* Get the referenced entity type */
    $entity_type = $data_definition
      ->getPropertyDefinition('entity')
      ->getTargetDefinition()
      ->getEntityTypeId();

    kint($entity_type);
    /** @var \Drupal\facets\Result\ResultInterface $result */
    $ids = []; //Get the ids of the referenced nodes
    if($entity_type == 'skos_concept') {
      foreach ($results as $result) {

        $entity_id = $result->getRawValue();
        $view_mode = 'teaser';
        $entity = $this->entityTypeManager->getStorage($entity_type);
        kint(
          get_class($entity),
          $entity_id);
        $view_builder = $this->entityTypeManager->getViewBuilder($entity_type);
        $pre_render = $view_builder->view($entity, $view_mode);
        $render_output = render($pre_render);
        kint($render_output);
//
//        $ids[] = $result->getRawValue();
//      }
//    } else {
//      foreach ($results as $result) {
//        $ids[] = $result->getRawValue();
      }
    }
//
//
//
//    // Load all indexed entities of this type.
//    $entities = $this->entityTypeManager
//      ->getStorage($entity_type)
//      ->loadMultiple($ids);
//    // Loop over all results.
//    foreach ($results as $i => $result) {
//      if (!isset($entities[$ids[$i]])) {
//        unset($results[$i]);
//        continue;
//      }
//
//      /** @var \Drupal\Core\Entity\ContentEntityBase $entity */
//      $entity = $entities[$ids[$i]];
//
//      // Check for a translation of the entity and load that instead if one's found.
//      if ($entity instanceof TranslatableInterface && $entity->hasTranslation($language_interface->getId())) {
//        $entity = $entity->getTranslation($language_interface->getId());
//      }
//
//      // Overwrite the result's display value.
//      $results[$i]->setDisplayValue($entity->get($field_name)->getString());
//    }

    // Return the results with the new display values.
    return $results;
  }


}
