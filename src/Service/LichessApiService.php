<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class LichessApiService
{
    private string $apiUrl;
    private string $explorerUrl;

    private $variant = 'standard';
    private $speeds = ['rapid'];
    private $ratings = [1600, 1800];
    private $sinceYear = '2021';
    private $untilYear = '2024';

    public function __construct(
        private readonly HttpClientInterface $client,
    ) {
        $this->apiUrl = "https://lichess.org/api";
        $this->explorerUrl = "https://explorer.lichess.ovh";
    }

    public function getMastersMoves(string $fen, int $since = null, int $until = null)
    {
        $url = $this->explorerUrl . '/masters';

        $query = [
            'fen' => $fen,
            'since' => $since ?? $this->sinceYear,
            'until' => $until ?? $this->untilYear,
            'moves' => 250,
            'topGames' => 0,
        ];

        $response = $this->client->request('GET', $url, [
            'query' => $query,
        ]);

        if ($response->getStatusCode() === 200) {

            $content = $response->toArray();
            $responseMoves = $content['moves'];
            array_unshift($responseMoves, ['san' => '-', ...$content]);

            return $responseMoves;
        }
    }

    public function getLichessMoves(string $fen, string $variant = null, array $speeds = null, array $ratings = null, int $since = null, int $until = null)
    {
        $url = $this->explorerUrl . '/lichess';

        $speeds = $speeds ?? $this->speeds;
        $ratings = $ratings ?? $this->ratings;

        $query = [
            'variant' => $variant ?? $this->variant,
            'fen' => $fen,
            'speeds' => implode(',', $speeds),
            'ratings' => implode(',', $ratings),
            'since' => $since ?? "$this->sinceYear-01",
            'until' => $until ?? "$this->untilYear-12",
            'moves' => 250,
            'topGames' => 0,
            'recentGames' => 0,
        ];

        $response = $this->client->request('GET', $url, [
            'query' => $query,
        ]);

        if ($response->getStatusCode() === 200) {

            $content = $response->toArray();
            $responseMoves = $content['moves'];
            array_unshift($responseMoves, ['san' => '-', ...$content]);

            return $responseMoves;
        }
    }
}
