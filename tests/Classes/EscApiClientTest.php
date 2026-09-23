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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

class EscApiClientTest extends TestCase {

    private function createHttpClient(MockHandler $mock): Client {
        $handlerStack = HandlerStack::create($mock);
        return new Client(['handler' => $handlerStack]);
    }

    private function createEscConfig(): EscConfig {
        return (new EscConfig(
            'a',
            'b',
            'c',
            'd',
            240.0
        ));
    }

    private function createApiClient(array $responseBody = ['Hello world']): EscApiClient {
        $clientMock = $this->createHttpClient(
            new MockHandler([
                new Response(200, ['X-Foo' => 'Bar'], json_encode($responseBody))
            ])
        );

        return new EscApiClient($this->createEscConfig(), $clientMock);
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
    public function testListPersonsSucceeds(): void {
        $apiClient = $this->createApiClient(['List persons']);
        $response = $apiClient->listPersons();
        $this->assertIsArray($response);
        $this->assertContains('List persons', $response);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testPersonExistencyCheckWorks(): void {
        $esi = 'abc';
        $apiClient = $this->createApiClient([true]);
        $response = $apiClient->personExists($esi);
        $this->assertIsBool($response);
        $this->assertTrue($response);
    }

    #[Group('cicd')]
    #[Group('local')]
    public function testPersonRequestSucceedsWithout(): void {
        $esi = 'abc';
        $apiClient = $this->createApiClient([$esi]);
        $response = $apiClient->getPerson($esi);
        $this->assertIsArray($response);
        $this->assertContains($esi, $response);
    }
}