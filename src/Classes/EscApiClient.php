<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Classes;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Hawb\EscrApiConnector\Exceptions\EscApiException;

final readonly class EscApiClient
{
    private Client $httpClient;
    private EscConfig $config;

    public function __construct(
        EscConfig $config,
        ?Client $httpClient = null
    )
    {
        $this->config = $config;

        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => rtrim($this->config->baseUrl, '/') . '/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config->apiToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
            'http_errors' => false,
        ]);
    }

    public function listCardholders(int $page = 0, int $size = 20, string $search = '', string $searchField = 'fullName'): array
    {
        try {
            $query = ['page' => $page, 'size' => $size, 'direction' => 'ASC'];

            if ($search !== '') {
                $query['search'] = "{$searchField}~{$search}";
            }

            $response = $this->httpClient->get('api/v2/persons', ['query' => $query]);

            return json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (GuzzleException $e) {
            throw new EscApiException(
                'HTTP error while fetching persons: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function getCardholder(string $esi): array
    {
        try {
            $response = $this->httpClient->get('api/v2/persons/' . rawurlencode($esi));
            $result = ['status_code' => $response->getStatusCode(), ...json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR)];
            return $result;
        } catch (GuzzleException $e) {
            throw new EscApiException(
                "HTTP error while fetching person {$esi}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function cardholderExists(string $esi): bool
    {
        $response = $this->getCardholder($esi);
        return $response['status_code'] === 200;
    }

    public function createCardholder(Cardholder $cardholder, string $esi): void
    {
        try {
            $this->httpClient->post('api/v2/persons', [
                'json' => $this->buildCardholderPayload($cardholder, $esi),
            ]);
        } catch (GuzzleException $e) {
            throw new EscApiException(
                "HTTP error while creating person {$esi}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function updateCardholder(Cardholder $cardholder, string $esi): void
    {
        try {
            $this->httpClient->put("api/v2/persons/{$esi}", [
                'json' => $this->buildCardholderPayload($cardholder, $esi),
            ]);
        } catch (GuzzleException $e) {
            throw new EscApiException(
                "HTTP error while updating person {$esi}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function cardExistsForCardholder(string $esi): bool
    {
        try {
            $result = $this->listCards(search: $esi, searchField: 'person.identifier', size: 1);

            return $result['page']['totalElements'] > 0;
        } catch (EscApiException $e) {
            throw new EscApiException(
                "Error checking cards for person {$esi}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function listCards(int $page = 0, int $size = 20, string $search = '', string $searchField = 'displayName'): array
    {
        try {
            $query = ['page' => $page, 'size' => $size, 'direction' => 'ASC'];

            if ($search !== '') {
                $query['search'] = "{$searchField}~{$search}";
            }

            $response = $this->httpClient->get('api/v2/cards', ['query' => $query]);

            return json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (GuzzleException $e) {
            throw new EscApiException(
                'HTTP error while fetching cards: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function getCard(string $escn): array
    {
        try {
            $response = $this->httpClient->get("api/v2/cards/{$escn}");

            return json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (GuzzleException $e) {
            throw new EscApiException(
                "HTTP error while fetching card {$escn}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function getQrCodeSvgByEsi(string $esi): ?string
    {
        if ($esi === '' || !$this->cardholderExists($esi)) {
            return null;
        }

        $cards = $this->listCards(search: $esi, searchField: 'person.identifier', size: 1);
        $cardNumber = $cards['content'][0]['cardNumber'] ?? null;
        if (!is_string($cardNumber) || $cardNumber === '') {
            return null;
        }

        return $this->getCardQrCode($cardNumber);
    }

    public function getCardQrCode(string $escn): string
    {
        try {
            $response = $this->httpClient->get('api/v2/cards/' . rawurlencode($escn) . '/qr', [
                'headers' => [
                    'Accept' => 'image/svg+xml',
                ],
                'query' => [
                    'orientation' => 'horizontal',
                    'colours' => 'normal',
                    'size' => 'M',
                ],
            ]);

            return $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            throw new EscApiException(
                "HTTP error while fetching QR code for card {$escn}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function createCard(Cardholder $cardholder, string $esi): void
    {
        $issuedAt = date('Y-m-d');

        try {
            $this->httpClient->post('api/v2/cards', [
                'json' => [
                    'personIdentifier' => $esi,
                    'issuerIdentifier' => $this->config->organisationPic,
                    'cardType' => $cardholder->cardType->value,
                    'cardStatusType' => 'ACTIVE',
                    'issuedAt' => $issuedAt,
                    'expiresAt' => $cardholder->cardExpiresAt,
                    'displayName' => 'Card of ' . $cardholder->fullName,
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new EscApiException(
                "HTTP error while creating card for {$esi}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function deleteCardsForCardholder(string $esi): void
    {
        $cardNumbers = [];
        $page = 0;

        do {
            $result = $this->listCards(
                page: $page,
                size: 100,
                search: $esi,
                searchField: 'person.identifier',
            );

            foreach ($result['content'] ?? [] as $card) {
                $cardNumber = $card['cardNumber'] ?? null;

                if (is_string($cardNumber) && $cardNumber !== '') {
                    $cardNumbers[] = $cardNumber;
                }
            }

            $totalPages = (int) ($result['page']['totalPages'] ?? 1);
            $page++;
        } while ($page < $totalPages);

        foreach (array_unique($cardNumbers) as $cardNumber) {
            $this->deleteCard($cardNumber);
        }
    }

    public function deleteCard(string $escn): void
    {
        try {
            $this->httpClient->delete('api/v2/cards/' . rawurlencode($escn));
        } catch (GuzzleException $e) {
            throw new EscApiException(
                "HTTP error while deleting card {$escn}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function deleteCardholder(string $esi): void
    {
        try {
            $this->httpClient->delete('api/v2/persons/' . rawurlencode($esi));
        } catch (GuzzleException $e) {
            throw new EscApiException(
                "HTTP error while deleting person {$esi}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    private function buildCardholderPayload(Cardholder $cardholder, string $esi): array
    {
        return [
            'identifier' => $esi,
            'identifierCode' => 'ESI',
            'personOrganisationUpdateViews' => [
                [
                    'organisationIdentifier' => $this->config->organisationPic,
                    'fullName' => $cardholder->fullName,
                    'email' => $cardholder->email,
                    'academicLevel' => $cardholder->academicLevel->value,
                ],
            ],
        ];
    }

}
