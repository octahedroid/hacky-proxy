<?php

namespace Stevector\HackyProxy;

use Proxy\Proxy;
use Proxy\Adapter\Guzzle\GuzzleAdapter;
use Proxy\Filter\RemoveEncodingFilter;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Diactoros\Response\SapiStreamEmitter;

class PantheonToGCPBucket {

  private function calculatePrefix() {
//print_r($_ENV);

    return $_ENV['PANTHEON_SITE_NAME'] . '--' . $_ENV['PANTHEON_ENVIRONMENT'];
    if (!empty($_ENV['PANTHEON_ENVIRONMENT']) && $_ENV['PANTHEON_ENVIRONMENT'] !== 'lando') {
      return $_ENV['PANTHEON_ENVIRONMENT'];
    }

    // @TODO return master, live, prod or the default stage name.
    return 'pr-19';
  }

  private function calculateUri() {
    //$suffix =  . '/index.html';
    $suffix = '';

    if ($_SERVER['REQUEST_URI'] === '/') {
      return '/' . $this->calculatePrefix() . $suffix;
    }

    return '/' . $this->calculatePrefix() . $_SERVER['REQUEST_URI'] . $;
  }

  private function isBackendPath() {
    // @TODO read from app-config or ENV
    $paths = [
      '.php',
      '/wp/',
      'wp-admin.php',
    ];

    $isBackendPath = array_filter($paths, function($path) {
      return strpos($_SERVER['REQUEST_URI'], $path) !== FALSE;
    });

    return (count($isBackendPath) > 0);
  }

  private function isValidPath($guzzle, $url, $uri) {
    try {
      $path = $url . ($uri[0] === "/" ? substr($uri, 1) : $uri);
      $guzzle->head($path);

      return true;
    } catch (\Exception $e) {

      return false;
    }
  }

  function __construct()
  {


      // No REQUEST_URI
      if (empty($_SERVER['REQUEST_URI'])) {
        return;
      }

      if ($this->isBackendPath()) {
        return;
      }


//      die();
      $server = array_merge(
        $_SERVER,
        [
          'REQUEST_URI' => $this->calculateUri(),
        ]
      );

      // Create request object
      $request = ServerRequestFactory::fromGlobals($server);

      // Create a guzzle client
      $guzzle = new \GuzzleHttp\Client([
        'curl' => [
          CURLOPT_TCP_KEEPALIVE => 15,
          CURLOPT_TCP_KEEPIDLE => 15,
        ]
      ]);

      // Create the proxy instance
      $proxy = new Proxy(new GuzzleAdapter($guzzle));

      // Add a response filter that removes the encoding headers.
      $proxy->filter(new RemoveEncodingFilter());

      // @TODO Read from ENV or app-config
      $url = 'https://us-central1-serverlessplayground.cloudfunctions.net/';

      if (!$this->isValidPath($guzzle, $url, $server['REQUEST_URI'])) {
        // @TODO update $server['REQUEST_URI'] to a valid 404 frontend page path
        return;
      }

      // Forward the request and get the response.
      $response = $proxy->forward($request)->to($url);

      // @TODO dependency to laminas-httphandlerrunner
      $emiter = new SapiStreamEmitter();
      $emiter->emit($response);
      exit();
    }
}
