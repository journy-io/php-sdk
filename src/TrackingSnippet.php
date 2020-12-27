<?php declare(strict_types=1);

namespace JournyIO\SDK;

final class TrackingSnippet implements ReturnValue
{
    private $domain;
    private $snippet;

    public function __construct(string $domain, string $snippet)
    {
        $this->domain = $domain;
        $this->snippet = $snippet;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getSnippet(): string
    {
        return $this->snippet;
    }
}
