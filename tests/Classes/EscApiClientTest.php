<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Tests\Classes;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Hawb\EscrApiConnector\Classes\EscApiClient;
use Hawb\EscrApiConnector\Classes\EscConfig;

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

    public function testEscApiClientClassExists(): void {
        $this->assertTrue(file_exists('src/Classes/EscApiClient.php'));
        $this->assertTrue(class_exists('Hawb\EscrApiConnector\Classes\EscApiClient'));
    }

    public function testListPersonsSucceeds(): void {
        $clientMock = $this->createHttpClient(
            new MockHandler([
                new Response(200, ['X-Foo' => 'Bar'], json_encode(['Hello world']))
            ])
        );

        $apiClient = new EscApiClient($this->createEscConfig(), $clientMock);

        $response = $apiClient->listPersons();
        $this->assertIsArray($response);
        $this->assertContains('Hello world', $response);
    }

}