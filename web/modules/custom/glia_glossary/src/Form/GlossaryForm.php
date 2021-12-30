<?php

namespace Drupal\glia_glossary\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Builds GlossaryForm
 */
class GlossaryForm extends FormBase {

  protected $submitted = false;

  protected $processed_text = '';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'glia_glossary';
  }

   /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // input textarea
    $form['bnb_text'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Paste the Brains and Briefs article here.'),
      '#required' => TRUE,
    );
    $form['examples_link'] = [
      '#title' => $this->t('Reports'),
      '#type' => 'link',
      '#url' => 'http://google.com',
    ];
    // container for ajax
    $form['container'] = [
      '#type'       => 'container',
      '#attributes' => ['id' => 'processed-text-container'], // CHECK THIS ID
    ];
    // container actions
    $form['container']['actions'] = [
      '#type'       => 'container',
      '#weight'     => 99,
      '#attributes' => ['class' => 'actions'], // CHECK THIS ID
    ];
    // ajax submit button
    $form['container']['actions']['submit'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Submit'),
      '#submit' => ['::button_callback'],
      '#attributes' => [
          'class' => ['button--primary'],
        ],
      '#ajax'   => [
        'callback' => '::glossary_form_ajax_callback',
        'wrapper'  => 'processed-text-container', // CHECK THIS ID
      ],
    ];
    if ($this->submitted) {
      // get style
      $style = $this->getStyle();
      // see if at least one term was found
      if (strpos($this->processed_text, '</span>') > -1) {
        $new_text = $style . $this->processed_text;
        // add new text textarea
        $form['container']['bnb_processed_text'] = array(
          '#type' => 'textarea',
          '#title' => $this->t('Transformed text, with tooltip descriptions.'),
          '#description' => $this->t('Now copy and paste this into the SquareSpace editor.'),
          '#value' => $new_text,
          '#attributes' => [
            'id' => ['edit-bnb-processed-text'],
          ],
        );
        $display = '<br><br><h3>' . $this->t('How transformed text will look:') . '</h3>' . $new_text . '<br><br>';
        // copy to clipboard link
        $url = Url::fromUserInput('#', ['fragment' => '#']);
        $link = Link::fromTextAndUrl(t('Copy to Clipboard'),  $url);
        $link = $link->toRenderable();
        $link['#attributes'] = [
          'class' => ['button'],
          'onclick' => 'gliaCopyClipboard()',
        ];
        $link = render($link);
        //echo $link; die();
        $form['container']['actions']['clipboard'] = [
          '#type' => 'markup',
          '#markup' => $link,
          '#allowed_tags' => ['a'],
        ];
      }
      else {
        $display = '<br><br><br>' . $this->t('No terms found in text.') . '<br><br>';
      }
      //set display
      $form['container']['display'] = array(
        '#type' => 'markup',
        '#markup' => $display,
        '#allowed_tags' => ['style', 'div', 'span', 'br', 'h3', 'p'],
        '#weight' => 999,
      );
    }
    //attach library
    $form['container']['#attached']['library'][] = 'glia_glossary/glia';
    return $form;
  }

  /**
   * Ajax callback for GlossaryForm
   */
  public function glossary_form_ajax_callback($form, $form_state) {
    return $form['container'];
  }
  public function button_callback(array &$form, FormStateInterface $form_state) {
    // get input
    $this->processed_text = $form_state->getValue(['bnb_text']);
    // get glossary
    $glossary = $this->getGlossary();
    // Loop through words to add a marker where the terms are in text.
    foreach ($glossary as $name => $description){
      $this->markTermLocation($name);
    }
    // loop through words to replace them.
    foreach ($glossary as $name => $description){
      $description = trim(strip_tags(Xss::filter($description)));
      //process text
      $this->replaceTerm($name, $description);
    }
    // set submitted to true
    $this->submitted = true;
    // rebuild form
    $form_state->setRebuild();
  }


   /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

 	}

  /**
   * Gets glossary of neuroscience terms
   *
   * @return array $glossary
   *
   * @TODO: Don't call services statically; inject them
   */
  private function getGlossary(){
    $vid = 'glossary';
    /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $terms = $term_storage->loadTree($vid);
    $glossary = [];
    foreach ($terms as $term) {
      $glossary[$term->name] = $term->description__value;
    }
    return $glossary;
  }

  /**
   * Replace terms in text with special HTML tags using preg_replace
   *
   * - case insenstive search
   *
   * @param string $name
   *
   * @return string
   */
  private function markTermLocation($name) {
    $this->processed_text = preg_replace('/\b(' . $name . '|' . $name . 's)\b/i', '<span class="glossary-term">$1>>>>>' . $name . '<<<<<', $this->processed_text);
  }

  /**
   * Replace terms in text with special HTML tags using preg_replace
   *
   * - case insenstive search
   *
   * @param string $name
   * @param string $description
   *
   * @return string
   */
  private function replaceTerm($name, $description) {
    $this->processed_text = preg_replace('/>>>>>' . $name . '<<<<</i', '<span class="glossary-term-text">' . $description . '</span></span>', $this->processed_text);
  }

  /**
   * Get CSS style to insert
   *
   * @return string
   *
   * @TODO: Don't call services statically; inject them
   */
  private function getStyle(){
    $module_handler = \Drupal::service('module_handler');
    $path = $module_handler->getModule('glia_glossary')->getPath();
    return file_get_contents($path . '/inc/glossary_style.txt');
  }
}