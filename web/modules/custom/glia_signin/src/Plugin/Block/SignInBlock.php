<?php

namespace Drupal\glia_signin\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;

/**
 * Provides a 'GLIA Sign-in' block.
 * 
 * @Block(
 *  id = "signin_block",
 *  admin_label = @Translation("GLIA Sign-in"),
 *  category = @Translation("GLIA Sign-in")
 * )
 */
class SignInBlock extends BlockBase {


  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $endpoint = $config['signin_block_url'];
    // Shibboleth variables
    if (isset($_SERVER['REMOTE_USER'])) {
      $pennKey = $_SERVER['REMOTE_USER'];
    } elseif (isset($_SERVER['REDIRECT_REMOTE_USER'])) {
      $pennKey = $_SERVER['REDIRECT_REMOTE_USER'];
    } else {
      echo 'Failed to find Penn Key';
      throw new RuntimeException('PennKey not found on server.');
    }

    $form = \Drupal::formBuilder()->getForm('Drupal\glia_signin\Form\SignInForm', $endpoint, $pennKey);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['signin_block_url'] = [
        '#type' => 'textfield',
        '#title' => $this->t('GLIA directory API URL'),
        '#default_value' => $config['signin_block_url'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    $url = $form_state->getValue('signin_block_url');
    if (!UrlHelper::isValid($url, TRUE)) {
      $form_state->setErrorByName('signin_block_url', $this->t('The URL %url is not valid.', ['%url' => $url]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['signin_block_url'] = $form_state->getValue('signin_block_url');
  }
}
