<?php

namespace Drupal\neuronet_misc\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Add Job' Block.
 *
 * @Block(
 *   id = "add_job_block",
 *   admin_label = @Translation("Add job block"),
 *   category = @Translation("Job Block"),
 * )
 */
class AddJobBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Current Path service
   *
   * @var CurrentPathStack
   */
  protected $currentPath;

  /**
   * Alias manager service
   *
   * @var AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Route match service
   *
   * @var RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Current User service
   *
   * @var UserInterface
   */
  protected $currentUser;

  /**
   * Entity type manager service
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs AddJobBlock
   *
   * @param array $configuration
   * @param string $plugin_id
   * @param string $plugin_definition
   * @param CurrentPathStack $current_path
   * @param AliasManagerInterface $alias_manager
   * @param RouteMatchInterface $route_match
   * @param AccountInterface $current_user
   * @param EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition,
   CurrentPathStack $current_path, AliasManagerInterface $alias_manager, RouteMatchInterface $route_match,
   AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentPath = $current_path;
    $this->aliasManager = $alias_manager;
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $this->entityTypeManager->getStorage('user')->load($current_user->id());
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('path.current'),
      $container->get('path_alias.manager'),
      $container->get('current_route_match'),
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $current_path = $this->currentPath->getPath();
    $path_alias = $this->aliasManager->getAliasByPath($current_path);
    $url = Url::fromRoute('<front>')->toString() . 'node/add/job?destination=' . $path_alias;
    $node = $this->routeMatch->getParameter('node');
    if ($node) {
      $url .= '&profile=' . $node->id();
    }
    $link = '<a class="btn-primary btn" href="' . $url . '">Add Job to History</a>';
    return [
      '#markup' => $link,
    ];
  }

  /**
   * Indicates whether the block should be shown.
   *
   * Blocks with specific access checking should override this method rather
   * than access(), in order to avoid repeating the handling of the
   * $return_as_object argument.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user session for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   *
   * @see self::access()
   */
  protected function blockAccess(AccountInterface $account) {
    if (
      ($node = $this->routeMatch->getParameter('node')) &&
      (!$node->get('field_alumni')->value)
    ) {
      return AccessResult::forbidden();
    }
    // If an administrator is viewing the profile, allow access.
    if ($this->currentUser->hasRole('deputy_admin') || $this->currentUser->hasRole('administrator')) {
      return AccessResult::allowed();
    }
    // If the profile being viewed belongs to the current user, allow, if not, forbid.
    if (
      (!$this->currentUser->get('field_profile')->isEmpty()) &&
      ($node = $this->routeMatch->getParameter('node')) &&
      ($this->currentUser->get('field_profile')->first()->target_id == $node->id())
    ) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}