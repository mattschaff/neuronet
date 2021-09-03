<?php

namespace Drupal\neuronet_misc\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\neuronet_misc\EntityBatchUpdateCallbackInterface;

/**
 * Service to re-save a field on some fieldable entity,
 * probably to populate a new computed field
 * (see https://www.drupal.org/project/computed_field/issues/2988058)
 * 
 * - Used in neuronet_misc_update_8006()
 */
class RefreshFields implements EntityBatchUpdateCallbackInterface {

    /**
     * List of fields to update 
     * 
     * @var array $fields
     */
    private $fields;

    /**
     * Constructs RefreshField object
     */
    public function __construct() {
        $this->fields = [];
    }

    public function addField(string $field) {
        $this->fields[] = $field;
    }

    /**
     * Process update, required method by interface
     * 
     * @param FieldableEntityInterface $entity
     */
    public function processUpdate(EntityInterface $entity) {
        foreach ($this->fields as $field) {
            $this->refreshField($entity, $field);
        }
    }

    /**
     * If necessary, add a blank item for the field, then save it
     * 
     * @param FieldableEntityInterface $entity
     * @param string $field
     */
    private function refreshField(FieldableEntityInterface $entity, string $field) {
        $items = $entity->get($field);
        if (empty($items[0])) {
            $items->appendItem();
        }
        $items->preSave();
        $entity->save();
    }
}
