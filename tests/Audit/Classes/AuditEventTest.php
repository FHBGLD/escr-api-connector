<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Tests\Audit\Classes;

use PHPUnit\Framework\TestCase;
use Hawb\EscrApiConnector\Audit\Classes\AuditEvent;
use Hawb\EscrApiConnector\Audit\Classes\AuditEventType;
use Hawb\EscrApiConnector\Audit\Classes\AuditOutcome;
use Hawb\EscrApiConnector\Audit\Classes\AuditFailureCode;
use InvalidArgumentException;
class AuditEventTest extends TestCase {

    public function testCreateAuditEventSucceeds(): void {
        $auditEvent = new AuditEvent(
            type: AuditEventType::ConsentGranted,
            outcome: AuditOutcome::Succeeded,
            actorUid: 'VALID-ACTOR-TEST-UID',
            subjectUid: 'VALID-SUBJECT-TEST-UID',
            consentVersion: 1,
            failureCode: null
        );

        $this->assertInstanceOf(AuditEvent::class, $auditEvent);
    }

    public function testCreateAuditEventSucceedsWithUidBeingNull(): void {
        $auditEvent = new AuditEvent(
            type: AuditEventType::ConsentGranted,
            outcome: AuditOutcome::Succeeded,
            actorUid: null,
            subjectUid: null,
            consentVersion: 1,
            failureCode: null
        );

        $this->assertInstanceOf(AuditEvent::class, $auditEvent);
    }    

    public function testCreateAuditEventHasNoFailureCode(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Failed audit events require a failure code, and other outcomes must not have one.');
        $auditEvent = new AuditEvent(
            type: AuditEventType::ConsentGranted,
            outcome: AuditOutcome::Failed,
            actorUid: 'VALID-ACTOR-TEST-UID',
            subjectUid: 'VALID-SUBJECT-TEST-UID',
            consentVersion: 1,
            failureCode: null
        );
    }

    public function testCreateAuditEventWithWrongNumericReferences(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Audit numeric references must be positive.');
        $auditEvent = new AuditEvent(
            type: AuditEventType::ConsentGranted,
            outcome: AuditOutcome::Succeeded,
            actorUid: 'VALID-ACTOR-TEST-UID',
            subjectUid: 'VALID-SUBJECT-TEST-UID',
            consentVersion: 0,
            failureCode: null
        );
    }

}
