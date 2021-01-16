[![journy.io](banner.png)](https://journy.io/?utm_source=github&utm_content=readme-php-sdk)

# journy.io PHP SDK

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

To be able to use the SDK you need to generate an API key. If you don't have one you can create one in [journy.io](https://app.journy.io?utm_source=github&utm_content=readme-php-sdk).

If you don't have an account yet, you can create one in [journy.io](https://app.journy.io/register?utm_source=github&utm_content=readme-php-sdk) or [request a demo first](https://www.journy.io/book-demo?utm_source=github&utm_content=readme-php-sdk).

Go to your settings, under the *Connections*-tab, to create and edit API keys. Make sure to give the correct permissions to the API Key.

```php
use JournyIO\SDK\Client;

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

```php
$call = $client->upsertAppUser(
    "userId",
    "name@domain.tld",
    [
        "plan" => "Pro",
        "age" => 26,
        "is_developer" => true,
        "registered_at" => new DateTimeImmutable("..."),
        "this_property_will_be_deleted" => "",
    ]
);
```

#### Create or update account

```php
$members = ["userId", "userId"];

$call = $client->upsertAppAccount(
    "accountId",
    "name",
    [
        "plan" => "Pro",
        "age" => 26,
        "is_developer" => true,
        "registered_at" => new \DateTimeImmutable("..."),
        "this_property_will_be_deleted" => "",
    ],
    $members
);
```

#### Add event

```php
use JournyIO\SDK\AppEvent;

$event = AppEvent::forUser("login", "userId");
$event = AppEvent::forUser("some_historic_event", "userId")->happenedAt(new \DateTimeImmutable("now"));
$event = AppEvent::forAccount("reached_monthly_volume", "accountId");
$event = AppEvent::forUserInAccount("updated_settings", "userId", "accountId");

$call = $client->addEvent($event);
```

### Handling errors

Every call will return a result, we don't throw errors when a call fails. We don't want to break your application when things go wrong.

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

## 🔒 Security

Please create an issue or contact us via the chat on our website.

If you discover any security related issues, please email hans at journy io instead of using the issue tracker.

