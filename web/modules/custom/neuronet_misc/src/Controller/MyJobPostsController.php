<?php

namespace Drupal\neuronet_misc\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\ProfileForm;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Drupal\views\Views;
use Drupal\Core\Url;

class MyJobPostsController extends ControllerBase {

  /**
   * Display the markup.
   *
   * @return array
   */
  public function content(NodeInterface $node) {
    $view = Views::getView('job_board');
    $view->setDisplay('my_job_posts');
    $connection = \Drupal::database();
    $query = $connection->query("SELECT entity_id FROM {user__field_profile} WHERE field_profile_target_id = :field_profile_target_id", [':field_profile_target_id' => $node->id()]);
    $result = $query->fetchAll();
    $node_alias = Url::fromRoute("neuronet_misc.my_job_posts", ["node" => $node->id()])->toString();
    return [
      'button' => [
        '#type' => 'markup',
        '#markup' => '<a class="btn btn-primary trigger" href="' . Url::fromRoute("node.add", ['node_type' => 'job_posting'])->toString() .
         '?destination=' . $node_alias . '" title="Edit" data-dialog-type="modal">'. $this->t('Post Job') . '</a>',
      ],
      'job_board' => [
        '#type' => 'view',
        '#name' => 'job_board',
        '#display_id' => 'my_job_posts',
        '#arguments' => [$result[0]->entity_id],
        '#embed' => TRUE,
      ],
    ];
  }

   /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   */
  public function access(AccountInterface $account, Route $route, NodeInterface $node) {
    // Check permissions and combine that with any custom access checking needed. Pass forward
    // parameters from the route and/or request as needed.
    //if node id is the same as the entity reference in the user
    $user = User::load($account->id());
    $access = false;
    if ($user->hasRole('administrator')) {
      $connection = \Drupal::database();
      $query = $connection->query("SELECT entity_id FROM {user__field_profile} WHERE field_profile_target_id = :field_profile_target_id", [':field_profile_target_id' => $node->id()]);
      $result = $query->fetchAll();
      if (!empty($result)) {
        $uid = $result[0]->entity_id;
        $user = User::load($uid);
        if (!$user->get('field_profile')->isEmpty() && $node->id() == $user->get('field_profile')->target_id) {
          $access = true;
        }
      }
    }
    elseif ($user->hasRole('alumni') &&
      !$user->get('field_profile')->isEmpty() &&
      $node->id() == $user->get('field_profile')->target_id) {
      $access = true;
    }
    return AccessResult::allowedIf($account->hasPermission('access content') && $access);
  }

}