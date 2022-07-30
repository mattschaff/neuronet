<?php

namespace Drupal\glia_signin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;


/**
 * Provides a form for signing in.
 */
class SignInForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'glia_signin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state,
                            $endpoint = NULL, $pennKey = NULL, $fullName = NULL) {
    $form['endpoint'] = [
      '#type' => 'hidden',
      '#value' => $endpoint,
    ];
    $form['pennkey'] = [
      '#type' => 'hidden',
      '#value' => $pennKey,
    ];
    $form['fullname'] = [
      '#type' => 'hidden',
      '#value' => $fullName ?? '',
    ];

    $form['signInButton'] = [
      '#type' => 'submit',
      '#value' => $this->t('Click here to sign in'),
      '#prefix' => '<div id="signin-button">',
      '#suffix' => '</div>',
      '#attributes' => [
        'class' => ['btn', 'btn-primary'],
      ],
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'wrapper' => 'signin-button',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(FALSE);

    $url = $form_state->getValue('endpoint');
    $pennkey = $form_state->getValue('pennkey');
    $fullname = $form_state->getValue('fullname');
    $signinData = $this->signIn($url, $pennkey, $fullname);

    if ($signinData['status'] == 'error') {
      return [
        '#type' => 'item',
        '#title' => t('Sign-in failed'),
        '#description' => $signinData['message'],
      ];
    }

    if ($signinData['status'] == 'none' || $signinData['status'] == 'prospective') {
      $statusLine = t('You are not yet a GLIA member.');
    } elseif ($signinData['status'] == 'current') {
      $statusLine = t('You are a GLIA member!');
    } else {
      $statusLine = t('You are not currently a GLIA member.');
    }

    $resultInfo['#type'] = 'container';
    if ($signinData['name']) {
      $resultInfo['header'] = [
        '#markup' => '<h2>' . t('Hi, <strong>@name</strong>!', ['@name' => $signinData['name']]) . '</h2>',
      ];
    } else {
      $resultInfo['header'] = [
        '#markup' => '<h2>' . t('Hello!') . '</h2>',
      ];
    }
    $resultInfo['status'] = [
      '#markup' => '<p>' . $statusLine . '</p>',
    ];

    if ($signinData['status'] == 'current' || $signinData['status'] == 'prospective') {
      // Whether it is the fall, winter, or spring meeting
      $currMeetingIndex = $this->getMeetingIndex();
      $numMeetingsAttended = $signinData['meetingsAttended'] + 1;
      $meetingsAttended = [];
      if ($signinData['fallPresent'] || $currMeetingIndex == 0) {
        $meetingsAttended[] = t('Fall');
      }
      if ($signinData['winterPresent'] || $currMeetingIndex == 1) {
        $meetingsAttended[] = t('Winter');
      }
      if ($signinData['springPresent'] || $currMeetingIndex == 2) {
        $meetingsAttended[] = t('Spring');
      }
      $meetingsStr = implode(', ', $meetingsAttended);
      $meetingsLeft = 2 - $currMeetingIndex;
      $possibleMember = $numMeetingsAttended + $meetingsLeft >= 2;
      $anotherMeetingRequired = $numMeetingsAttended == 1;
      $numClass = $anotherMeetingRequired ? '""' : '"text-success"';

      $resultInfo['memberInfoHeader'] = [
        '#markup' => '<h3>' . t('Membership status for next year:') . '</h3>',
      ];
      $resultInfo['meetingsInfo'] = [
        '#markup' => '<p>' .
          t('You have attended <span class=@numClass>@numMeetingsAttended</span> of 2 required meetings this year (@meetings).', [
            '@numClass' => $numClass,
            '@numMeetingsAttended' => $numMeetingsAttended,
            '@meetings' => $meetingsStr,
          ]) . '</p>',
      ];

      $goodActivityStanding = TRUE;
      if ($signinData['activityLeader']) {
        $resultInfo['activityStanding'] = [
          '#markup' => '<p class="text-success">' . t('You are an activity leader or exec.') . '</p>',
        ];
      } elseif ($resultInfo['goodActivityStanding']) {
        $resultInfo['activityStanding'] = [
          '#markup' => '<p class="text-success">' . t('You have been involved with at least 1 activity this year.') . '</p>',
        ];
      } else {
        $resultInfo['activityStanding'] = [
          '#markup' => '<p>' . t('You may not yet have been involved with at least 1 activity this year.') . '</p>',
        ];
        $goodActivityStanding = FALSE;
      }

      if (!$anotherMeetingRequired && $goodActivityStanding) {
        $resultInfo['conclusion'] = [
          '#markup' => '<strong>' . t('You will be a GLIA member next year!') . '</strong>',
        ];
      } elseif ($possibleMember) {
        $resultInfo['conclusion'] = [
          '#markup' => '<strong>' . t('You still have time to fulfill these requirements before next year.') . '</strong>',
        ];
      } else {
        $resultInfo['conclusion'] = [
          '#markup' => '<strong>' . t('Unfortunately, you cannot be a GLIA member next year.') . '</strong>',
        ];
      }
    }

    if ($signinData['status'] != 'none') {
      $resultInfo['dataHeader'] = [
        '#markup' => '<h3>' . t('Here\'s what we know about you:') . '</h3>',
      ];
      $resultInfo['dataList'] = [
        '#markup' => t('<ul>
            <li><strong>Name:</strong> @name</li>
            <li><strong>Preferred email:</strong> @email</li>
            <li><strong>PI(s):</strong> @PI</li>
            <li><strong>GLIA activities:</strong> @activities</li>
          </ul>', [
            '@name' => $signinData['name'],
            '@email' => $signinData['email'],
            '@PI' => $signinData['PI'] ?: t('(none)'),
            '@activities' => $signinData['activities'] ?: t('(none)'),
          ]
        ),
      ];
      $resultInfo['dataButtonPrompt'] = [
        '#markup' => '<p>' . t('To edit this information, click the button below.') . '</p>',
      ];
    } else {
      $resultInfo['dataButtonPrompt'] = [
        '#markup' => '<p>' . t('To become a prospective member of GLIA, click the button below.') . '</p>',
      ];
    }

    $resultInfo['dataButton'] = [
      '#type' => 'link',
      '#title' => t('Update your info'),
      '#url' => Url::fromUri($signinData['updateForm']),
      '#attributes' => [
        'class' => ['btn', 'btn-primary'],
      ],
    ];

    return $resultInfo;
  }

  private function signIn(string $url, string $pennkey, string $fullname) {
    $body['secret'] = Settings::get('glia_signin_secret');    
    $body['pennkey'] = $pennkey;
    $body['name'] = $fullname;
    $body['markAttendance'] = TRUE;

    $response = \Drupal::httpClient()->post($url, [
      'timeout' => 20,
      'body' => json_encode($body, JSON_FORCE_OBJECT),
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
    ]);

    if ($response->getStatusCode() != 200) {
      return [
        'status' => 'error',
        'message' => 'HTTP ' . $response->getStatusCode() . ': ' . $response->getReasonPhrase(),
      ];
    }

    $data = json_decode($response->getBody(), TRUE);
    return $data;
  }

private function getMeetingIndex() {
    $currMonth = getdate()['mon'];
    if ($currMonth >= 8 && $currMonth <= 11) {
      return 0;
    } elseif ($currMonth == 12 || $currMonth <= 3) {
      return 1;
    } elseif ($currMonth >= 4 && $currMonth <= 7) {
      return 2;
    }
  }
}
