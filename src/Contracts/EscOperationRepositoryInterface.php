<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Contracts;

use Hawb\MemberCard\Classes\EscOperation;
use Hawb\MemberCard\Classes\EscOperationType;

interface EscOperationRepositoryInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function enqueue(
        EscOperationType $type,
        string $uid,
        string $esi,
        array $payload,
    ): EscOperation;

    public function claim(int $id): ?EscOperation;

    /**
     * @return list<EscOperation>
     */
    public function claimDue(int $limit): array;

    public function markCompleted(EscOperation $operation): EscOperation;

    public function markFailed(
        EscOperation $operation,
        string $error,
        int $retryAfterSeconds,
    ): EscOperation;
}
