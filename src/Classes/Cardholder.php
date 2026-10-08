<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Classes;

use DateTimeImmutable;
use InvalidArgumentException;
use Hawb\EscrApiConnector\Enums\AcademicLevel;
use Hawb\EscrApiConnector\Enums\CardType;

final class Cardholder
{
    public function __construct(
        public string $studentCode,
        public string $fullName,
        public string $email,
        public AcademicLevel $academicLevel,
        public string $cardExpiresAt,
        public array $duplicateLabels = [],
        public CardType $cardType = CardType::SMART_PASSIVE,
    ) {
    }

    public static function fromSessionData(array $data): self
    {
        $uid = self::requiredString($data, 'uid');
        $studentCode = self::optionalString($data, 'matrikelnr') ?? $uid;
        $fullName = trim(
            self::requiredString($data, 'vorname') . ' ' . self::requiredString($data, 'nachname')
        );

        $academicLevel = match (strtoupper(self::requiredString($data, 'studiengang_typ'))) {
            'BA', 'B' => AcademicLevel::BACHELOR,
            'MA', 'M' => AcademicLevel::MASTER,
            'PHD', 'D', 'P' => AcademicLevel::DOCTORATE,
            default => throw new InvalidArgumentException('Unsupported academic level.'),
        };

        $email = isset($data['email']) && is_string($data['email'])
            ? trim($data['email'])
            : '';

        if ($email === '') {
            $emailDomain = ltrim((string) ($_ENV['ESC_EMAIL_DOMAIN'] ?? 'hochschule-burgenland.at'), '@');
            $email = $uid . '@' . $emailDomain;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invalid student email address.');
        }

        return new self(
            studentCode: $studentCode,
            fullName: $fullName,
            email: $email,
            academicLevel: $academicLevel,
            cardExpiresAt: self::formatExpirationDate(self::requiredString($data, 'expiryDate')),
        );
    }

    public static function esi(string $uid, EscConfig $config): string
    {
        $countryCode = strtoupper(trim((string) ($config->countryCode ?? '')));
        $uid = trim($uid);

        if ($countryCode === '' || $uid === '') {
            throw new InvalidArgumentException('Cannot build the European Student Identifier.');
        }

        return sprintf('urn:schac:personalUniqueCode:int:esi:%s:%s', $countryCode, $uid);
    }

    private static function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException("Missing student field: {$key}.");
        }

        $value = trim((string) $value);

        if ($value === '') {
            throw new InvalidArgumentException("Missing student field: {$key}.");
        }

        return $value;
    }

    private static function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private static function formatExpirationDate(string $value): string
    {
        foreach (['!Y-m-d', '!d.m.Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if (
                $date !== false
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            ) {
                return $date->format('Y-m-d');
            }
        }

        throw new InvalidArgumentException('Invalid card expiration date.');
    }
}
