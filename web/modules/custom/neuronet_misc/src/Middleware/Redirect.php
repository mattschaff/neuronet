<?php

/**
 * @file Redirect.php
 * @see https://drupal.stackexchange.com/questions/138697/what-function-method-can-i-use-to-redirect-users-to-a-different-page
 */

namespace Drupal\neuronet_misc\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class Redirect implements HttpKernelInterface {
    protected $httpKernel;
    protected $redirectResponse;

    public function __construct(HttpKernelInterface $httpKernel) {
        $this->httpKernel = $httpKernel;
    }

    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
        $response = $this->httpKernel->handle($request, $type, $catch);
        return $this->redirectResponse ?: $response;
    }

    public function setRedirectResponse(?RedirectResponse $redirectResponse) {
        $this->redirectResponse = $redirectResponse;
    }
}