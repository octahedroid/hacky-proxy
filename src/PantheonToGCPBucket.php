<?php

namespace Stevector\HackyProxy;

use Proxy\Proxy;
use Proxy\Adapter\Guzzle\GuzzleAdapter;
use Proxy\Filter\RemoveEncodingFilter;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Diactoros\Response\SapiStreamEmitter;

class PantheonToGCPBucket {

  protected $domain = 'static.pantheon.io';

  protected $skipUrls = [
      '.php',
      '/wp/',
      'wp-admin.php',
  ];

  protected $environment = 'dev';

  protected $site = 'pantheon-proxy-wordpress';

  public function setDomain(String $domain)
  {
    $this->domain = $domain;

    return $this;
  }

  public function setSkipUrls(Array $skipUrls)
  {
    $this->skipUrls = $skipUrls;

    return $this;
  }

  public function addSkipUrls(Array $skipUrls)
  {
    $this->skipUrls = array_merge($this->skipUrls ,$skipUrls);

    return $this;
  }

  public function setEnvironment(String $environment)
  {
    $this->environment = $environment;

    return $this;
  }

  public function setSite(String $site)
  {
    $this->site = $site;

    return $this;
  }

  private function calculatePrefix() {
    if (!empty($_ENV['PANTHEON_ENVIRONMENT']) && $_ENV['PANTHEON_ENVIRONMENT'] !== 'lando') {
      return $_ENV['PANTHEON_ENVIRONMENT'];
    }

    return $this->environment;
  }

  private function calculateUri() {
    if ($_SERVER['REQUEST_URI'] === '/') {
      return '/' . $this->calculatePrefix();
    }

    return '/' . $this->calculatePrefix() . $_SERVER['REQUEST_URI'];
  }

  private function calculateSite() {
    if (!empty($_ENV['PANTHEON_ENVIRONMENT']) && $_ENV['PANTHEON_ENVIRONMENT'] !== 'lando') {
      return $_ENV['PANTHEON_SITE_NAME'];
    }

    return $this->site;
  }

  private function isBackendPath() {
    $isBackendPath = array_filter($this->skipUrls, function($url) {
      return strpos($_SERVER['REQUEST_URI'], $url) !== FALSE;
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

  function forward() {
      if (empty($_SERVER['REQUEST_URI'])) {
        return;
      }

      if ($this->isBackendPath()) {
        return;
      }

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

      $url = 'http://'.$this->calculateSite().'.'.$this->domain.'/';

      if (!$this->isValidPath($guzzle, $url, $server['REQUEST_URI'])) {

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
