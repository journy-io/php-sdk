<?php

declare(strict_types=1);

namespace JournyIO\SDK;

use Buzz\Client\Curl;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HttpClientFixed implements ClientInterface
{
    private $response;
    private $lastRequest;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    public function getLastRequest()
    {
        return $this->lastRequest;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;
        $this->response->getBody()->rewind();

        return $this->response;
    }
}

class ClientTest extends TestCase
{
    public function test_it_returns_client_with_defaults()
    {
        $this->assertInstanceOf(
            Client::class,
            Client::withDefaults("key")
        );
    }

    public function test_it_throws_error_if_configuration_is_empty()
    {
        $this->expectExceptionMessage("Configuration cannot be empty!");
        $factory = new Psr17Factory();
        $http = new Curl($factory);
        new Client($http, $factory, $factory, []);
    }

    public function test_it_throws_error_if_api_key_is_missing()
    {
        $this->expectExceptionMessage("apiKey is missing!");
        $factory = new Psr17Factory();
        $http = new Curl($factory);
        new Client($http, $factory, $factory, ["a" => "b"]);
    }

    public function test_it_throws_error_if_api_key_is_empty()
    {
        $this->expectExceptionMessage("apiKey cannot be empty!");
        $factory = new Psr17Factory();
        $http = new Curl($factory);
        new Client($http, $factory, $factory, ["apiKey" => ""]);
    }

    public function test_it_returns_api_key_details()
    {
        $factory = new Psr17Factory();
        $json = '{"data":{"permissions":["TrackData","GetTrackingSnippet"]},"meta":{"status":200,"requestId":"01ETG0K4WP1X375JWK87X1RQE1"}}';
        $http = new HttpClientFixed(new Response(200, [], $json));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);
        $result = $client->getApiKeyDetails();

        $this->assertEquals(
            new CallResult(true, false, 0, 0, [], new ApiKeyDetails(["TrackData", "GetTrackingSnippet"])),
            $result
        );

        $this->assertEquals(
            ["TrackData", "GetTrackingSnippet"],
            $result->result()->getPermissions()
        );

