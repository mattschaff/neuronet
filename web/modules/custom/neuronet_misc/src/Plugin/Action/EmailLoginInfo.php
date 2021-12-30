<?php

namespace Drupal\neuronet_misc\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;


/**
 * Action to email login information.
 *
 * @Action(
 *   id = "email_login_info",
 *   label = @Translation("Email login info"),
 *   type = "node"
 * )
 */
class EmailLoginInfo extends ActionBase {

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
          $params['action_values']['subject'] = "Login Info for Neuronet";
          $link = user_pass_reset_url($user);
          $params['action_values']['body'] = _neuronet_alumnus_email_body($link);
          $to = $user->get('mail')->value;
          $mailManager->mail('neuronet_misc', 'login', $to, $langcode, $params);
          \Drupal::messenger()->addStatus('Login link email sent to ' . $node->getTitle());
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

function _neuronet_alumnus_email_body($link) {
  $body = "<p>Hi NGG alum!</p>
<p>NGG and GLIA are proud to announce the creation of NGG's very own online current student and alumni database, NeuroNet, and you're invited to be one of the first alumni users! At this point the site is pretty much done and just needs those finishing touches. If you could log-in to the site here <strong>(NOTE: You need a separate password from your PennKey for NeuroNet!)</strong>: </p> 
<p>" . $link . "</p> 
<p>Log in, update your profile, and take a look around the site and then let us know any questions/comments/concerns you have, we'd really appreciate it!</p>

<p>In particular we would like to know:</p>
<ul>
  <li>Do you find the site easy to log-in to?</li>
  <li>Are you content with the information displayed on the database?</li>
  <li>Would you use this database to look up other alumni and future NGG students?</li>
  <li>If the database grew to include a discussion board where you could place job postings or inquiries, would you use it?</li>
  <li>Are there any other functions or information you would like to see on this site?</li>
</ul>
<p>Please log-in to NeuroNet and let us know what you think! We're looking forward to your feedback!</p>

<p>Thanks for your time and efforts with this!</p>

<p>Ethan Blackwood</p>
<p>The NeuroNet Committee (Matt Schaff, Ethan Blackwood, Catrina Hacker, Emily Pickup, and David Goldberg)</p>";
return $body;
}