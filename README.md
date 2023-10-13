[![journy.io](banner.png)](https://journy.io/?utm_source=github&utm_content=readme-php-sdk)

# journy.io PHP SDK

[![Latest version on Packagist](https://img.shields.io/packagist/v/journy-io/sdk?color=%234d84f5&style=flat-square)](https://packagist.org/packages/journy-io/sdk)
[![Downloads](https://img.shields.io/packagist/dt/journy-io/sdk?color=%234d84f5&style=flat-square)](https://packagist.org/packages/journy-io/sdk)
[![Supported PHP versions](https://img.shields.io/packagist/php-v/journy-io/sdk?color=%234d84f5&style=flat-square)](https://packagist.org/packages/journy-io/sdk)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This is the official PHP SDK for [journy.io](https://journy.io?utm_source=github&utm_content=readme-php-sdk).

## 💾 Installation

You can use composer to install the SDK:

```bash
composer require journy-io/sdk
```

You will also need a PSR-7 implementation (HTTP messages), PSR-17 implementation (HTTP factory) and PSR-18 implementation (HTTP client).

If your app doesn't have these yet installed, we recommend:

```bash
composer require kriswallsmith/buzz nyholm/psr7
```

-   [https://github.com/kriswallsmith/Buzz](https://github.com/kriswallsmith/Buzz)
-   [https://github.com/Nyholm/psr7](https://github.com/Nyholm/psr7)

## 🔌 Getting started

### Configuration

To be able to use the SDK you need to generate an API key. If you don't have one you can create one in [journy.io](https://system.journy.io?utm_source=github&utm_content=readme-php-sdk).

If you don't have an account yet, you can create one in [journy.io](https://system.journy.io/register?utm_source=github&utm_content=readme-php-sdk) or [request a demo first](https://www.journy.io/book-demo?utm_source=github&utm_content=readme-php-sdk).

Go to your settings, under the _Connections_-tab, to create and edit API keys. Make sure to give the correct permissions to the API Key.

```php
use JournyIO\SDK\Client;

// composer require kriswallsmith/buzz nyholm/psr7
$client = Client::withDefaults("your-api-key");
```

If you want to use your own HTTP client (PSR-18):

```php
use Buzz\Client\Curl;
use JournyIO\SDK\Client;
use Nyholm\Psr7\Factory\Psr17Factory;

// https://github.com/Nyholm/psr7
$factory = new Psr17Factory();

// https://github.com/kriswallsmith/Buzz
$http = new Curl($factory, ["timeout" => 5]);

$client = new Client($http, $factory, $factory, ["apiKey" => "your-api-key"]);
```

### Methods

#### Get API key details

```php
use JournyIO\SDK\ApiKeyDetails;

$call = $client->getApiKeyDetails();

if ($call->succeeded()) {
    $result = $call->result();

    if ($result instanceof ApiKeyDetails) {
        var_dump($result->getPermissions()); // string[]
    }
} else {
    var_dump($call->errors());
}
```

### Handling errors

Every call will return a result, we don't throw errors when a call fails. We don't want to break your application when things go wrong. An exception will be thrown for required arguments that are empty or missing.

```php
$call = $client->getTrackingSnippet("blog.acme.com");

var_dump($call->succeeded()); // bool
var_dump($call->rateLimited()); // bool
var_dump($call->remainingRequests()); // int
var_dump($call->maxRequests()); // int
var_dump($call->errors()); // array
```

## 📬 API Docs

[API reference](https://developers.journy.io)

## 💯 Tests

To run the tests:

```bash
composer run test
```

## ❓ Help

We welcome your feedback, ideas and suggestions. We really want to make your life easier, so if we’re falling short or should be doing something different, we want to hear about it.

Please create an issue or contact us via the chat on our website.

## 🔒 Security

If you discover any security related issues, please email security at journy io instead of using the issue tracker.
