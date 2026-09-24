<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Tests\Classes;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Hawb\EscrApiConnector\Classes\EscApiClient;
use Hawb\EscrApiConnector\Classes\EscConfig;
use Hawb\EscrApiConnector\Classes\Cardholder;
use Hawb\EscrApiConnector\Enums\AcademicLevel;
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
    public function testCardholderExistencyCheckWorksWithExistingCardholder(): void {
        $esi = 'abc';
        $apiClient = $this->createApiClient([new Response(200, [], json_encode(['one element']))]);
        $result = $apiClient->cardholderExists($esi);
        $this->assertIsBool($result);
        $this->assertTrue($result);
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
    public function testUpdateCardholderSucceeds(): void {
        $cardholder = $this->createFakeCardholder();
        $esi = 'abc';
        $apiClient = $this->createApiClient([new Response(200, [], json_encode([$esi]))]);
        $result = $apiClient->updateCardholder($cardholder, $esi);
        $this->assertNull($result);
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
    public function testListCardsSucceeds(): void {
        $apiClient = $this->createApiClient([new Response(200, [], json_encode(['List cards']))]);
        $result = $apiClient->listCards();
        $this->assertIsArray($result);
        $this->assertContains('List cards', $result);
    }

    public function testGetCardSucceeds(): void {
        $escn = 'abc';
        $apiClient = $this->createApiClient([new Response(200, [], json_encode([$escn]))]);
        $result = $apiClient->getCard($escn);
        $this->assertIsArray($result);
        $this->assertContains($escn, $result);
    }

    public function testGetCardFails(): void {
        $escn = 'abc';
        $apiClient = $this->createApiClient([new Response(404, [], json_encode([]))]);
        $result = $apiClient->getCard($escn);
        $this->assertIsArray($result);
        $this->assertNotContains($escn, $result);
    }

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
}
