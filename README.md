# PoC proxying to GCP Buckets from Pantheon

## Description

This library sets up a proxy for all routes on the site and forward routes to a domain where the static site is deployed.

The only routes that are allowed are ones that are configured via the library i.e. those containing 'php', '/wp/', and 'wp-admin.php',

You can set a different array of URLs using the `setSkipUrls` method, or add more using the `addSkipUrls` method.

### Usage

#### When not using composer

TBD...

#### When using composer

Add a new file named `proxy-loader.php` on your composer root project containing the following PHP code.

```php
<?php

// Create new PantheonToGCPBucket instance
$hackyproxy = new \Stevector\HackyProxy\PantheonToGCPBucket();

// Using a single forward configuration
$hackyproxy
  ->setSite('pantheon-proxy-wordpress') // pantheon site
  ->setEnvironment('dev') // pantheon environment
  ->setFramework('wordpress') // pantheon framework `wordpress` or `drupal`
  ->setForwards(
    [
      [
        'path' => '/',
        'url' => 'http://{site}.static.artifactor.io',
        'prefix' => '{site}--{environment}',
      ]
    ]
  )
  ->forward();

// Using a more complex forward configuration
$hackyproxy
  ->setSite('pantheon-rogers-funny-words') // pantheon site
  ->setEnvironment('dev') // pantheon environment
  ->setFramework('wordpress') // pantheon framework
  ->setHash('b54df3e') // pantheon hash
  ->setHashEnabled(true) // pantheon hash-flag
  ->setCacheDisabled(true) // cache-control
  ->setForwards(
    [
      [
        'path' => '/static/',
        'url' => 'http://{site}.static.artifactor.io',
        'prefix' => '{site}--{environment}',
      ],
      [
        'path' => '/',
        'url' => 'https://us-central1-webops-prototypes.cloudfunctions.net',
        'prefix' => '{site}--{environment}',
      ],
    ]
  )
  ->forward();
```

Add on the `autotload` section of your `composer.json` file the following configuration.

```json
"files": [
  "proxy-loader.php"
]
```
