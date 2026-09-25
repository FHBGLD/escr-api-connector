<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Tests\Classes;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\TransferException;
use Hawb\EscrApiConnector\Classes\EscApiClient;
use Hawb\EscrApiConnector\Classes\EscConfig;
use Hawb\EscrApiConnector\Classes\Cardholder;
use Hawb\EscrApiConnector\Enums\AcademicLevel;
use Hawb\EscrApiConnector\Exceptions\EscApiException;
use PHPUnit\Framework\Attributes\Group;

class EscApiClientTest extends TestCase {

    private function createEscConfig(): EscConfig {
        return (new EscConfig(
            'a',
            'b',
            'c',
            'd',
            240.0
        ));
    }

    private function createFakeCardholder(): Cardholder {
        return new Cardholder(
            'abc123',
            'Didi Demo',
            'dididemo@hochschule-burgenland.at',
            AcademicLevel::BACHELOR,
            '2027-09-01'
        );
    }

    private function createApiClient(array $responses): EscApiClient {
        $clientMock = $this->createHttpClient(
            new MockHandler($responses)
        );

        return new EscApiClient($this->createEscConfig(), $clientMock);
    }

    private function createHttpClient(MockHandler $mock): Client {
        $handlerStack = HandlerStack::create($mock);
        return new Client([
            'http_errors' => false,
            'handler' => $handlerStack
        ]);
    }

