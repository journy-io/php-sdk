<?php declare(strict_types=1);

namespace JournyIO\SDK;

final class TrackingSnippet
{
    private $domain;
    private $snippet;

    public function __construct(string $domain, string $snippet)
    {
        $this->domain = $domain;
        $this->snippet = $snippet;
    }

    public function getDomain()
    {
        return $this->domain;
    }

    public function getSnippet()
    {
        return $this->snippet;
    }
}
