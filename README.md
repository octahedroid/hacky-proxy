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

// Use hackyproxy instance
$hackyproxy
  ->setDomain('static.artifactor.io') // domain to forward the static site
  ->setSite('pantheon-proxy-wordpress') // pantheon site name
  ->forward();
```

Add on the `autotload` section of your `composer.json` file the following configuration.

```json
"files": [
  "proxy-loader.php"
]
```
