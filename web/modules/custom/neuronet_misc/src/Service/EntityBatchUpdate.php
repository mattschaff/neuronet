<?php

namespace Drupal\neuronet_misc\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\neuronet_misc\EntityBatchUpdateCallbackInterface;

/**
 * Service that allows update hooks to access the batch system
 */
class EntityBatchUpdate {

  use StringTranslationTrait;

  /**
   * Database service
   *
   * @var Connection
   */
  protected $database;

  /**
   * Entity type manager service
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs EntityBatchUpdate object
   */
  public function __construct(Connection $Connection, EntityTypeManagerInterface $EntityTypeManager) {
    $this->database = $Connection;
    $this->entityTypeManager = $EntityTypeManager;
  }

  /**
   * Get EntityQuery object
   *
   * @param string $entity_type_id
   * @return QueryInterface
   */
  public function getEntityQuery($entity_type_id) {
    return $this->entityTypeManager
      ->getStorage($entity_type_id)
      ->getQuery()
      ->accessCheck(FALSE);
  }

  /**
   * Run batch update
   *
   * @param array $sandbox
   * @param QueryInterface $query
   * @param integer $limit
   * @param string|EntityBatchUpdateCallbackInterface $callback
   * @return string
   *   Returns final message
   */
  public function runUpdate(&$sandbox, $query, $limit = 20, $callback) {
    // Extract entity type ID & ID key.
    $entity_type_id = $query->getEntityTypeId();
    $id_key = $this->entityTypeManager->getDefinition($entity_type_id)->getKeys()['id'];
    // Initialize.
    if (!isset($sandbox['progress'])) {
      $count_query = $query;
      $sandbox['progress'] = 0;
      $sandbox['max'] = (int) $count_query->count()->execute();
      $sandbox['messages'] = [];
      $sandbox['already_run'] = [-1];
    }
    // Get new IDs.
    $result = $query
      ->condition($id_key, $sandbox['already_run'], 'NOT IN')
      ->range(0, $limit)
      ->execute();
    // Execute callback;
    foreach ($result as $id) {
      if ($entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id)) {
        if (is_string($callback)) {
          $callback($entity);
        }
        else {
          $callback->processUpdate($entity);
        }
      }
      unset($entity);
      $sandbox['already_run'][] = $id;
      $sandbox['progress']++;
    }
    $sandbox['#finished'] = $sandbox['progress'] >= $sandbox['max'] ? TRUE : $sandbox['progress'] / $sandbox['max'];
    // Handle messages.
    $sandbox_status = $sandbox;
    unset($sandbox_status['messages']);
    $sandbox['messages'][] = t('$sandbox=') . print_r($sandbox_status, TRUE);
    if ($sandbox['#finished']) {
      $final_message = '<ul><li>' . implode('</li><li>', $sandbox['messages']) . "</li></ul>";
      return $this->t('Entities processed: @message', [
        '@message' => $final_message,
      ]);
    }
  }
}