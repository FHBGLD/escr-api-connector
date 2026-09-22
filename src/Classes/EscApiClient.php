<?php

declare(strict_types=1);

namespace Hawb\MemberCard\Classes;

use DOMDocument;
use DOMElement;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Hawb\EscrApiConnector\Contracts\EscApiLoggerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Thin wrapper around the ESC Router REST API v2.
 *
 * @see https://router.europeanstudentcard.eu/esc-rest/v3/api-docs/V2
 */
final readonly class EscApiClient
{
    private const QR_CODE_RENDER_SIZE = 240.0;

    private Client $http;
    private EscConfig $config;

    public function __construct(
        ?Client $http = null,
        ?EscConfig $config = null,
        ?EscApiLoggerInterface $logger = null,
    )
    {
        $this->config = $config ?? new EscConfig(
            apiToken: $_ENV['ESC_API_TOKEN'],
            baseUrl: $_ENV['ESC_API_BASE_URL'],
            organisationPic: $_ENV['ESC_ORGANISATION_PIC'],
            countryCode: $_ENV['ESC_COUNTRY_CODE'],
        );

        $http ??= new Client([
            'base_uri' => rtrim($this->config->baseUrl, '/') . '/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config->apiToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        $this->http = $logger === null ? $http : $this->withLogging($http, $logger);
    }

    private function withLogging(Client $http, EscApiLoggerInterface $logger): Client
    {
        $clientConfig = $http->getConfig();
        $handler = $clientConfig['handler'] ?? null;
        $stack = $handler instanceof HandlerStack
            ? clone $handler
            : HandlerStack::create(is_callable($handler) ? $handler : null);

        $stack->push($this->loggingMiddleware($logger), 'escr_daily_logger');
        $clientConfig['handler'] = $stack;

        return new Client($clientConfig);
    }

    private function loggingMiddleware(EscApiLoggerInterface $logger): callable
    {
        return static function (callable $handler) use ($logger): callable {
            return static function (RequestInterface $request, array $options) use ($handler, $logger): PromiseInterface {
                $startedAt = hrtime(true);

                try {
                    $promise = $handler($request, $options);
                } catch (Throwable $error) {
                    self::logExchange($logger, $request, null, $error, $startedAt);
                    throw $error;
                }

                return $promise->then(
                    static function (ResponseInterface $response) use ($logger, $request, $startedAt): ResponseInterface {
                        self::logExchange($logger, $request, $response, null, $startedAt);

                        return $response;
                    },
                    static function (mixed $reason) use ($logger, $request, $startedAt): PromiseInterface {
                        $error = Create::exceptionFor($reason);
                        $response = $error instanceof ResponseException
                            ? $error->getResponse()
                            : null;
                        self::logExchange($logger, $request, $response, $error, $startedAt);

                        return Create::rejectionFor($reason);
                    },
                );
            };
        };
    }

    private static function logExchange(
        EscApiLoggerInterface $logger,
        RequestInterface $request,
        ?ResponseInterface $response,
        ?Throwable $error,
        int $startedAt,
    ): void {
        try {
            $logger->log(
                $request,
                $response,
                $error,
                (hrtime(true) - $startedAt) / 1_000_000,
            );
        } catch (Throwable $loggingFailure) {
            error_log('Unable to log ESCR exchange: ' . $loggingFailure->getMessage());
        }
    }

    /**
     * Build the full URL that listPersons would request, including all query parameters.
     */
    public function buildListPersonsUrl(int $page = 0, int $size = 20, string $search = '', string $searchField = 'fullName'): string
    {
        $query = ['page' => $page, 'size' => $size, 'direction' => 'ASC'];

        if ($search !== '') {
            $query['search'] = "{$searchField}~{$search}";
        }

        $base = rtrim($this->config->baseUrl, '/') . '/api/v2/persons';

        return $base . '?' . http_build_query($query);
    }

    /**
     * Fetch a paginated list of persons from the ESC API.
     *
     * @return array{content: list<array<string, mixed>>, page: array{number: int, size: int, totalElements: int, totalPages: int}}
     */
    public function listPersons(int $page = 0, int $size = 20, string $search = '', string $searchField = 'fullName'): array
    {
        try {
            $query = ['page' => $page, 'size' => $size, 'direction' => 'ASC'];

            if ($search !== '') {
                $query['search'] = "{$searchField}~{$search}";
            }

            $response = $this->http->get('api/v2/persons', ['query' => $query]);

            /** @var array{content: list<array<string, mixed>>, page: array{number: int, size: int, totalElements: int, totalPages: int}} $decoded */
            return json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (GuzzleException $e) {
            throw new EscApiException(
                'HTTP error while fetching persons: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Build the full URL that getPerson would request.
     */
    public function buildPersonUrl(string $esi): string
    {
        return rtrim($this->config->baseUrl, '/') . '/api/v2/persons/' . rawurlencode($esi);
    }

    /**
     * Fetch a single person by ESI from the ESC API.
     *
     * @return array<string, mixed>
     */
    public function getPerson(string $esi): array
    {
        try {
            $response = $this->http->get('api/v2/persons/' . rawurlencode($esi));

            /** @var array<string, mixed> $decoded */
            return json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();

            if ($status === 404) {
                throw new EscApiException("Person not found: {$esi}", previous: $e);
            }

            throw new EscApiException(
                "Failed to fetch person {$esi} (HTTP {$status}): " . $e->getMessage(),
                previous: $e,
            );
        } catch (GuzzleException $e) {
            throw new EscApiException(
                "HTTP error while fetching person {$esi}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Check whether a person already exists in the ESC API.
     */
    public function personExists(string $esi): bool
    {
        try {
            $this->http->get('api/v2/persons/' . rawurlencode($esi));

            return true;
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return false;
            }

            throw new EscApiException(
                "Unexpected response while checking person {$esi}: " . $e->getMessage(),
                previous: $e,
            );
        } catch (GuzzleException $e) {
            throw new EscApiException(
                "HTTP error while checking person {$esi}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Create a new person (student) in the ESC API.
     */
    public function createPerson(Student $student, string $esi): void
    {
        try {
            $this->http->post('api/v2/persons', [
                'json' => $this->buildPersonPayload($student, $esi),
            ]);
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();

            // 409 = already exists – treat as a no-op
            if ($status === 409) {
                return;
            }

            throw new EscApiException(
                "Failed to create person {$esi} (HTTP {$status}): " . $e->getMessage(),
                previous: $e,
            );
        } catch (GuzzleException $e) {
            throw new EscApiException(
                "HTTP error while creating person {$esi}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Update an existing person (student) in the ESC API.
     */
    public function updatePerson(Student $student, string $esi): void
    {
        try {
            $this->http->put("api/v2/persons/{$esi}", [
                'json' => $this->buildPersonPayload($student, $esi),
            ]);
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();

            throw new EscApiException(
                "Failed to update person {$esi} (HTTP {$status}): " . $e->getMessage(),
                previous: $e,
            );
        } catch (GuzzleException $e) {
            throw new EscApiException(
                "HTTP error while updating person {$esi}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Check whether a card already exists for the given ESI.
     */
    public function cardExistsForPerson(string $esi): bool
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

    /**
     * Build the full URL that listCards would request, including all query parameters.
     */
    public function buildListCardsUrl(int $page = 0, int $size = 20, string $search = '', string $searchField = 'displayName'): string
    {
        $query = ['page' => $page, 'size' => $size, 'direction' => 'ASC'];

        if ($search !== '') {
            $query['search'] = "{$searchField}~{$search}";
        }

        $base = rtrim($this->config->baseUrl, '/') . '/api/v2/cards';

        return $base . '?' . http_build_query($query);
    }

    /**
     * Build the full URL that getCard would request.
     */
    public function buildCardUrl(string $escn): string
    {
        return rtrim($this->config->baseUrl, '/') . '/api/v2/cards/' . rawurlencode($escn);
    }

    /**
     * Build the full URL that getCardQrCode would request.
     */
    public function buildCardQrCodeUrl(string $escn): string
    {
        return rtrim($this->config->baseUrl, '/') . '/api/v2/cards/' . rawurlencode($escn) . '/qr';
    }

    /**
     * Fetch a paginated list of cards from the ESC API.
     *
     * @return array{content: list<array<string, mixed>>, page: array{number: int, size: int, totalElements: int, totalPages: int}}
     */
    public function listCards(int $page = 0, int $size = 20, string $search = '', string $searchField = 'displayName'): array
    {
        try {
            $query = ['page' => $page, 'size' => $size, 'direction' => 'ASC'];

            if ($search !== '') {
                $query['search'] = "{$searchField}~{$search}";
            }

            $response = $this->http->get('api/v2/cards', ['query' => $query]);

            /** @var array{content: list<array<string, mixed>>, page: array{number: int, size: int, totalElements: int, totalPages: int}} $decoded */
            return json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (GuzzleException $e) {
            throw new EscApiException(
                'HTTP error while fetching cards: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Fetch a single card by ESCN from the ESC API.
     *
     * @return array<string, mixed>
     */
    public function getCard(string $escn): array
    {
        try {
            $response = $this->http->get("api/v2/cards/{$escn}");

            /** @var array<string, mixed> $decoded */
            return json_decode((string) $response->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();

            if ($status === 404) {
                throw new EscApiException("Card not found: {$escn}", previous: $e);
            }

            throw new EscApiException(
                "Failed to fetch card {$escn} (HTTP {$status}): " . $e->getMessage(),
                previous: $e,
            );
        } catch (GuzzleException $e) {
            throw new EscApiException(
                "HTTP error while fetching card {$escn}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Fetch the first ESC QR code assigned to a person.
     */
    public function getQrCodeSvgByEsi(string $esi): ?string
    {
        if ($esi === '' || !$this->personExists($esi)) {
            return null;
        }

        $cards = $this->listCards(search: $esi, searchField: 'person.identifier', size: 1);
        $cardNumber = $cards['content'][0]['cardNumber'] ?? null;

        if (!is_string($cardNumber) || $cardNumber === '') {
            return null;
        }

        return $this->getCardQrCode($cardNumber);
    }

    /**
     * Fetch an SVG QR code for a card from the ESC API.
     */
    public function getCardQrCode(string $escn): string
    {
        try {
            $response = $this->http->get('api/v2/cards/' . rawurlencode($escn) . '/qr', [
                'headers' => [
                    'Accept' => 'image/svg+xml',
                ],
                'query' => [
                    'orientation' => 'horizontal',
                    'colours' => 'normal',
                    'size' => 'M',
                ],
            ]);

            return $this->resizeQrCodeSvg(trim((string) $response->getBody()));
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();

            throw new EscApiException(
                "Failed to fetch QR code for card {$escn} (HTTP {$status}): " . $e->getMessage(),
                previous: $e,
            );
        } catch (GuzzleException $e) {
            throw new EscApiException(
                "HTTP error while fetching QR code for card {$escn}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Create a new card for a student in the ESC API.
     *
     * The card number (ESCN) is left blank so the API auto-generates it.
     */
    public function createCard(Student $student, string $esi): void
    {
        $issuedAt = date('Y-m-d');

        try {
            $this->http->post('api/v2/cards', [
                'json' => [
                    'personIdentifier' => $esi,
                    'issuerIdentifier' => $this->config->organisationPic,
                    'cardType' => $student->cardType->value,
                    'cardStatusType' => 'ACTIVE',
                    'issuedAt' => $issuedAt,
                    'expiresAt' => $student->cardExpiresAt,
                    'displayName' => 'Card of ' . $student->fullName,
                ],
            ]);
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();

            // 409 = card already exists – treat as a no-op
            if ($status === 409) {
                return;
            }

            throw new EscApiException(
                "Failed to create card for {$esi} (HTTP {$status}): " . $e->getMessage(),
                previous: $e,
            );
        } catch (GuzzleException $e) {
            throw new EscApiException(
                "HTTP error while creating card for {$esi}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Delete every card assigned to a person.
     */
    public function deleteCardsForPerson(string $esi): void
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

    /**
     * Delete a card by its ESCN (card number).
     */
    public function deleteCard(string $escn): void
    {
        try {
            $this->http->delete('api/v2/cards/' . rawurlencode($escn));
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();

            if ($status === 404) {
                return;
            }

            throw new EscApiException(
                "Failed to delete card {$escn} (HTTP {$status}): " . $e->getMessage(),
                previous: $e,
            );
        } catch (GuzzleException $e) {
            throw new EscApiException(
                "HTTP error while deleting card {$escn}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Delete a person (student) by ESI.
     */
    public function deletePerson(string $esi): void
    {
        try {
            $this->http->delete('api/v2/persons/' . rawurlencode($esi));
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();

            if ($status === 404) {
                return;
            }

            throw new EscApiException(
                "Failed to delete person {$esi} (HTTP {$status}): " . $e->getMessage(),
                previous: $e,
            );
        } catch (GuzzleException $e) {
            throw new EscApiException(
                "HTTP error while deleting person {$esi}: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Build the PersonUpdateView payload expected by the ESC API.
     *
     * @return array<string, mixed>
     */
    private function buildPersonPayload(Student $student, string $esi): array
    {
        return [
            'identifier' => $esi,
            'identifierCode' => 'ESI',
            'personOrganisationUpdateViews' => [
                [
                    'organisationIdentifier' => $this->config->organisationPic,
                    'fullName' => $student->fullName,
                    'email' => $student->email,
                    'academicLevel' => $student->academicLevel->value,
                ],
            ],
        ];
    }

    private function resizeQrCodeSvg(string $svg): string
    {
        $document = new DOMDocument();
        $previousErrorHandling = libxml_use_internal_errors(true);

        try {
            if (!$document->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS)) {
                return $svg;
            }

            $root = $document->documentElement;
            if (!$root instanceof DOMElement || $root->localName !== 'svg') {
                return $svg;
            }

            $parts = [];
            foreach ($root->childNodes as $child) {
                if ($child instanceof DOMElement && $child->localName === 'svg') {
                    $parts[] = $child;
                }
            }

            if (count($parts) < 2) {
                return $svg;
            }

            [$qrCode, $logo] = $parts;
            $logoAspectRatio = $this->svgAspectRatio($logo);
            if ($logoAspectRatio === null) {
                return $svg;
            }

            $qrCodeSize = self::QR_CODE_RENDER_SIZE;
            $logoWidth = $qrCodeSize * $logoAspectRatio;
            $totalWidth = $qrCodeSize + $logoWidth;
            $formattedQrCodeSize = $this->svgNumber($qrCodeSize);
            $formattedLogoWidth = $this->svgNumber($logoWidth);
            $formattedTotalWidth = $this->svgNumber($totalWidth);

            $root->setAttribute('width', $formattedTotalWidth);
            $root->setAttribute('height', $formattedQrCodeSize);
            $root->setAttribute('viewBox', "0 0 {$formattedTotalWidth} {$formattedQrCodeSize}");
            $root->setAttribute('preserveAspectRatio', 'xMinYMin meet');

            $qrCode->setAttribute('x', '0');
            $qrCode->setAttribute('y', '0');
            $qrCode->setAttribute('width', $formattedQrCodeSize);
            $qrCode->setAttribute('height', $formattedQrCodeSize);

            $logo->setAttribute('x', $formattedQrCodeSize);
            $logo->setAttribute('y', '0');
            $logo->setAttribute('width', $formattedLogoWidth);
            $logo->setAttribute('height', $formattedQrCodeSize);

            $resizedSvg = $document->saveXML($root);

            return is_string($resizedSvg) ? $resizedSvg : $svg;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorHandling);
        }
    }

    private function svgAspectRatio(DOMElement $element): ?float
    {
        $viewBox = preg_split('/\s+/', trim($element->getAttribute('viewBox')));
        if (is_array($viewBox) && count($viewBox) === 4) {
            $width = (float) $viewBox[2];
            $height = (float) $viewBox[3];
        } else {
            $width = (float) $element->getAttribute('width');
            $height = (float) $element->getAttribute('height');
        }

        return $width > 0.0 && $height > 0.0 ? $width / $height : null;
    }

    private function svgNumber(float $number): string
    {
        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');
    }
}
