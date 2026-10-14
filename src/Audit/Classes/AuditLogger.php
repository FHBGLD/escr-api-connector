<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Audit\Classes;

use Hawb\EscrApiConnector\Audit\Contracts\AuditLoggerInterface;
use PDO;
use Throwable;

final readonly class AuditLogger implements AuditLoggerInterface
{
    public function __construct(private PDO $dbConnection)
    {
    }

    public function record(AuditEvent $event): void
    {
        try {
            $statement = $this->dbConnection->prepare(
                <<<'SQL'
                    INSERT INTO addon.tbl_studentcard_audit_events (
                        event_type,
                        outcome,
                        actor_uid,
                        subject_uid,
                        operation_id,
                        operation_attempt,
                        consent_version,
                        failure_code
                    ) VALUES (
                        :event_type,
                        :outcome,
                        :actor_uid,
                        :subject_uid,
                        :operation_id,
                        :operation_attempt,
                        :consent_version,
                        :failure_code
                    )
                SQL
            );
            $statement->execute([
                'event_type' => $event->type->value,
                'outcome' => $event->outcome->value,
                'actor_uid' => $event->actorUid,
                'subject_uid' => $event->subjectUid,
                'operation_id' => $event->operationId,
                'operation_attempt' => $event->operationAttempt,
                'consent_version' => $event->consentVersion,
                'failure_code' => $event->failureCode?->value,
            ]);
        } catch (Throwable) {
            // Audit storage must not turn an already-completed remote operation into a retry.
            error_log("Unable to persist audit event {$event->type->value}.");
        }
    }
}
