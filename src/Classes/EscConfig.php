<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Classes;

final class EscConfig {
    public function __construct(
        public readonly string $apiToken,
        public readonly string $baseUrl,
        public readonly string $organisationPic,
        public readonly string $countryCode,
        public readonly float $qrCodeRenderSize = 240.0
    ) {}
}