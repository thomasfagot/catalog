<?php

namespace App\Service;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class VideoScraper
{
    public function __construct(
        private HttpClientInterface $httpClient
    ) {}

    public function scrape(string $url): array
    {
        $response = $this->httpClient->request('GET', $url);
        $content = $response->getContent();

        $crawler = new Crawler($content);

        $title = $this->getMeta($crawler, 'og:title') ?? $this->getTitle($crawler);
        $description = $this->getMeta($crawler, 'og:description') ?? $this->getMeta($crawler, 'description');
        $image = $this->getMeta($crawler, 'og:image');

        return [
            'url' => $url,
            'title' => trim($title ?? ''),
            'description' => trim($description ?? ''),
            'image' => $image,
        ];
    }

    private function getTitle(Crawler $crawler): ?string
    {
        try {
            return $crawler->filter('title')->first()->text();
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function getMeta(Crawler $crawler, string $property): ?string
    {
        $nodes = $crawler->filter("meta[property=\"$property\"], meta[name=\"$property\"]");
        if ($nodes->count() > 0) {
            return $nodes->first()->attr('content');
        }
        return null;
    }
}
