<?php

namespace Drupal\neuronet_misc\Plugin\Action;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\State;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\UrlGeneratorTrait;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Action to send a custom email
 *
 * @Action(
 *   id = "send_custom_email",
 *   label = @Translation("Send custom email"),
 *   type = "node"
 * )
 */
class SendCustomEmail extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface, PluginFormInterface {

  /**
   * The tempstore object.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected $tempStore;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * MailManager service
   *
   * @var MailManager
   */
  protected $mailManager;

  /**
   * EntityTypeManager service
   *
   * @var EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Connection service
   *
   * @var Connection
   */
  protected $database;

  /**
   * Custom email options
   *
   * @var array
   */
  protected $customEmailOptions;

  /**
   * Constructs a new DeleteAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param AccountInterface $current_user
   *   Current user.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManager $entity_type_manager,
    PrivateTempStoreFactory $temp_store_factory,
    AccountInterface $current_user,
    State $State,
    MailManager $MailManager,
    Connection $Connection
    ) {
    $this->currentUser = $current_user;
    $this->tempStore = $temp_store_factory->get('send_custom_email__form_path');
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $State;
    $this->mailManager = $MailManager;
    $this->database = $Connection;
    $this->customEmailOptions = $this->getCustomEmails();
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
    ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('current_user'),
      $container->get('state'),
      $container->get('plugin.manager.mail'),
      $container->get('database')
    );
  }

  /**
   * Get custom email options
   *
   * - Numeric keys, name values.
   *
   * @return array
   */
  protected function getCustomEmails() {
    // Get email config from state.
    $config = $this->state->get('neuronet_misc.custom_emails');
    if (empty($config['emails_container']) && !is_null($this->context['redirect_url'])) {
      $this->tempStore->set(\Drupal::currentUser()->id(), $this->context['redirect_url']->getRouteName());
      $response = new RedirectResponse(Url::fromRoute('neuronet_misc.custom_emails')->toString());
      \Drupal::service('http_middleware.neuronet_misc_redirect')->setRedirectResponse($response);
    }
    return $config['emails_container'];
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Set select options.
    $options = [];
    foreach ($this->customEmailOptions as $email) {
      $options[] = $email['name'];
    }
    // Select custom email.
    $form['custom_email_selected'] = [
      '#type' => 'select',
      '#required' => true,
      '#title' => $this->t('Custom Emails to Send'),
      '#options' => $options,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['selected_email_key'] = $form_state->getValue('custom_email_selected');
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    // Get key of selected email.
    $key = (int) $this->configuration['selected_email_key'] + 1;
    $recipient_array = [];
    $recipient_names = '';
    $disabled_recipient_names = '';
    foreach ($entities as $entity) {
      // Get user associated with selected profile.
      $result = $this->entityTypeManager->getStorage('user')->loadByProperties([
        'field_profile' => $entity->id(),
      ]);
      /** @var User $user */
      if ($user = reset($result)) {
        // Don't send mail to people who have disabled notifications.
        if (!$user->get('field_general_emails')->value) {
          $disabled_recipient_names .= $entity->getTitle() . ' (' . $user->get('mail')->value . '), ';
          continue;
        }
        // Send mail.
        $langcode = $user->getPreferredLangcode();
        $params = [
          'user' => $user,
          'profile' => $entity,
          'subject' => $this->customEmailOptions[$key]['subject'],
          'body' => $this->customEmailOptions[$key]['email']['value'],
        ];
        $to = $user->get('mail')->value;
        $recipient_names .= $entity->getTitle() . ' (' . $to . '), ';
        $recipient_array[] = $entity->id();
        $this->mailManager->mail('neuronet_misc', 'custom', $to, $langcode, $params);
      }
    }
    // Set messages.
    if (!empty($recipient_names)) {
      // Get the existing data on sent emails.
      $tempstore = \Drupal::service('tempstore.private');
      $store = $tempstore->get('neuronet_misc');
      $sent_email_data = $store->get('sent_email_data');
      // Set new sent email.
      $store->set('sent_email_data', [
        'recipient_nids' => !empty($sent_email_data['recipient_nids']) ?
          array_merge($sent_email_data['recipient_nids'], $recipient_array) : $recipient_array,
        'recipient_names' => !empty($sent_email_data['recipient_names']) ?
          $sent_email_data['recipient_names'] . $recipient_names : $recipient_names,
        'disabled_recipient_names' => !empty($sent_email_data['disabled_recipient_names']) ?
          $sent_email_data['disabled_recipient_names'] . $disabled_recipient_names : $disabled_recipient_names,
        'email_sent' => $this->customEmailOptions[$key],
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    $this->executeMultiple([$object]);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return true;
  }

}