<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Audit\Classes;

use InvalidArgumentException;

final readonly class AuditEvent
{
    public ?string $actorUid;
    public ?string $subjectUid;

    public function __construct(
        public AuditEventType $type,
        public AuditOutcome $outcome,
        ?string $actorUid = null,
        ?string $subjectUid = null,
        public ?int $operationId = null,
        public ?int $operationAttempt = null,
        public ?int $consentVersion = null,
        public ?AuditFailureCode $failureCode = null,
    ) {
        if (($outcome === AuditOutcome::Failed) !== ($failureCode !== null)) {
            throw new InvalidArgumentException(
                'Failed audit events require a failure code, and other outcomes must not have one.'
            );
        }

        foreach ([$operationId, $operationAttempt, $consentVersion] as $positiveNumber) {
            if ($positiveNumber !== null && $positiveNumber < 1) {
                throw new InvalidArgumentException('Audit numeric references must be positive.');
            }
        }

        $this->actorUid = $this->normalizeUid($actorUid);
        $this->subjectUid = $this->normalizeUid($subjectUid);
    }

    private function normalizeUid(?string $uid): ?string
    {
        if ($uid === null) {
            return null;
        }

        $uid = substr(trim($uid), 0, 64);

        return $uid !== '' && preg_match('/\A[a-zA-Z0-9._-]+\z/', $uid) === 1
            ? $uid
            : null;
    }
}
