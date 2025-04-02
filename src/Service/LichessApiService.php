<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LichessApiService
{
    private readonly LoggerInterface $logger;

    private string $apiUrl;
    private string $explorerUrl;

    private $variant = 'standard';
    private $speeds = ['rapid'];
    private $ratings = [1600, 1800];
    private $sinceYear = '2021';
    private $untilYear = '2024';

    public function __construct(
        private readonly HttpClientInterface $client,
        LoggerInterface $lichessApiLogger,
    ) {
        $this->apiUrl = "https://lichess.org/api";
        $this->explorerUrl = "https://explorer.lichess.ovh";
        $this->logger = $lichessApiLogger;
    }

    public function getMastersMoves(string $fen, ?int $since = null, ?int $until = null)
    {
        $this->logger->info("Downloading masters moves from fen $fen");

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
            unset($content['moves']);
            array_unshift($responseMoves, ['san' => '-', ...$content]);

            return $responseMoves;
        } else {
            $this->logger->error(sprintf("Problem downloading masters moves : HTTP status code %d", $response->getStatusCode()));
        }
    }

    public function getAmateursMoves(string $fen, ?string $variant = null, ?array $speeds = null, ?array $ratings = null, ?int $since = null, ?int $until = null)
    {
        $this->logger->info("Downloading amateurs moves from fen $fen");

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
            unset($content['moves']);
            array_unshift($responseMoves, ['san' => '-', ...$content]);

            return $responseMoves;
        } else {
            $this->logger->error(sprintf("Problem downloading amateurs moves : HTTP status code %d", $response->getStatusCode()));
        }
    }

    /**
     * @return null|array{cp:?int,mate:?int}
     */
    public function getEvaluation(string $fen)
    {
        $this->logger->info('getLichessMoves ' . $fen);

        $url = $this->apiUrl . '/cloud-eval';

        $query = [
            'fen' => $fen,
            'multiPv' => 0,
        ];

        $response = $this->client->request('GET', $url, [
            'query' => $query,
        ]);

        if ($response->getStatusCode() === 200) {

            $content = $response->toArray();

            $variation = reset($content['pvs']);

            if ($variation !== false) {

                $evaluation = [
                    'cp' => $variation['cp'] ?? null,
                    'mate' => $variation['mate'] ?? null,
                ];

                return $evaluation;
            }
        } else {
            $this->logger->error(sprintf("Problem downloading evaluation : HTTP status code %d", $response->getStatusCode()));
        }
    }
}
