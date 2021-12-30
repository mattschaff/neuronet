<?php

namespace Drupal\neuronet_misc\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use \Drupal\user\Entity\User;


/**
 * Push term in front.
 *
 * @Action(
 *   id = "pass_reset",
 *   label = @Translation("Email password reset link"),
 *   type = "node"
 * )
 */
class PassReset extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
   /* if ($entity->hasField('field_push')) {
      $entity->field_push->value = 1;
      $entity->save();
    }*/
    $node = $entity;
     $connection = \Drupal::database();
    $query = $connection->query("SELECT entity_id FROM {user__field_profile} WHERE field_profile_target_id = :field_profile_target_id", [':field_profile_target_id' => $node->id()]);
    $result = $query->fetchAll();
      if (!empty($result)) {
        $uid = $result[0]->entity_id;
        $user = User::load($uid);
        if (is_object($user)){
          $mailManager = \Drupal::service('plugin.manager.mail');
          $langcode = $user->getPreferredLangcode();
          $params['action_values']['subject'] = "Password Reset Link for Neuronet";
          $link = user_pass_reset_url($user);
          $params['action_values']['body'] = _neuronet_pass_reset_body($link);
          $to = $user->get('mail')->value;
          $mailManager->mail('neuronet_misc', 'passreset', $to, $langcode, $params);
          \Drupal::messenger()->addStatus('Password reset link sent to ' . $node->getTitle());
        }
      }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    //$result = $object->access('update', $account, TRUE)
      //->andIf($object->field_push->access('edit', $account, TRUE));

    return true;
  }

}

function _neuronet_pass_reset_body($link) {
  $body = "<p>Hi NGG student/alum!</p>
<p>Reset your password on NeuroNet here:</p> 
<p>" . $link . "</p> 

<p>Sincerely,</p>

<p>Ethan Blackwood</p>
<p>The NeuroNet Committee (Matt Schaff, Ethan Blackwood, Catrina Hacker, Emily Pickup, and David Goldberg)</p>";
return $body;
}