    public static function validEsiProvider(): array {
        return [
            'urn:schac:personalUniqueCode:int:esi:AT:1910783001'
        ];
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testEscApiClientClassExists(): void {
        $this->assertTrue(file_exists('src/Classes/EscApiClient.php'));
        $this->assertTrue(class_exists('Hawb\EscrApiConnector\Classes\EscApiClient'));
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testListCardholdersSucceeds(): void {
        $apiClient = $this->createApiClient([new Response(200, [],  json_encode(['List persons']))]);
        $result = $apiClient->listCardholders();
        $this->assertIsArray($result);
        $this->assertContains('List persons', $result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testListCardholdersWithSearchSucceeds(): void {
        $apiClient = $this->createApiClient([new Response(200, [],  json_encode(['List persons with search']))]);
        $result = $apiClient->listCardholders(search: 'Demo');
        $this->assertIsArray($result);
        $this->assertContains('List persons with search', $result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testListCardholdersThrowsEscApiException(): void {
        $this->expectException(EscApiException::class);
        $this->expectExceptionMessageIsOrContains('HTTP error while fetching persons: ');
        $apiClient = $this->createApiClient([new TransferException('Error communicating', new Request('GET', 'test'))]);
        $apiClient->listCardholders();
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testCardholderExistencyCheckWorksWithExistingCardholder(): void {
        $esi = 'abc';
        $apiClient = $this->createApiClient([new Response(200, [], json_encode(['one element']))]);
        $result = $apiClient->cardholderExists($esi);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testCardholderExistencyCheckThrowsException(): void {
        $this->expectException(EscApiException::class);
        $this->expectExceptionMessageIsOrContains('HTTP error while fetching person ');
        $esi = 'abc';
        $apiClient = $this->createApiClient([new TransferException('Error communicating', new Request('GET', 'test'))]);
        $apiClient->cardholderExists($esi);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testCardholderExistencyCheckWorksWithNonExistingCardholder(): void {
        $esi = 'abc';
        $apiClient = $this->createApiClient([new Response(404, [], json_encode([]))]);
        $result = $apiClient->cardholderExists($esi);
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testCardholderRequestSucceeds(): void {
        $esi = 'abc';
        $apiClient = $this->createApiClient([new Response(200, [], json_encode([$esi]))]);
        $result = $apiClient->getCardholder($esi);
        $this->assertIsArray($result);
        $this->assertContains($esi, $result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testCreateCardholderSucceeds(): void {
        $cardholder = $this->createFakeCardholder();
        $esi = 'abc';
        $apiClient = $this->createApiClient([new Response(200, [], json_encode([$esi]))]);
        $result = $apiClient->createCardholder($cardholder, $esi);
        $this->assertNull($result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testCreateCardholderThrowsException(): void {
        $this->expectException(EscApiException::class);
        $this->expectExceptionMessageIsOrContains('HTTP error while creating person ');
        $cardholder = $this->createFakeCardholder();
        $esi = 'abc';
        $apiClient = $this->createApiClient([new TransferException('Error communicating', new Request('GET', 'test'))]);
        $apiClient->createCardholder($cardholder, $esi);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testUpdateCardholderSucceeds(): void {
        $cardholder = $this->createFakeCardholder();
        $esi = 'abc';
        $apiClient = $this->createApiClient([new Response(200, [], json_encode([$esi]))]);
        $result = $apiClient->updateCardholder($cardholder, $esi);
        $this->assertNull($result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testUpdateCardholderThrowsException(): void {
        $this->expectException(EscApiException::class);
        $this->expectExceptionMessageIsOrContains('HTTP error while updating person ');
        $cardholder = $this->createFakeCardholder();
        $esi = 'abc';
        $apiClient = $this->createApiClient([new TransferException('Error communicating', new Request('GET', 'test'))]);
        $apiClient->updateCardholder($cardholder, $esi);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testCardExistsForCardholderReturnsTrue(): void {
        $esi = 'abc';
        $apiClient = $this->createApiClient([new Response(200, [], json_encode(['page' => ['totalElements' => 1]]))]);
        $result = $apiClient->cardExistsForCardholder($esi);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testCardExistForCardholderReturnsFalse(): void {
        $esi = 'abc';
        $apiClient = $this->createApiClient([new Response(200, [], json_encode(['page' => ['totalElements' => 0]]))]);
        $result = $apiClient->cardExistsForCardholder($esi);
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testCardExistForCardholderThrowsException(): void {
        $this->expectException(EscApiException::class);
        $this->expectExceptionMessageIsOrContains('Error checking cards for person ');
        $esi = 'abc';
        $apiClient = $this->createApiClient([new TransferException('Error communicating', new Request('GET', 'test'))]);
        $apiClient->cardExistsForCardholder($esi);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testListCardsSucceeds(): void {
        $apiClient = $this->createApiClient([new Response(200, [], json_encode(['List cards']))]);
        $result = $apiClient->listCards();
        $this->assertIsArray($result);
        $this->assertContains('List cards', $result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testListCardsThrowsException(): void {
        $this->expectException(EscApiException::class);
        $this->expectExceptionMessageIsOrContains('HTTP error while fetching cards: ');
        $apiClient = $this->createApiClient([new TransferException('Error communicating', new Request('GET', 'test'))]);
        $apiClient->listCards();
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testGetCardSucceeds(): void {
        $escn = 'abc';
        $apiClient = $this->createApiClient([new Response(200, [], json_encode([$escn]))]);
        $result = $apiClient->getCard($escn);
        $this->assertIsArray($result);
        $this->assertContains($escn, $result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testGetCardFails(): void {
        $escn = 'abc';
        $apiClient = $this->createApiClient([new Response(404, [], json_encode([]))]);
        $result = $apiClient->getCard($escn);
        $this->assertIsArray($result);
        $this->assertNotContains($escn, $result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testGetCardThrowsException(): void {
        $this->expectException(EscApiException::class);
        $this->expectExceptionMessageIsOrContains('HTTP error while fetching card ');
        $escn = 'abc';
        $apiClient = $this->createApiClient([new TransferException('Error communicating', new Request('GET', 'test'))]);
        $apiClient->getCard($escn);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testGetQrCodeSvgByEsiSucceeds(): void {
        $esi = 'abc';
        $responses = [
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['one element'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['content' => [0 => ['cardNumber' => 'card-number']]])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['svg_result']))
        ];
        $apiClient = $this->createApiClient($responses);
        $result = $apiClient->getQrCodeSvgByEsi($esi);
        $this->assertIsString($result);
        $this->assertStringContainsString('svg_result', $result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testGetQrCodeSvgByEsiWithoutResultSucceeds(): void {
        $esi = 'abc';
        $responses = [
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['one element'])),
            new Response(200, ['X-Foo' => 'Bar'], json_encode(['content' => [0 => ['cardNumber' => 'card-number']]])),
            new Response(200, [], json_encode(null))
        ];
        $apiClient = $this->createApiClient($responses);
        $result = $apiClient->getQrCodeSvgByEsi($esi);
        $this->assertEquals('null', $result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testGetQrCodeSvgByEsiWithEmptyArgReturnsNull(): void {
        $esi = '';
        $responses = [
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['one element'])),
            new Response(200, ['X-Foo' => 'Bar'], json_encode(['content' => [0 => ['cardNumber' => 'card-number']]])),
            new Response(200, [], json_encode(null))
        ];
        $apiClient = $this->createApiClient($responses);
        $result = $apiClient->getQrCodeSvgByEsi($esi);
        $this->assertNull($result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testGetQrCodeSvgByEsiWithEmptyResultReturnsNull(): void {
        $esi = 'abc';
        $responses = [
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['one element'])),
            new Response(200, ['X-Foo' => 'Bar'], json_encode(['content' => [0 => ['cardNumber' => '']]])),
            new Response(200, [], json_encode(null))
        ];
        $apiClient = $this->createApiClient($responses);
        $result = $apiClient->getQrCodeSvgByEsi($esi);
        $this->assertNull($result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testGetQrCodeSvgByEsiThrowsException(): void {
        $this->expectException(EscApiException::class);
        $this->expectExceptionMessageIsOrContains('HTTP error while fetching QR code for card ');
        $esi = 'abc';
        $responses = [
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['one element'])),
            new Response(200, ['X-Foo' => 'Bar'], json_encode(['content' => [0 => ['cardNumber' => 'fakecardnr']]])),
            new TransferException('Error communicating', new Request('GET', 'test'))
        ];
        $apiClient = $this->createApiClient($responses);
        $apiClient->getQrCodeSvgByEsi($esi);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testCreateCardSucceeds(): void {
        $fakeCardholder = $this->createFakeCardholder();
        $esi = 'abc';
        $response = [
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['one element'])),
        ];
        $apiClient = $this->createApiClient($response);
        $result = $apiClient->createCard($fakeCardholder, $esi);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testCreateCardThrowsException(): void {
        $this->expectException(EscApiException::class);
        $this->expectExceptionMessageIsOrContains('HTTP error while creating card for ');
        $fakeCardholder = $this->createFakeCardholder();
        $esi = 'abc';
        $responses = [
            new TransferException('Error communicating', new Request('GET', 'test'))
        ];
        $apiClient = $this->createApiClient($responses);
        $apiClient->createCard($fakeCardholder, $esi);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testDeleteCardsForCardHolderSucceeds(): void {
        $esi = 'abc';
        $responses = [
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'content' => [
                        (object) ['cardNumber' => 'cardnr1'],
                        (object) ['cardNumber' => 'cardnr2']
                    ],
                    'page' => [
                        'totalPages' => 1
                    ]
                ])
            ),
            new Response(204, [], ''),
            new Response(204, [], '')
        ];
        $apiClient = $this->createApiClient($responses);
        $result = $apiClient->deleteCardsForCardHolder($esi);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testDeleteCardThrowsException(): void {
        $this->expectException(EscApiException::class);
        $this->expectExceptionMessageIsOrContains('HTTP error while deleting card ');
        $escn = 'abc';
        $responses = [
            new TransferException('Error communicating', new Request('GET', 'test'))
        ];
        $apiClient = $this->createApiClient($responses);
        $apiClient->deleteCard($escn);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testDeleteCardholderSucceeds(): void {
        $esi = 'abc';
        $responses = [
            new Response(204, [], '')
        ];
        $apiClient = $this->createApiClient($responses);
        $result = $apiClient->deleteCardholder($esi);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testDeleteCardholderThrowsException(): void {
        $this->expectException(EscApiException::class);
        $this->expectExceptionMessageIsOrContains('HTTP error while deleting person ');
        $esi = 'abc';
        $responses = [
            new TransferException('Error communicating', new Request('GET', 'test'))
        ];
        $apiClient = $this->createApiClient($responses);
        $apiClient->deleteCardholder($esi);
    }
}
