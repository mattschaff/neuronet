<?php

namespace Drupal\neuronet_misc\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Service to handle sending email notifications of job postings
 */
class JobPostingEmails {

  use StringTranslationTrait;

  /**
   * The tempstore object.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected $tempStore;

  /**
   * Entity type manager service
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs JobPostingEmails object.
   *
   * @var PrivateTempStoreFactory $tempStoreFactory
   * @var EntityTypeManagerInterface $EntityTypeManager
   */
  public function __construct(PrivateTempStoreFactory $tempStoreFactory, EntityTypeManagerInterface $EntityTypeManager) {
    $this->tempStore = $tempStoreFactory->get('neuronet_misc');
    $this->entityTypeManager = $EntityTypeManager;
  }

  /**
   * Gets NIDs of profiles that should receive emails from temp store
   *
   * @return array
   */
  public function getRecipients() {
    return $this->tempStore->get('job_posting_recipients');
  }

  /**
   * Sets NIDs of recipient profiles based on job posting node to temp store
   */
  public function selectRecipients() {
    $node = $this->tempStore->get('job_posting_node');
    // Get users who fit role requested.
    $career_stages = array_column($node->get('field_stage')->getValue(), 'value');
    $recipient_nids = [];
    foreach ($career_stages as $stage) {
      switch ($stage) {
        case 'early':
          $recent_years = $this->getRecentYearTaxonomyIds();
          $recipient_nids = array_merge($recipient_nids, $this->getNewQuery('node')
            ->condition('type', 'profile')
            ->condition('status', 1)
            ->condition('field_matriculation_year', $recent_years, 'IN')
            ->condition('field_alumni', FALSE)
            ->execute());
          break;
        case 'late':
          $recent_years = $this->getRecentYearTaxonomyIds();
          $recipient_nids = array_merge($recipient_nids, $this->getNewQuery('node')
            ->condition('type', 'profile')
            ->condition('status', 1)
            ->condition('field_matriculation_year', $recent_years, 'NOT IN')
            ->condition('field_alumni', FALSE)
            ->execute());
          break;
        case 'alumni':
          $recipient_nids = array_merge($recipient_nids, $this->getNewQuery('node')
            ->condition('type', 'profile')
            ->condition('status', 1)
            ->condition('field_alumni', TRUE)
            ->execute());
          break;
      }
    }
    $this->tempStore->set('job_posting_recipients', $recipient_nids);
  }

  /**
   * Get recent year taxonomy IDs
   *
   * - last 3 years, including current year
   *
   * @return array
   */
  protected function getRecentYearTaxonomyIds() {
    return $this->getNewQuery('taxonomy_term')
      ->condition('vid', 'matriculation_year')
      ->condition('name', range(date("Y"), date("Y")-2), 'IN')
      ->execute();
  }

  /**
   * Get entity query of specific type
   *
   * @param string $entity_type
   * @return QueryInterface
   */
  protected function getNewQuery($entity_type = 'user') {
    return $this->entityTypeManager->getStorage($entity_type)->getQuery();
  }

  /**
   * Submit handler for the confirmation form
   *
   * - Sets the batch process to send emails.
   */
  public function handleConfirmationSubmit() {
    $job_node = $this->tempStore->get('job_posting_node');
    $batch = [
      'title' => $this->t('Notifying NeuroNet members of this job opportunity...'),
      'operations' => [],
      'init_message'     => $this->t('Commencing'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'error_message'    => $this->t('An error occurred during processing'),
      'finished' => [get_class($this), 'finishBatch']
    ];
    foreach ($this->getRecipients() as $profile_nid) {
      $batch['operations'][] = [[get_class($this), 'sendEmail'],[$profile_nid, $job_node]];
    }
    batch_set($batch);
  }

  /**
   * Batch finish callback
   *
   * - Redirects to the job_posting_redirect_destination from private temp store
   *   & deletes store.
   * - Sets a success message.
   */
  public static function finishBatch() {
    /** @var PrivateTempStoreFactory $tempstore */
    $tempstore = \Drupal::service('tempstore.private');
    $store = $tempstore->get('neuronet_misc');
    $destination = $store->get('job_posting_redirect_destination') ? $store->get('job_posting_redirect_destination') : '/user';
    $job = $store->get('job_posting_node');
    $store->delete('job_posting_redirect_destination');
    $store->delete('job_posting_recipients');
    $store->delete('job_posting_node');
    $response = new TrustedRedirectResponse($destination);
    \Drupal::service('http_middleware.neuronet_misc_redirect')->setRedirectResponse($response);
    \Drupal::messenger()->addStatus(t('An email notification about \'@title\' has been sent to relevant NeuroNet community members.', ['@title' => $job->getTitle()]));
  }

  /**
   * Sends email to the user associated w/ a profile nid
   *
   * @param integer $nid
   * @param \Drupal\node\NodeInterface $job_node
   */
  public static function sendEmail($profile_nid, $job_node) {
    // Get user associated with the profile.
    $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties([
      'field_profile' => $profile_nid,
    ]);
    // Make sure the user is active & allows emails.
    /** @var \Drupal\user\UserInterface $user */
    if (
      ($user = reset($users)) &&
      (!$user->isBlocked()) &&
      ((int) $user->get('field_job_posting_emails')->value)
    ) {
      /** @var \Drupal\node\NodeInterface $profile */
      $profile = \Drupal::entityTypeManager()->getStorage('node')->load($profile_nid);
      // Send email.
      /** @var MailManager $mail_manager */
      $mail_manager = \Drupal::service('plugin.manager.mail');
      $params = [
        'subject' => t('New Job Post: ', ['@title' => $job_node->getTitle()]),
        'notification_render_array' => [
          '#theme' => 'job_posting_notification',
          '#title' => $job_node->getTitle(),
          '#first_name' => $profile->get('field_first_name')->value,
          '#job_url' => $job_node->toUrl()->toString(),
          '#description' => $job_node->get('field_description')->value,
        ],
      ];
      $mail_manager->mail('neuronet_misc', 'job_posting_notification', $user->get('mail')->value, $user->getPreferredLangcode(), $params);
    }
  }

}