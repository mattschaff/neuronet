<?php

namespace Drupal\neuronet_misc\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use \Drupal\user\Entity\User;


/**
 * Action to send welcome email.
 *
 * @Action(
 *   id = "welcome_email",
 *   label = @Translation("Welcome Email"),
 *   type = "node"
 * )
 */
class WelcomeEmail extends ActionBase {

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
          $params['action_values']['subject'] = "Welcome to NGG Neuronet";
          $link = user_pass_reset_url($user);
          $params['action_values']['body'] = _neuronet_welcome_email_body($link);
          $to = $user->get('mail')->value;
          $mailManager->mail('neuronet_misc', 'welcome', $to, $langcode, $params);
          \Drupal::messenger()->addStatus('Welcome email sent to ' . $node->getTitle());
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

function _neuronet_welcome_email_body($link) {
  $body = "<p><strong>Hi NGG!</strong></p>

<p>We are pleased to announce the launch of NeuroNet, a directory of current students and - coming soon - alumni! </p>

<p>Looking for advice on rotations, picking elective courses, or a new apartment? Now you can check out what experiences and interests other NGG students have and contact them! We need your help, though! As we are not mind readers (sadly), we need you to log in and complete your profiles.</p>

<p>You can earn extra raffle tickets at the retreat by filling out your profile!</p>

<ul>
  <li>1 ticket for logging in and ...</li>
  <li>1 ticket for uploading a profile picture and completing 5 fields</li>
</ul>

<p>You can log into the website following the link below and using your Penn ID. </p>

<p>" . $link . "</p>

<p>Questions? Concerns? Contact neuronet.glia@gmail.com</p>

<p>Best regards! </p>
<p>Ethan Blackwood</p>
<p>The NeuroNet Committee (Matt Schaff, Ethan Blackwood, Catrina Hacker, Emily Pickup, and David Goldberg)</p>";
return $body;
}