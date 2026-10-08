<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Tests\Classes;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use InvalidArgumentException;
use Hawb\EscrApiConnector\Classes\Cardholder;
use Hawb\EscrApiConnector\Classes\EscConfig;
use Hawb\EscrApiConnector\Enums\AcademicLevel;
use Hawb\EscrApiConnector\Enums\CardType;

class CardholderTest extends TestCase {

    public static function cardholderValidDataProvider(): array {
        return [
            'with all fields populated' => [
                [
                    'uid' => 'UNIT_TEST_UID',
                    'matrikelnr' => 'UNIT_TEST_STUDENT_CODE',
                    'vorname' => 'UNIT Didi',
                    'nachname' => 'TEST Demo',
                    'studiengang_typ' => 'BA',
                    'email' => 'unit.test@example.com',
                    'expiryDate' => '2027-12-31',
                ]
            ],
            'without student code' => [
                [
                    'uid' => 'UNIT_TEST_UID',
                    'vorname' => 'UNIT Didi',
                    'nachname' => 'TEST Demo',
                    'studiengang_typ' => 'MA',
                    'email' => 'unit.test@example.com',
                    'expiryDate' => '2027-12-31',
                ]
            ],
            'without email' => [
                [
                    'uid' => 'UNIT_TEST_UID',
                    'matrikelnr' => 'UNIT_TEST_STUDENT_CODE',
                    'vorname' => 'UNIT Didi',
                    'nachname' => 'TEST Demo',
                    'studiengang_typ' => 'PHD',
                    'expiryDate' => '2027-12-31',
                ]
            ],
        ];
    }

    public static function cardholderInvalidDataProvider(): array {
        return [
            'without academic level' => [
                [
                    'uid' => 'UNIT_TEST_UID',
                    'matrikelnr' => 'UNIT_TEST_STUDENT_CODE',
                    'vorname' => 'UNIT Didi',
                    'nachname' => 'TEST Demo',
                    'email' => 'unit.test@example.com',
                    'expiryDate' => '2027-12-31',
                ],
                'Missing student field',
            ],
            'without firstname part' => [
                [
                    'uid' => 'UNIT_TEST_UID',
                    'matrikelnr' => 'UNIT_TEST_STUDENT_CODE',
                    'nachname' => 'TEST Demo',
                    'studiengang_typ' => 'BA',
                    'email' => 'unit.test@example.com',
                    'expiryDate' => '2027-12-31',
                ],
                'Missing student field',
            ],
            'without lastname part' => [
                [
                    'uid' => 'UNIT_TEST_UID',
                    'matrikelnr' => 'UNIT_TEST_STUDENT_CODE',
                    'vorname' => 'UNIT Didi',
                    'studiengang_typ' => 'BA',
                    'email' => 'unit.test@example.com',
                    'expiryDate' => '2027-12-31',
                ],
                'Missing student field',
            ],
            'with invalid email' => [
                [
                    'uid' => 'UNIT_TEST_UID',
                    'matrikelnr' => 'UNIT_TEST_STUDENT_CODE',
                    'vorname' => 'UNIT Didi',
                    'nachname' => 'TEST Demo',
                    'studiengang_typ' => 'BA',
                    'email' => 'unit.test@fakemail',
                    'expiryDate' => '2027-12-31',
                ],
                'Invalid student email address.',
            ],
            'without expiry date' => [
                [
                    'uid' => 'UNIT_TEST_UID',
                    'matrikelnr' => 'UNIT_TEST_STUDENT_CODE',
                    'vorname' => 'UNIT Didi',
                    'nachname' => 'TEST Demo',
                    'studiengang_typ' => 'BA',
                    'email' => 'unit.test@example.com',
                ],
                'Missing student field',
            ],
            'with invalid academic level' => [
                [
                    'uid' => 'UNIT_TEST_UID',
                    'matrikelnr' => 'UNIT_TEST_STUDENT_CODE',
                    'vorname' => 'UNIT Didi',
                    'nachname' => 'TEST Demo',
                    'studiengang_typ' => 'NOTYPE',
                    'email' => 'unit.test@fakemail',
                ],
                'Unsupported academic level.'
            ],
            'with empty required string' => [
                [
                    'uid' => 'UNIT_TEST_UID',
                    'matrikelnr' => 'UNIT_TEST_STUDENT_CODE',
                    'vorname' => '',
                    'nachname' => 'TEST Demo',
                    'studiengang_typ' => 'BA',
                    'email' => 'unit.test@fakemail',
                ],
                'Missing student field',
            ],
            'with invalid expiry date' => [
                [
                    'uid' => 'UNIT_TEST_UID',
                    'matrikelnr' => 'UNIT_TEST_STUDENT_CODE',
                    'vorname' => 'UNIT Didi',
                    'nachname' => 'TEST Demo',
                    'studiengang_typ' => 'BA',
                    'email' => 'unit.test@example.com',
                    'expiryDate' => 'flikflak',
                ],
                'Invalid card expiration date.',
            ],
        ];
    }

    #[DataProvider('cardholderValidDataProvider')]
    public function testCreateFromSessionSucceeds(array $data): void {
        $cardholder = Cardholder::fromSessionData($data);
        $this->assertNotEmpty($cardholder);
        $this->assertEquals($data['matrikelnr'] ?? $data['uid'], $cardholder->studentCode);
        $this->assertEquals($data['vorname'] . ' ' . $data['nachname'], $cardholder->fullName);
        $this->assertIsString(filter_var($cardholder->email, FILTER_VALIDATE_EMAIL));
        $this->assertInstanceOf(AcademicLevel::class, $cardholder->academicLevel);
        $this->assertMatchesRegularExpression('/^\d{4}\-(0?[1-9]|1[012])\-(0?[1-9]|[12][0-9]|3[01])$/', $cardholder->cardExpiresAt);
        $this->assertEquals(CardType::SMART_PASSIVE, $cardholder->cardType);
    }

    #[DataProvider('cardholderInvalidDataProvider')]
    public function testCreateFromSessionFails(array $data, string $exceptionMessage): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($exceptionMessage);
        $cardholder = Cardholder::fromSessionData($data);
    }

    public function testEsiGenerationSucceeds(): void {
        $uid = 'TEST_UID';
        $config = new EscConfig('', '', '', 'AT', 0.0);
        $esi = Cardholder::esi($uid, $config);
        $this->assertEquals('urn:schac:personalUniqueCode:int:esi:' . $config->countryCode . ':' . $uid, $esi);
    }

    public function testEsiGenerationFailsWithoutCountryCode(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Cannot build the European Student Identifier.');
        $uid = 'TEST_UID';
        $config = new EscConfig('', '', '', '', 0.0);
        $esi = Cardholder::esi($uid, $config);
    }

    public function testEsiGenerationFailsWithoutUid(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Cannot build the European Student Identifier.');
        $uid = '';
        $config = new EscConfig('', '', '', 'AT', 0.0);
        $esi = Cardholder::esi($uid, $config);
    }
}