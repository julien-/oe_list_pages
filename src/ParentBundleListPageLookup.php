<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Resolves the list page node that lists a given content bundle.
 *
 * Returns the first published "oe_list_page" node whose entity-meta source
 * equals "<entity_type>:<bundle>", translated to the current language when
 * available. The lookup is done via direct SQL on the entity_meta tables to
 * avoid EMR computed-field hydration quirks during bulk node loads.
 */
class ParentBundleListPageLookup {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * Constructs a ParentBundleListPageLookup.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->database = $database;
  }

  /**
   * Returns the list page node for the given entity type / bundle.
   *
   * @param string $bundle
   *   The bundle machine name (for instance "article").
   * @param string $entity_type_id
   *   The entity type the bundle belongs to. Defaults to "node".
   *
   * @return \Drupal\node\NodeInterface|null
   *   The list page node translated to the current language when available,
   *   or NULL if no matching list page exists.
   */
  public function find(string $bundle, string $entity_type_id = 'node'): ?NodeInterface {
    $expected_source = $entity_type_id . ':' . $bundle;

    // Find the published list page node whose source field matches. In
    // oe_list_pages 2.x the source is stored directly on the node, so we read
    // the node field table instead of the (now removed) entity meta tables.
    $nid = $this->database->query(
      'SELECT s.entity_id AS nid
       FROM {node__oe_list_page_source} s
       INNER JOIN {node_field_data} n
         ON n.nid = s.entity_id AND n.status = 1
       WHERE s.oe_list_page_source_value = :source
       LIMIT 1',
      [':source' => $expected_source]
    )->fetchField();

    if (!$nid) {
      return NULL;
    }

    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node) {
      return NULL;
    }

    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    if ($node->hasTranslation($langcode)) {
      $node = $node->getTranslation($langcode);
    }
    return $node;
  }

}
