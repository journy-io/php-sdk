<?php

declare(strict_types=1);

namespace JournyIO\SDK;

use Buzz\Client\Curl;
use Composer\InstalledVersions;
use DateTimeInterface;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class Client
{
    private $http;
    private $requestFactory;
    private $streamFactory;
    private $apiKey;
    private $rootUrl;
    private $version;

    public function __construct(
        ClientInterface $http,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        array $config
    ) {
        if (empty($config)) {
            throw new InvalidArgumentException("Configuration cannot be empty!");
        }

        if (array_key_exists("apiKey", $config) === false) {
            throw new InvalidArgumentException("apiKey is missing!");
        }

        if (empty($config["apiKey"])) {
            throw new InvalidArgumentException("apiKey cannot be empty!");
        }

        $this->http = $http;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->apiKey = $config["apiKey"];
        $this->rootUrl = $config["rootUrl"] ?? "https://api.journy.io";
        $this->version = InstalledVersions::getPrettyVersion('journy-io/sdk');
    }

    public static function withDefaults(string $apiKey): Client
    {
        $factory = new Psr17Factory();
        $http = new Curl($factory, ["timeout" => 5]);

        return new Client($http, $factory, $factory, ["apiKey" => $apiKey]);
    }

    private function withUserAgent(RequestInterface $request): RequestInterface
    {
        return $request->withAddedHeader("user-agent", "php-sdk/" . $this->version);
    }

    private function withAuthentication(RequestInterface $request): RequestInterface
    {
        return $request->withAddedHeader("x-api-key", $this->apiKey);
    }

    private function getMaxRequests(ResponseInterface $response): int
    {
        $values = $response->getHeader("x-ratelimit-limit");

        return count($values) > 0 ? (int) $values[0] : 0;
    }

    private function getRemainingRequests(ResponseInterface $response): int
    {
        $values = $response->getHeader("x-ratelimit-remaining");

        return count($values) > 0 ? (int) $values[0] : 0;
    }

    private function check(ResponseInterface $response)
    {
        $response->getBody()->rewind();
        $json = json_decode($response->getBody()->getContents(), true);

        if ($response->getStatusCode() === 401) {
            return new CallResult(
                false,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [$json["message"]]
            );
        }

        if ($response->getStatusCode() === 403) {
            return new CallResult(
                false,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [$json["message"]]
            );
        }

        if ($response->getStatusCode() === 500) {
            return new CallResult(
                false,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                ["something unexpected happened"]
            );
        }

        if ($response->getStatusCode() === 429) {
            return new CallResult(
                false,
                true,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [$json["message"]]
            );
        }
    }

    public function getApiKeyDetails(): CallResult
    {
        $response = $this->http->sendRequest(
            $this->withAuthentication(
                $this->withUserAgent(
                    $this->requestFactory->createRequest(
                        "GET",
                        new Uri("{$this->rootUrl}/validate")
                    )
                )
            )
        );

        $result = $this->check($response);

        if ($result) {
            return $result;
        }

        $response->getBody()->rewind();
        $json = json_decode($response->getBody()->getContents(), true);

        if ($response->getStatusCode() === 200) {
            return new CallResult(
                true,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [],
                new ApiKeyDetails($json["data"]["permissions"])
            );
        }

        return new CallResult(
            false,
            false,
            $this->getRemainingRequests($response),
            $this->getMaxRequests($response),
            $json['errors'] ?: [],
            null
        );
    }

    public function getTrackingSnippet(string $domain): CallResult
    {
        if (empty($domain)) {
            throw new InvalidArgumentException("Domain cannot be empty!");
        }

        $encodedDomain = urlencode($domain);
        $response = $this->http->sendRequest(
            $this->withAuthentication(
                $this->withUserAgent(
                    $this->requestFactory->createRequest(
                        "GET",
                        new Uri("{$this->rootUrl}/tracking/snippet?domain={$encodedDomain}")
                    )
                )
            )
        );

        $result = $this->check($response);

        if ($result) {
            return $result;
        }

        $response->getBody()->rewind();
        $json = json_decode($response->getBody()->getContents(), true);

        if ($response->getStatusCode() === 200) {
            return new CallResult(
                true,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [],
                new TrackingSnippet(
                    $json['data']['domain'],
                    $json['data']['snippet']
                )
            );
        }

        if ($response->getStatusCode() === 404) {
            return new CallResult(
                false,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                $json['message'] ? [$json['message']] : [],
                null
            );
        }

        return new CallResult(
            false,
            false,
            $this->getRemainingRequests($response),
            $this->getMaxRequests($response),
            $json['errors'] ?: [],
            null
        );
    }

    private function userIdentifiersToArray(UserIdentified $user): array
    {
        $result = [];

        $userId = $user->getUserId();
        if ($userId) {
            $result["userId"] = $userId;
        }

        $email = $user->getEmail();
        if ($email) {
            $result["email"] = $email;
        }

        return $result;
    }

    private function accountIdentifiersToArray(AccountIdentified $account): array
    {
        $result = [];

        $accountId = $account->getAccountId();
        if ($accountId) {
            $result["accountId"] = $accountId;
        }

        $domain = $account->getDomain();
        if ($domain) {
            $result["domain"] = $domain;
        }

        return $result;
    }

    public function addEvent(Event $event): CallResult
    {
        $identification = [];

        if ($event->getUser() instanceof UserIdentified) {
            $identification["user"] = $this->userIdentifiersToArray($event->getUser());
        }

        if ($event->getAccount() instanceof AccountIdentified) {
            $identification["account"] = $this->accountIdentifiersToArray($event->getAccount());
        }

        $payload = [
            'name' => $event->getName(),
            'identification' => $identification,
        ];

        $recordedAt = $event->getRecordedAt();
        if ($recordedAt instanceof DateTimeInterface) {
            $payload["recordedAt"] = $recordedAt->format(DATE_ATOM);
        }

        $metadata = $event->getMetadata();
        if (!empty($metadata)) {
            $payload["metadata"] = $this->formatMetadata($metadata);
        }

        $body = $this->streamFactory->createStream(json_encode($payload));

        $response = $this->http->sendRequest(
            $this->withAuthentication(
                $this->withUserAgent(
                    $this->requestFactory
                        ->createRequest(
                            "POST",
                            new Uri("{$this->rootUrl}/track")
                        )
                        ->withHeader("content-type", "application/json")
                        ->withBody($body)
                )
            )
        );

        $result = $this->check($response);

        if ($result) {
            return $result;
        }

        $response->getBody()->rewind();
        $json = json_decode($response->getBody()->getContents(), true);

        if ($response->getStatusCode() === 201) {
            return new CallResult(
                true,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [],
                null
            );
        }

        return new CallResult(
            false,
            false,
            $this->getRemainingRequests($response),
            $this->getMaxRequests($response),
            $json['errors'] ?: [],
            null
        );
    }

    public function link(array $arguments): CallResult
    {
        if (isset($arguments["deviceId"]) === false || empty($arguments["deviceId"])) {
            throw new InvalidArgumentException("Device ID cannot be empty!");
        }

        $payload = [
            "deviceId" => $arguments["deviceId"],
            "identification" => $this->userIdentifiersToArray(
                new UserIdentified(
                    isset($arguments["userId"]) ? (string) $arguments["userId"] : null,
                    isset($arguments["email"]) ? (string) $arguments["email"] : null
                )
            ),
        ];

        $body = $this->streamFactory->createStream(json_encode($payload));

        $response = $this->http->sendRequest(
            $this->withAuthentication(
                $this->withUserAgent(
                    $this->requestFactory
                        ->createRequest(
                            "POST",
                            new Uri("{$this->rootUrl}/link")
                        )
                        ->withHeader("content-type", "application/json")
                        ->withBody($body)
                )
            )
        );

        $result = $this->check($response);

        if ($result) {
            return $result;
        }

        $response->getBody()->rewind();
        $json = json_decode($response->getBody()->getContents(), true);

        if ($response->getStatusCode() === 201) {
            return new CallResult(
                true,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [],
                null
            );
        }

        return new CallResult(
            false,
            false,
            $this->getRemainingRequests($response),
            $this->getMaxRequests($response),
            $json['errors'] ?: [],
            null
        );
    }

    private function formatMetadata(array $metadata): array
    {
        $formatted = array();

        foreach ($metadata as $name => $value) {
            if (is_int($value) || is_float($value) || is_string($value) || is_bool($value)) {
                $formatted[$name] = $value;
                continue;
            }

            if ($value instanceof DateTimeInterface) {
                $formatted[$name] = $value->format(DATE_ATOM);
                continue;
            }

            $formatted[$name] = (string) $value;
        }

        return $formatted;
    }

    private function formatProperties(array $properties): array
    {
        $formatted = array();

        foreach ($properties as $name => $value) {
            if (
                is_int($value) ||
                is_float($value) ||
                is_string($value) ||
                is_bool($value) ||
                is_array($value) ||
                is_null($value)
            ) {
                $formatted[$name] = $value;
                continue;
            }

            if ($value instanceof DateTimeInterface) {
                $formatted[$name] = $value->format(DATE_ATOM);
                continue;
            }

            $formatted[$name] = (string) $value;
        }

        return $formatted;
    }

    public function upsertUser(array $user): CallResult
    {
        $payload = [
            "identification" => $this->userIdentifiersToArray(
                new UserIdentified(
                    isset($user["userId"]) ? (string) $user["userId"] : null,
                    isset($user["email"]) ? (string) $user["email"] : null
                )
            ),
        ];

        if (isset($user["properties"]) && is_array($user["properties"])) {
            $payload["properties"] = $this->formatProperties($user["properties"]);
        }

        $body = $this->streamFactory->createStream(json_encode($payload));

        $response = $this->http->sendRequest(
            $this->withAuthentication(
                $this->withUserAgent(
                    $this->requestFactory
                        ->createRequest(
                            "POST",
                            new Uri("{$this->rootUrl}/users/upsert")
                        )
                        ->withHeader("content-type", "application/json")
                        ->withBody($body)
                )
            )
        );

        $result = $this->check($response);

        if ($result) {
            return $result;
        }

        $response->getBody()->rewind();
        $json = json_decode($response->getBody()->getContents(), true);

        if ($response->getStatusCode() === 201) {
            return new CallResult(
                true,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [],
                null
            );
        }

        return new CallResult(
            false,
            false,
            $this->getRemainingRequests($response),
            $this->getMaxRequests($response),
            $json['errors'] ?: [],
            null
        );
    }

    public function deleteUser(array $user): CallResult
    {
        $payload = [
            "identification" => $this->userIdentifiersToArray(
                new UserIdentified(
                    isset($user["userId"]) ? (string) $user["userId"] : null,
                    isset($user["email"]) ? (string) $user["email"] : null
                )
            ),
        ];

        $body = $this->streamFactory->createStream(json_encode($payload));

        $response = $this->http->sendRequest(
            $this->withAuthentication(
                $this->withUserAgent(
                    $this->requestFactory
                        ->createRequest(
                            "DELETE",
                            new Uri("{$this->rootUrl}/users")
                        )
                        ->withHeader("content-type", "application/json")
                        ->withBody($body)
                )
            )
        );

        $result = $this->check($response);

        if ($result) {
            return $result;
        }

        $response->getBody()->rewind();
        $json = json_decode($response->getBody()->getContents(), true);

        if ($response->getStatusCode() === 202) {
            return new CallResult(
                true,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [],
                null
            );
        }

        return new CallResult(
            false,
            false,
            $this->getRemainingRequests($response),
            $this->getMaxRequests($response),
            $json['errors'] ?: [],
            null
        );
    }

    public function upsertAccount(array $account): CallResult
    {
        $payload = [
            "identification" => $this->accountIdentifiersToArray(
                new AccountIdentified(
                    isset($account["accountId"]) ? (string) $account["accountId"] : null,
                    isset($account["domain"]) ? (string) $account["domain"] : null
                )
            ),
        ];

        if (isset($account["properties"]) && is_array($account["properties"])) {
            $payload["properties"] = $this->formatProperties($account["properties"]);
        }

        $body = $this->streamFactory->createStream(json_encode($payload));

        $response = $this->http->sendRequest(
            $this->withAuthentication(
                $this->withUserAgent(
                    $this->requestFactory
                        ->createRequest(
                            "POST",
                            new Uri("{$this->rootUrl}/accounts/upsert")
                        )
                        ->withHeader("content-type", "application/json")
                        ->withBody($body)
                )
            )
        );

        $result = $this->check($response);

        if ($result) {
            return $result;
        }

        $response->getBody()->rewind();
        $json = json_decode($response->getBody()->getContents(), true);

        if ($response->getStatusCode() === 201) {
            return new CallResult(
                true,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [],
                null
            );
        }

        return new CallResult(
            false,
            false,
            $this->getRemainingRequests($response),
            $this->getMaxRequests($response),
            $json['errors'] ?: [],
            null
        );
    }

    public function deleteAccount(array $account): CallResult
    {
        $payload = [
            "identification" => $this->accountIdentifiersToArray(
                new AccountIdentified(
                    isset($account["accountId"]) ? (string) $account["accountId"] : null,
                    isset($account["domain"]) ? (string) $account["domain"] : null
                )
            ),
        ];

        $body = $this->streamFactory->createStream(json_encode($payload));

        $response = $this->http->sendRequest(
            $this->withAuthentication(
                $this->withUserAgent(
                    $this->requestFactory
                        ->createRequest(
                            "DELETE",
                            new Uri("{$this->rootUrl}/accounts")
                        )
                        ->withHeader("content-type", "application/json")
                        ->withBody($body)
                )
            )
        );

        $result = $this->check($response);

        if ($result) {
            return $result;
        }

        $response->getBody()->rewind();
        $json = json_decode($response->getBody()->getContents(), true);

        if ($response->getStatusCode() === 202) {
            return new CallResult(
                true,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [],
                null
            );
        }

        return new CallResult(
            false,
            false,
            $this->getRemainingRequests($response),
            $this->getMaxRequests($response),
            $json['errors'] ?: [],
            null
        );
    }

    public function addUsersToAccount(array $arguments): CallResult
    {
        if (!isset($arguments["account"])) {
            throw new InvalidArgumentException("Account can not be empty!");
        }
        if (!isset($arguments["users"])) {
            throw new InvalidArgumentException("Users can not be empty!");
        }

        $payload = [
            "account" => $this->accountIdentifiersToArray(
                new AccountIdentified(
                    isset($arguments["account"]["accountId"]) ? (string) $arguments["account"]["accountId"] : null,
                    isset($arguments["account"]["domain"]) ? (string) $arguments["account"]["domain"] : null
                )
            ),
            "users" => array_map(
                function (array $user) {
                    return ["identification" => $this->userIdentifiersToArray(
                        new UserIdentified(
                            isset($user["userId"]) ? (string) $user["userId"] : null,
                            isset($user["email"]) ? (string) $user["email"] : null
                        )
                    )];
                },
                array_values($arguments["users"])
            )
        ];

        $body = $this->streamFactory->createStream(json_encode($payload));

        $response = $this->http->sendRequest(
            $this->withAuthentication(
                $this->withUserAgent(
                    $this->requestFactory
                        ->createRequest(
                            "POST",
                            new Uri("{$this->rootUrl}/accounts/users/add")
                        )
                        ->withHeader("content-type", "application/json")
                        ->withBody($body)
                )
            )
        );

        $result = $this->check($response);

        if ($result) {
            return $result;
        }

        $response->getBody()->rewind();
        $json = json_decode($response->getBody()->getContents(), true);

        if ($response->getStatusCode() === 201) {
            return new CallResult(
                true,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [],
                null
            );
        }

        return new CallResult(
            false,
            false,
            $this->getRemainingRequests($response),
            $this->getMaxRequests($response),
            $json['errors'] ?: [],
            null
        );
    }

    public function removeUsersFromAccount(array $arguments): CallResult
    {
        if (!isset($arguments["account"])) {
            throw new InvalidArgumentException("Account can not be empty!");
        }
        if (!isset($arguments["users"])) {
            throw new InvalidArgumentException("Users can not be empty!");
        }

        $payload = [
            "account" => $this->accountIdentifiersToArray(
                new AccountIdentified(
                    isset($arguments["account"]["accountId"]) ? (string) $arguments["account"]["accountId"] : null,
                    isset($arguments["account"]["domain"]) ? (string) $arguments["account"]["domain"] : null
                )
            ),
            "users" => array_map(
                function (array $user) {
                    return ["identification" => $this->userIdentifiersToArray(
                        new UserIdentified(
                            isset($user["userId"]) ? (string) $user["userId"] : null,
                            isset($user["email"]) ? (string) $user["email"] : null
                        )
                    )];
                },
                array_values($arguments["users"])
            )
        ];

        $body = $this->streamFactory->createStream(json_encode($payload));

        $response = $this->http->sendRequest(
            $this->withAuthentication(
                $this->withUserAgent(
                    $this->requestFactory
                        ->createRequest(
                            "POST",
                            new Uri("{$this->rootUrl}/accounts/users/remove")
                        )
                        ->withHeader("content-type", "application/json")
                        ->withBody($body)
                )
            )
        );

        $result = $this->check($response);

        if ($result) {
            return $result;
        }

        $response->getBody()->rewind();
        $json = json_decode($response->getBody()->getContents(), true);

        if ($response->getStatusCode() === 204) {
            return new CallResult(
                true,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [],
                null
            );
        }

        return new CallResult(
            false,
            false,
            $this->getRemainingRequests($response),
            $this->getMaxRequests($response),
            $json['errors'] ?: [],
            null
        );
    }
}
