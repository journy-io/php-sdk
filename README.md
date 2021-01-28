[![journy.io](banner.png)](https://journy.io/?utm_source=github&utm_content=readme-php-sdk)

# journy.io PHP SDK

![npm](https://img.shields.io/packagist/v/journy-io/sdk?color=%234d84f5&style=flat-square)

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

* [https://github.com/kriswallsmith/Buzz](https://github.com/kriswallsmith/Buzz)
* [https://github.com/Nyholm/psr7](https://github.com/Nyholm/psr7)


## 🔌 Getting started

### Configuration

To be able to use the SDK you need to generate an API key. If you don't have one you can create one in [journy.io](https://system.journy.io?utm_source=github&utm_content=readme-php-sdk).

If you don't have an account yet, you can create one in [journy.io](https://system.journy.io/register?utm_source=github&utm_content=readme-php-sdk) or [request a demo first](https://www.journy.io/book-demo?utm_source=github&utm_content=readme-php-sdk).

Go to your settings, under the *Connections*-tab, to create and edit API keys. Make sure to give the correct permissions to the API Key.

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

#### Get tracking snippet for domain

```php
use JournyIO\SDK\TrackingSnippet;

$call = $client->getTrackingSnippet("blog.acme.com");

if ($call->succeeded()) {
    $result = $call->result();

    if ($result instanceof TrackingSnippet) {
        var_dump($result->getSnippet()); // string
        var_dump($result->getDomain()); // string
    }
} else {
    var_dump($call->errors());
}
```

#### Create or update user

_Note: when sending an empty value (`""`) as value for a property, the property will be deleted._

```php
$call = $client->upsertUser([
    // required
    "userId" => "userId",
    "email" => "name@domain.tld",

    // optional
    "properties" => [
        "plan" => "Pro",
        "age" => 26,
        "is_developer" => true,
        "registered_at" => new DateTimeImmutable("..."),
        "this_property_will_be_deleted" => "",
    ],
]);
```

#### Create or update account

_Note: when sending an empty value (`""`) as value for a property, the property will be deleted._

```php
$call = $client->upsertAccount([
    // required
    "accountId" => "accountId",
    "name" => "Name",

    // optional
    "properties" => [
        "plan" => "Pro",
        "age" => 26,
        "is_developer" => true,
        "registered_at" => new \DateTimeImmutable("..."),
        "this_property_will_be_deleted" => "",
    ],

    // optional
    "members" => ["userId", "userId"],
]);
```

#### Link web visitor to a user

You can link a web visitor to a user in your application when you have our snippet installed on your website. The snippet sets a cookie named `__journey`. If the cookie exists, you can link the web visitor to the user that is currently logged in:

```php
/** @var Psr\Http\Message\ServerRequestInterface $request */
$cookies = $request->cookieParams();
$deviceId = $cookies["__journey"];

/** @var Illuminate\Http\Request $request */
$deviceId = $request->cookie("__journey");

/** @var Symfony\Component\HttpFoundation\Request $request */
$deviceId = $request->cookies->get("__journey");

if ($deviceId) {
  $call = $client->link($deviceId, "userId");
}
```

#### Add event

```php
use JournyIO\SDK\Event;

$event = Event::forUser("login", "userId");
$event = Event::forUser("some_historic_event", "userId")->happenedAt(new \DateTimeImmutable("now"));
$event = Event::forAccount("reached_monthly_volume", "accountId")->withMetadata([
    "number" => 13313,
    "string" => "string",
    "boolean" => true,
]);
$event = Event::forUserInAccount("updated_settings", "userId", "accountId");

$call = $client->addEvent($event);
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

If you discover any security related issues, please email hans at journy io instead of using the issue tracker.