        $request = $http->getLastRequest();
        $this->assertInstanceOf(RequestInterface::class, $request);
    }

    public function test_it_returns_tracking_snippet()
    {
        $factory = new Psr17Factory();
        $json = '{"data":{"domain":"php-sdk.com","snippet":"javascript"},"meta":{"status":200,"requestId":"01ETG10BGDBA23FV9PE4Z1X5YF"}}';
        $http = new HttpClientFixed(new Response(200, [], $json));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);
        $result = $client->getTrackingSnippet("php-sdk.com");

        $this->assertEquals(
            new CallResult(true, false, 0, 0, [], new TrackingSnippet("php-sdk.com", "javascript")),
            $result
        );

        $request = $http->getLastRequest();
        $this->assertInstanceOf(RequestInterface::class, $request);

        $this->assertEquals(
            "php-sdk.com",
            $result->result()->getDomain()
        );

        $this->assertEquals(
            "javascript",
            $result->result()->getSnippet()
        );
    }

    public function test_it_upserts_user()
    {
        $factory = new Psr17Factory();
        $json = '{"message":"The data is correctly stored.","meta":{"status":201,"requestId":"01ETG3HQ4JY4HNNZ84FBJM3CSC"}}';
        $http = new HttpClientFixed(new Response(201, [], $json));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);
        $now = new DateTimeImmutable("now");

        $this->assertEquals(
            new CallResult(true, false, 0, 0, [], null),
            $client->upsertUser(
                [
                    "userId" => "1",
                    "email" => new Email("hans@journy.io"),
                    "properties" => [
                        "string" => "string",
                        "boolean" => true,
                        "number" => 22,
                        "date" => $now,
                        "phone" => new PhoneNumber("number"),
                    ],
                ]
            )
        );

        $request = $http->getLastRequest();
        $this->assertInstanceOf(RequestInterface::class, $request);

        if ($request instanceof RequestInterface) {
            $request->getBody()->rewind();
            $body = $request->getBody()->getContents();
            $payload = json_decode($body, true);
            $this->assertEquals(
                [
                    "identification" => [
                        "userId" => "1",
                        "email" => "hans@journy.io",
                    ],
                    "properties" => [
                        "string" => "string",
                        "boolean" => true,
                        "number" => 22,
                        "date" => $now->format(DATE_ATOM),
                        "phone" => "number",
                    ],
                ],
                $payload
            );
        }
    }

    public function test_it_upserts_user_without_user_id()
    {
        $factory = new Psr17Factory();
        $json = '{"message":"The data is correctly stored.","meta":{"status":201,"requestId":"01ETG3HQ4JY4HNNZ84FBJM3CSC"}}';
        $http = new HttpClientFixed(new Response(201, [], $json));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);
        $now = new DateTimeImmutable("now");

        $this->assertEquals(
            new CallResult(true, false, 0, 0, [], null),
            $client->upsertUser(
                [
                    "email" => "hans@journy.io",
                    "properties" => [
                        "string" => "string",
                        "boolean" => true,
                        "number" => 22,
                        "date" => $now,
                    ],
                ]
            )
        );

        $request = $http->getLastRequest();
        $this->assertInstanceOf(RequestInterface::class, $request);

        if ($request instanceof RequestInterface) {
            $request->getBody()->rewind();
            $body = $request->getBody()->getContents();
            $payload = json_decode($body, true);
            $this->assertEquals(
                [
                    "identification" => [
                        "email" => "hans@journy.io",
                    ],
                    "properties" => [
                        "string" => "string",
                        "boolean" => true,
                        "number" => 22,
                        "date" => $now->format(DATE_ATOM),
                    ],
                ],
                $payload
            );
        }
    }

    public function test_it_upserts_account()
    {
        $factory = new Psr17Factory();
        $json = '{"message":"The data is correctly stored.","meta":{"status":201,"requestId":"01ETG3HQ4JY4HNNZ84FBJM3CSC"}}';
        $http = new HttpClientFixed(new Response(201, [], $json));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);
        $now = new DateTimeImmutable("now");

        $this->assertEquals(
            new CallResult(true, false, 0, 0, [], null),
            $client->upsertAccount(
                [
                    "accountId" => "1",
                    "domain" => "journy.io",
                    "properties" => [
                        "string" => "string",
                        "boolean" => true,
                        "number" => 22,
                        "date" => $now,
                    ],
                    "members" => [
                        ["userId" => "1"],
                        ["userId" => "2"],
                    ],
                ]
            )
        );

        $request = $http->getLastRequest();
        $this->assertInstanceOf(RequestInterface::class, $request);
        if ($request instanceof RequestInterface) {
            $request->getBody()->rewind();
            $body = $request->getBody()->getContents();
            $payload = json_decode($body, true);
            $this->assertEquals(
                [
                    "identification" => [
                        "accountId" => 1,
                        "domain" => "journy.io",
                    ],
                    "properties" => [
                        "string" => "string",
                        "boolean" => true,
                        "number" => 22,
                        "date" => $now->format(DATE_ATOM),
                    ],
                    "members" => [
                        [
                            "identification" => ["userId" => "1"],
                        ],
                        [
                            "identification" => ["userId" => "2"],
                        ],
                    ],
                ],
                $payload
            );
        }
    }

    public function test_it_upserts_account_without_account_id()
    {
        $factory = new Psr17Factory();
        $json = '{"message":"The data is correctly stored.","meta":{"status":201,"requestId":"01ETG3HQ4JY4HNNZ84FBJM3CSC"}}';
        $http = new HttpClientFixed(new Response(201, [], $json));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);
        $now = new DateTimeImmutable("now");

        $this->assertEquals(
            new CallResult(true, false, 0, 0, [], null),
            $client->upsertAccount(
                [
                    "domain" => "journy.io",
                    "properties" => [
                        "string" => "string",
                        "boolean" => true,
                        "number" => 22,
                        "date" => $now,
                    ],
                    "members" => [
                        ["userId" => "1"],
                        ["userId" => "2"],
                    ],
                ]
            )
        );

        $request = $http->getLastRequest();
        $this->assertInstanceOf(RequestInterface::class, $request);
        if ($request instanceof RequestInterface) {
            $request->getBody()->rewind();
            $body = $request->getBody()->getContents();
            $payload = json_decode($body, true);
            $this->assertEquals(
                [
                    "identification" => [
                        "domain" => "journy.io",
                    ],
                    "properties" => [
                        "string" => "string",
                        "boolean" => true,
                        "number" => 22,
                        "date" => $now->format(DATE_ATOM),
                    ],
                    "members" => [
                        [
                            "identification" => ["userId" => "1"],
                        ],
                        [
                            "identification" => ["userId" => "2"],
                        ],
                    ],
                ],
                $payload
            );
        }
    }

    public function test_it_deals_with_sparse_arrays()
    {
        $factory = new Psr17Factory();
        $json = '{"message":"The data is correctly stored.","meta":{"status":201,"requestId":"01ETG3HQ4JY4HNNZ84FBJM3CSC"}}';
        $http = new HttpClientFixed(new Response(201, [], $json));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);
        $now = new DateTimeImmutable("now");

        $this->assertEquals(
            new CallResult(true, false, 0, 0, [], null),
            $client->upsertAccount(
                [
                    "accountId" => "1",
                    "members" => [
                        1 => ["userId" => "1"],
                        2 => ["userId" => "2"],
                    ],
                ]
            )
        );

        $request = $http->getLastRequest();
        $this->assertInstanceOf(RequestInterface::class, $request);
        if ($request instanceof RequestInterface) {
            $request->getBody()->rewind();
            $body = $request->getBody()->getContents();
            $payload = json_decode($body, true);
            $this->assertEquals(
                [
                    "identification" => [
                        "accountId" => "1",
                    ],
                    "members" => [
                        [
                            "identification" => ["userId" => "1"],
                        ],
                        [
                            "identification" => ["userId" => "2"],
                        ],
                    ],
                ],
                $payload
            );
        }
    }

    public function test_it_triggers_event_for_user()
    {
        $factory = new Psr17Factory();
        $json = '{"message":"The data is correctly stored.","meta":{"status":201,"requestId":"01ETG3HQ4JY4HNNZ84FBJM3CSC"}}';
        $http = new HttpClientFixed(new Response(201, [], $json));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);

        $this->assertEquals(
            new CallResult(true, false, 0, 0, [], null),
            $client->addEvent(Event::forUser("login", UserIdentified::byUserId("1")))
        );

        $request = $http->getLastRequest();
        $this->assertInstanceOf(RequestInterface::class, $request);
        if ($request instanceof RequestInterface) {
            $request->getBody()->rewind();
            $body = $request->getBody()->getContents();
            $payload = json_decode($body, true);
            $this->assertEquals(
                [
                    "name" => "login",
                    "identification" => [
                        "user" => [
                            "userId" => "1"
                        ],
                    ],
                ],
                $payload
            );
        }
    }

    public function test_it_triggers_event_for_user_with_email()
    {
        $factory = new Psr17Factory();
        $json = '{"message":"The data is correctly stored.","meta":{"status":201,"requestId":"01ETG3HQ4JY4HNNZ84FBJM3CSC"}}';
        $http = new HttpClientFixed(new Response(201, [], $json));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);

        $this->assertEquals(
            new CallResult(true, false, 0, 0, [], null),
            $client->addEvent(Event::forUser("login", UserIdentified::byEmail("hi@journy.io")))
        );

        $request = $http->getLastRequest();
        $this->assertInstanceOf(RequestInterface::class, $request);
        if ($request instanceof RequestInterface) {
            $request->getBody()->rewind();
            $body = $request->getBody()->getContents();
            $payload = json_decode($body, true);
            $this->assertEquals(
                [
                    "name" => "login",
                    "identification" => [
                        "user" => [
                            "email" => "hi@journy.io"
                        ],
                    ],
                ],
                $payload
            );
        }
    }

    public function test_it_links_web_visitor_with_user()
    {
        $factory = new Psr17Factory();
        $json = '{"message":"The data is correctly stored.","meta":{"status":201,"requestId":"01ETG3HQ4JY4HNNZ84FBJM3CSC"}}';
        $http = new HttpClientFixed(new Response(201, [], $json));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);

        $this->assertEquals(
            new CallResult(true, false, 0, 0, [], null),
            $client->link(["deviceId" => "deviceId", "userId" => "userId", "email" => "email"])
        );

        $request = $http->getLastRequest();
        $this->assertInstanceOf(RequestInterface::class, $request);
        if ($request instanceof RequestInterface) {
            $request->getBody()->rewind();
            $body = $request->getBody()->getContents();
            $payload = json_decode($body, true);
            $this->assertEquals(
                [
                    "deviceId" => "deviceId",
                    "identification" => [
                        "userId" => "userId",
                        "email" => "email",
                    ],
                ],
                $payload
            );
        }
    }

    public function test_it_links_web_visitor_with_user_without_user_id()
    {
        $factory = new Psr17Factory();
        $json = '{"message":"The data is correctly stored.","meta":{"status":201,"requestId":"01ETG3HQ4JY4HNNZ84FBJM3CSC"}}';
        $http = new HttpClientFixed(new Response(201, [], $json));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);

        $this->assertEquals(
            new CallResult(true, false, 0, 0, [], null),
            $client->link(["deviceId" => "deviceId", "email" => "email"])
        );

        $request = $http->getLastRequest();
        $this->assertInstanceOf(RequestInterface::class, $request);
        if ($request instanceof RequestInterface) {
            $request->getBody()->rewind();
            $body = $request->getBody()->getContents();
            $payload = json_decode($body, true);
            $this->assertEquals(
                [
                    "deviceId" => "deviceId",
                    "identification" => [
                        "email" => "email",
                    ],
                ],
                $payload
            );
        }
    }

    public function test_it_triggers_event_for_user_with_date()
    {
        $factory = new Psr17Factory();
        $json = '{"message":"The data is correctly stored.","meta":{"status":201,"requestId":"01ETG3HQ4JY4HNNZ84FBJM3CSC"}}';
        $http = new HttpClientFixed(new Response(201, [], $json));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);
        $now = new DateTimeImmutable("now");

        $this->assertEquals(
            new CallResult(true, false, 0, 0, [], null),
            $client->addEvent(Event::forUser("login", UserIdentified::byUserId("1"))->happenedAt($now))
        );

        $request = $http->getLastRequest();
        $this->assertInstanceOf(RequestInterface::class, $request);
        if ($request instanceof RequestInterface) {
            $request->getBody()->rewind();
            $body = $request->getBody()->getContents();
            $payload = json_decode($body, true);
            $this->assertEquals(
                [
                    "name" => "login",
                    "identification" => [
                        "user" => [
                            "userId" => "1"
                        ],
                    ],
                    "recordedAt" => $now->format(DATE_ATOM),
                ],
                $payload
            );
        }
    }

    public function test_it_triggers_event_for_user_with_metadata()
    {
        $factory = new Psr17Factory();
        $json = '{"message":"The data is correctly stored.","meta":{"status":201,"requestId":"01ETG3HQ4JY4HNNZ84FBJM3CSC"}}';
        $http = new HttpClientFixed(new Response(201, [], $json));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);
        $now = new DateTimeImmutable("now");

        $this->assertEquals(
            new CallResult(true, false, 0, 0, [], null),
            $client->addEvent(
                Event::forUser("login", UserIdentified::byUserId("1"))->withMetadata([
                    "number" => 1,
                    "string" => "string",
                    "boolean" => false,
                ])
            )
        );

        $request = $http->getLastRequest();
        $this->assertInstanceOf(RequestInterface::class, $request);
        if ($request instanceof RequestInterface) {
            $request->getBody()->rewind();
            $body = $request->getBody()->getContents();
            $payload = json_decode($body, true);
            $this->assertEquals(
                [
                    "name" => "login",
                    "identification" => [
                        "user" => [
                            "userId" => "1"
                        ],
                    ],
                    "metadata" => [
                        "number" => 1,
                        "string" => "string",
                        "boolean" => false,
                    ],
                ],
                $payload
            );
        }
    }

    public function test_it_triggers_event_for_account()
    {
        $factory = new Psr17Factory();
        $json = '{"message":"The data is correctly stored.","meta":{"status":201,"requestId":"01ETG3HQ4JY4HNNZ84FBJM3CSC"}}';
        $http = new HttpClientFixed(new Response(201, [], $json));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);

        $this->assertEquals(
            new CallResult(true, false, 0, 0, [], null),
            $client->addEvent(Event::forAccount("login", AccountIdentified::byAccountId("1")))
        );

        $request = $http->getLastRequest();
        $this->assertInstanceOf(RequestInterface::class, $request);
        if ($request instanceof RequestInterface) {
            $request->getBody()->rewind();
            $body = $request->getBody()->getContents();
            $payload = json_decode($body, true);
            $this->assertEquals(
                [
                    "name" => "login",
                    "identification" => [
                        "account" => [
                            "accountId" => "1"
                        ]
                    ],
                ],
                $payload
            );
        }
    }

    public function test_it_triggers_event_for_user_in_account()
    {
        $factory = new Psr17Factory();
        $json = '{"message":"The data is correctly stored.","meta":{"status":201,"requestId":"01ETG3HQ4JY4HNNZ84FBJM3CSC"}}';
        $http = new HttpClientFixed(new Response(201, [], $json));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);

        $this->assertEquals(
            new CallResult(true, false, 0, 0, [], null),
            $client->addEvent(Event::forUserInAccount("login", UserIdentified::byUserId("1"), AccountIdentified::byAccountId("1")))
        );

        $request = $http->getLastRequest();
        $this->assertInstanceOf(RequestInterface::class, $request);
        if ($request instanceof RequestInterface) {
            $request->getBody()->rewind();
            $body = $request->getBody()->getContents();
            $payload = json_decode($body, true);
            $this->assertEquals(
                [
                    "name" => "login",
                    "identification" => [
                        "user" => [
                            "userId" => "1"
                        ],
                        "account" => [
                            "accountId" => "1"
                        ],
                    ],
                ],
                $payload
            );
        }
    }

    public function test_it_deals_with_unexpected_error()
    {
        $factory = new Psr17Factory();
        $http = new HttpClientFixed(new Response(500, [], null));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);

        $this->assertEquals(
            new CallResult(false, false, 0, 0, ["something unexpected happened"], null),
            $client->addEvent(Event::forUser("login", UserIdentified::byUserId("1")))
        );
    }

    public function test_it_deals_with_unauthorized_error()
    {
        $factory = new Psr17Factory();
        $json = '{"message":"You are not authorized to \'GET\' the path \'/tracking/snippet\' with this API Key. You need the permission: GetTrackingSnippet.","meta":{"status":401,"requestId":"01ETJQH0D1GTPJ75BFGTZX2HR1"}}';
        $http = new HttpClientFixed(new Response(401, [], $json));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);

        $this->assertEquals(
            new CallResult(
                false,
                false,
                0,
                0,
                ['You are not authorized to \'GET\' the path \'/tracking/snippet\' with this API Key. You need the permission: GetTrackingSnippet.'],
                null
            ),
            $client->addEvent(Event::forUser("login", UserIdentified::byUserId("1")))
        );
    }

    public function test_it_adds_rate_limit_information()
    {
        $factory = new Psr17Factory();
        $json = '{"message":"The data is correctly stored.","meta":{"status":201,"requestId":"01ETG3HQ4JY4HNNZ84FBJM3CSC"}}';
        $http = new HttpClientFixed(new Response(
            201,
            ["x-ratelimit-remaining" => "1999", "x-ratelimit-limit" => "2000"],
            $json
        ));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);

        $this->assertEquals(
            new CallResult(true, false, 1999, 2000, [], null),
            $client->addEvent(Event::forUser("login", UserIdentified::byUserId("1")))
        );
    }

    public function test_it_knows_when_rate_limited()
    {
        $factory = new Psr17Factory();
        $json = '{"message":"The data is correctly stored.","meta":{"status":201,"requestId":"01ETG3HQ4JY4HNNZ84FBJM3CSC"}}';
        $http = new HttpClientFixed(new Response(429, [], $json));
        $client = new Client($http, $factory, $factory, ["apiKey" => "key"]);

        $this->assertEquals(
            new CallResult(false, true, 0, 0, ["rate limited"], null),
            $client->addEvent(Event::forUser("login", UserIdentified::byUserId("1")))
        );
    }
}
