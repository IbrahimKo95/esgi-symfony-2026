<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Consomme l'API externe de taux de change Frankfurter (https://frankfurter.dev)
 * pour convertir un montant d'une devise vers une autre.
 */
class ExchangeRateService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl = 'https://api.frankfurter.dev/v1',
    ) {
    }

    /**
     * @throws ExchangeRateUnavailableException si l'API externe est injoignable ou renvoie une reponse invalide
     */
    public function getRate(string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        try {
            $response = $this->httpClient->request('GET', $this->baseUrl.'/latest', [
                'query' => ['base' => $from, 'symbols' => $to],
            ]);

            $data = $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new ExchangeRateUnavailableException('Impossible de recuperer le taux de change aupres du service externe.', previous: $e);
        }

        if (!isset($data['rates'][$to])) {
            throw new ExchangeRateUnavailableException(sprintf('Taux de change indisponible pour %s -> %s.', $from, $to));
        }

        return (float) $data['rates'][$to];
    }

    public function convert(float $amount, string $from, string $to): float
    {
        return round($amount * $this->getRate($from, $to), 2);
    }
}
