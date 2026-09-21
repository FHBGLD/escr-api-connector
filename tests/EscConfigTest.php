<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Tests;

use PHPUnit\Framework\TestCase;
use Hawb\EscrApiConnector\Classes\EscConfig;

class EscConfigTest extends TestCase {
    public function testEscConfigClassExists(): void {
        $this->assertTrue(file_exists('src/Classes/EscConfig.php'));
        $this->assertTrue(class_exists('Hawb\EscrApiConnector\Classes\EscConfig'));
    }

    public function testEscConfigHasProperties(): void {
        $escConfig = new EscConfig('a', 'b', 'c', 'd');
        $this->assertObjectHasProperty('apiToken', $escConfig);
        $this->assertIsString($escConfig->apiToken);
        $this->assertObjectHasProperty('baseUrl', $escConfig);
        $this->assertIsString($escConfig->baseUrl);
        $this->assertObjectHasProperty('organisationPic', $escConfig);
        $this->assertIsString($escConfig->organisationPic);
        $this->assertObjectHasProperty('countryCode', $escConfig);
        $this->assertIsString($escConfig->countryCode);
    }
}