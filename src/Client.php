<?php declare(strict_types=1);

namespace JournyIO\SDK;

use Buzz\Client\Curl;
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
    }

    public static function withDefaults(string $apiKey): Client
    {
        $factory = new Psr17Factory();
        $http = new Curl($factory, ["timeout" => 5]);

        return new Client($http, $factory, $factory, ["apiKey" => $apiKey]);
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
        if ($response->getStatusCode() === 401) {
            $response->getBody()->rewind();
            $json = json_decode($response->getBody()->getContents(), true);

            return new CallResult(
                false,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [$json["message"]],
                null
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
                ["rate limited"],
                null
            );
        }
    }

    public function getApiKeyDetails(): CallResult
    {
        $response = $this->http->sendRequest(
            $this->withAuthentication(
                $this->requestFactory->createRequest(
                    "GET",
                    new Uri("{$this->rootUrl}/validate")
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
                $this->requestFactory->createRequest(
                    "GET",
                    new Uri("{$this->rootUrl}/tracking/snippet?domain={$encodedDomain}")
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

        return new CallResult(
            false,
            false,
            $this->getRemainingRequests($response),
            $this->getMaxRequests($response),
            $json['errors'] ?: [],
            null
        );
    }

    public function addUserEvent(string $name, string $userId): CallResult
    {
        return $this->addEvent($name, $userId);
    }

    public function addAccountEvent(string $name, string $accountId, string $userId = null): CallResult
    {
        return $this->addEvent($name, $userId, $accountId);
    }

    private function addEvent(string $name, string $userId = null, string $accountId = null): CallResult
    {
        if (empty($name)) {
            throw new InvalidArgumentException("Event name cannot be empty!");
        }

        if (preg_match("/^[a-z_]+$/", $name, $matches) === 0) {
            throw new InvalidArgumentException("Event names need to be lowercase with underscores!");
        }

        if (empty($userId) && empty($accountId)) {
            throw new InvalidArgumentException("At least one identifier needs to be set!");
        }

        $identification = [];

        if ($userId) {
            $identification["userId"] = $userId;
        }

        if ($accountId) {
            $identification["accountId"] = $accountId;
        }

        $body = $this->streamFactory->createStream(
            json_encode(
                array(
                    'name' => $name,
                    'identification' => $identification,
                )
            )
        );

        $response = $this->http->sendRequest(
            $this->withAuthentication(
                $this->requestFactory
                    ->createRequest(
                        "POST",
                        new Uri("{$this->rootUrl}/events")
                    )
                    ->withHeader("content-type", "application/json")
                    ->withBody($body)
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

    private function formatProperties(array $properties)
    {
        $formatted = array();

        foreach ($properties as $name => $value) {
            if (is_int($value) || is_float($value) || is_string($value)) {
                $formatted[$name] = (string) $value;
            }

            if (is_bool($value)) {
                $formatted[$name] = $value ? "true" : "false";
            }

            if ($value instanceof DateTimeInterface) {
                $formatted[$name] = $value->format(DATE_ATOM);
            }
        }

        return $formatted;
    }

    public function upsertUser(string $id, string $email, array $properties = []): CallResult
    {
        if (empty($id)) {
            throw new InvalidArgumentException("User ID cannot be empty!");
        }

        if (empty($email)) {
            throw new InvalidArgumentException("Email cannot be empty!");
        }

        $payload = [
            "userId" => $id,
            "email" => $email,
        ];

        if (!empty($properties)) {
            $payload["properties"] = $this->formatProperties($properties);
        }

        $body = $this->streamFactory->createStream(json_encode($payload));

        $response = $this->http->sendRequest(
            $this->withAuthentication(
                $this->requestFactory
                    ->createRequest(
                        "POST",
                        new Uri("{$this->rootUrl}/users/upsert")
                    )
                    ->withHeader("content-type", "application/json")
                    ->withBody($body)
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

    public function upsertAccount(string $id, string $name, array $properties = [], array $memberIds = null): CallResult
    {
        if (empty($id)) {
            throw new InvalidArgumentException("Account ID cannot be empty!");
        }

        if (empty($name)) {
            throw new InvalidArgumentException("Name cannot be empty!");
        }

        $payload = [
            "accountId" => $id,
            "name" => $name,
        ];

        if (!empty($properties)) {
            $payload["properties"] = $this->formatProperties($properties);
        }

        if (is_array($memberIds)) {
            $payload["members"] = array_map(
                function ($value) {
                    return (string) $value;
                },
                $memberIds
            );
        }

        $body = $this->streamFactory->createStream(json_encode($payload));

        $response = $this->http->sendRequest(
            $this->withAuthentication(
                $this->requestFactory
                    ->createRequest(
                        "POST",
                        new Uri("{$this->rootUrl}/accounts/upsert")
                    )
                    ->withHeader("content-type", "application/json")
                    ->withBody($body)
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
}
