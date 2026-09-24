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

    private function createApiClient(int $statusCode = 200, array $responseBody = ['Hello world']): EscApiClient {
        $clientMock = $this->createHttpClient(
            new MockHandler([
                new Response($statusCode, ['X-Foo' => 'Bar'], json_encode($responseBody))
            ])
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
        $apiClient = $this->createApiClient(200, ['List persons']);
        $response = $apiClient->listCardholders();
        $this->assertIsArray($response);
        $this->assertContains('List persons', $response);
    } 

    #[Group('cicd')]
    #[Group('local')]
    public function testCardholderExistencyCheckWorksWithExistingCardholder(): void {
        $esi = 'abc';
        $apiClient = $this->createApiClient(200, ['one element']);
        $response = $apiClient->cardholderExists($esi);
        $this->assertIsBool($response);
        $this->assertTrue($response);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testCardholderExistencyCheckWorksWithNonExistingCardholder(): void {
        $esi = 'abc';
        $apiClient = $this->createApiClient(404, []);
        $response = $apiClient->cardholderExists($esi);
        $this->assertIsBool($response);
        $this->assertFalse($response);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testCardholderRequestSucceeds(): void {
        $esi = 'abc';
        $apiClient = $this->createApiClient(200, [$esi]);
        $response = $apiClient->getCardholder($esi);
        $this->assertIsArray($response);
        $this->assertContains($esi, $response);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testCreateCardholderSucceeds(): void {
        $student = $this->createFakeCardholder();
        $esi = 'abc';
        $apiClient = $this->createApiClient(200, [$esi]);
        $response = $apiClient->createCardholder($student, $esi);
        $this->assertNull($response);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testUpdateCardholderSucceeds(): void {
        $student = $this->createFakeCardholder();
        $esi = 'abc';
        $apiClient = $this->createApiClient(200, [$esi]);
        $response = $apiClient->updateCardholder($student, $esi);
        $this->assertNull($response);
    }    
    
}