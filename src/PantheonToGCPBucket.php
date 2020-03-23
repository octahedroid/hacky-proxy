<?php

namespace Stevector\HackyProxy;

use Proxy\Proxy;
use Proxy\Adapter\Guzzle\GuzzleAdapter;
use Proxy\Filter\RemoveEncodingFilter;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;

class PantheonToGCPBucket {

  protected $platformEnvironments = [
    'dev',
    'test',
    'live'
  ];
  protected $skipUrls = [
    'wordpress' => [
      '.php',
      '/wp/',
      'wp-admin.php',
    ],
    'drupal' => [
      '.php',
      '/admin/',
      '/user/login',
      '/user/register',
      '/graphql/',
    ]
  ];
  protected $hashEnabled = false;
  protected $environment = '';
  protected $site = '';
  protected $hash = '';
  protected $framework = '';
  protected $forwards = [];
  protected $prefix = '';
  protected $url = '';
  protected $uri = '';

  public function setForwards(Array $forwards)
  {
    $this->forwards = $forwards;

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

  // @todo, what good is this method if calculateEnvironment ignores the value?
  public function setEnvironment(String $environment)
  {
    $this->environment = $environment;

    return $this;
  }

  // @todo, what good is this method if calculateEnvironment ignores the value?
  public function setSite(String $site)
  {
    $this->site = $site;

    return $this;
  }

  public function setHash(String $hash)
  {
    $this->hash = $hash;

    return $this;
  }

  public function setFramework(String $framework)
  {
    $this->framework = $framework;

    return $this;
  }

  public function setHashEnabled($hashEnabled)
  {
    $this->hashEnabled = $hashEnabled;

    return $this;
  }

  private function calculateSite()
  {
    if (!empty($_ENV['PANTHEON_ENVIRONMENT']) && $_ENV['PANTHEON_ENVIRONMENT'] !== 'lando') {
      $this->site = $_ENV['PANTHEON_SITE_NAME'];
    }
  }

  private function calculateEnvironment()
  {
    if (!empty($_ENV['PANTHEON_ENVIRONMENT']) && $_ENV['PANTHEON_ENVIRONMENT'] !== 'lando') {
      $this->environment = $_ENV['PANTHEON_ENVIRONMENT'];
    };
  }

  private function calculateHash()
  {
    if (!empty($_ENV['PANTHEON_ENVIRONMENT']) && $_ENV['PANTHEON_ENVIRONMENT'] !== 'lando') {
      $this->hash = $_ENV['PANTHEON_DEPLOYMENT_IDENTIFIER'];
    }
  }

  private function calculateForward()
  {
    foreach ($this->forwards as $forward) {
      if (strpos($_SERVER['REQUEST_URI'], $forward['path']) !== FALSE) {
        if ($this->hashEnabled && in_array($this->environment, $this->platformEnvironments)) {
          $forward['prefix'] = $forward['prefix'] . '--{hash}';
        }

        $this->prefix = str_replace(
          [
            '{site}',
            '{environment}',
            '{hash}',
          ],
          [
            $this->site,
            $this->environment,
            $this->hash,
          ],
          $forward['prefix']
        );
        $this->url = str_replace(
          [
            '{site}',
            '{environment}',
            '{hash}',
          ],
          [
            $this->site,
            $this->environment,
            $this->hash,
          ],
          $forward['url'] . '/'
        );

        return;
      }
    }
  }

  private function calculateUri()
  {
    if ($_SERVER['REQUEST_URI'] === '/') {
      $this->uri = '/' . $this->prefix;
    }

    $this->uri = $this->prefix . $_SERVER['REQUEST_URI'];
    return $this->uri;
  }

  private function isBackendPath()
  {
    $isBackendPath = array_filter($this->skipUrls[$this->framework], function($url) {
      return strpos($_SERVER['REQUEST_URI'], $url) !== FALSE;
    });

    return (count($isBackendPath) > 0);
  }

  private function isValidPath($guzzle) {
    try {
      $path = $this->url . ($this->uri[0] === "/" ? substr($this->uri, 1) : $this->uri);
      $guzzle->head($path);

      return true;
    } catch (\Exception $e) {

      return false;
    }
  }

  function forward()
  {
      if (empty($_SERVER['REQUEST_URI'])) {
        return;
      }

      if ($this->isBackendPath()) {
        return;
      }

      // Calculate variables
      $this->calculateEnvironment();
      $this->calculateSite();
      $this->calculateHash();
      $this->calculateForward();
      $this->calculateUri();

      $server = array_merge(
        $_SERVER,
        [
          'REQUEST_URI' => $this->uri,
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

      if (!$this->isValidPath($guzzle)) {
        return;
      }

      // Forward the request and get the response.
      $response = $proxy->forward($request)->to($this->url);
      $emitter = new SapiStreamEmitter();
      $emitter->emit($response);
      exit();
    }
}
