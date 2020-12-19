<?php

declare(strict_types=1);

namespace JournyIO\SDK;

use InvalidArgumentException;
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

    private function withAuthentication(RequestInterface $request)
    {
        return $request->withAddedHeader("x-api-key", $this->apiKey);
    }

    private function getMaxRequests(ResponseInterface $response)
    {
        $values = $response->getHeader("x-ratelimit-limit");

        return count($values) > 0 ? (int) $values[0] : 0;
    }

    private function getRemainingRequests(ResponseInterface $response)
    {
        $values = $response->getHeader("x-ratelimit-remaining");

        return count($values) > 0 ? (int) $values[0] : 0;
    }

    private function check(ResponseInterface $response)
    {
        if ($response->getStatusCode() === 500) {
            return new Result(
                false,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                ["unexpected error"] // TODO
            );
        }

        if ($response->getStatusCode() === 429) {
            return new Result(
                false,
                true,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                null
            );
        }
    }

    public function getApiKeyDetails()
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

        $json = json_decode($response->getBody(), true);

        if ($response->getStatusCode() === 200) {
            return new Result(
                true,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [],
                new ApiKeyDetails($json['data']['permissions'])
            );
        }

        return new Result(
            false,
            false,
            $this->getRemainingRequests($response),
            $this->getMaxRequests($response),
            $json['errors'],
            null
        );
    }

    public function getTrackingSnippet(string $domain)
    {
        $response = $this->http->sendRequest(
            $this->withAuthentication(
                $this->requestFactory->createRequest(
                    "GET",
                    new Uri("{$this->rootUrl}/tracking/snippet")
                )
            )
        );

        $result = $this->check($response);

        if ($result) {
            return $result;
        }

        $json = json_decode($response->getBody(), true);

        if ($response->getStatusCode() === 200) {
            return new Result(
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

        return new Result(
            false,
            false,
            $this->getRemainingRequests($response),
            $this->getMaxRequests($response),
            $json['errors'],
            null
        );
    }

    public function addEvent(string $name, string $userId = null, string $accountId = null)
    {
        $body = $this->streamFactory->createStream(
            json_encode(
                array(
                    'name' => $name,
                    'identification' => [
                        'user_id' => $userId,
                        'account_id' => $accountId,
                    ],
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
                    ->withBody($body)
            )
        );

        $result = $this->check($response);

        if ($result) {
            return $result;
        }

        $json = json_decode($response->getBody(), true);

        if ($response->getStatusCode() === 200) {
            return new Result(
                true,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [],
                null
            );
        }

        return new Result(
            false,
            false,
            $this->getRemainingRequests($response),
            $this->getMaxRequests($response),
            $json['errors'],
            null
        );
    }

    public function upsertUser(string $id, string $email, array $properties = [])
    {
        $body = $this->streamFactory->createStream(
            json_encode(
                array(
                    'id' => $id,
                    'email' => $email,
                    'properties' => $properties,
                )
            )
        );

        $response = $this->http->sendRequest(
            $this->withAuthentication(
                $this->requestFactory
                    ->createRequest(
                        "POST",
                        new Uri("{$this->rootUrl}/users/upsert")
                    )
                    ->withBody($body)
            )
        );

        $result = $this->check($response);

        if ($result) {
            return $result;
        }

        $json = json_decode($response->getBody(), true);

        if ($response->getStatusCode() === 200) {
            return new Result(
                true,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [],
                null
            );
        }

        return new Result(
            false,
            false,
            $this->getRemainingRequests($response),
            $this->getMaxRequests($response),
            $json['errors'],
            null
        );
    }

    public function upsertAccount(string $id, string $name, array $properties = [], array $members = null)
    {
        $body = $this->streamFactory->createStream(
            json_encode(
                array(
                    'id' => $id,
                    'name' => $name,
                    'properties' => $properties,
                    'members' => $members,
                )
            )
        );

        $response = $this->http->sendRequest(
            $this->withAuthentication(
                $this->requestFactory
                    ->createRequest(
                        "POST",
                        new Uri("{$this->rootUrl}/accounts/upsert")
                    )
                    ->withBody($body)
            )
        );

        $result = $this->check($response);

        if ($result) {
            return $result;
        }

        $json = json_decode($response->getBody(), true);

        if ($response->getStatusCode() === 200) {
            return new Result(
                true,
                false,
                $this->getRemainingRequests($response),
                $this->getMaxRequests($response),
                [],
                null
            );
        }

        return new Result(
            false,
            false,
            $this->getRemainingRequests($response),
            $this->getMaxRequests($response),
            $json['errors'],
            null
        );
    }
}
