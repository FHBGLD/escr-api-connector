<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Tests\Audit\Classes;

use PHPUnit\Framework\TestCase;
use Mockery;
use PDO;
use PDOStatement;
use Exception;
use Hawb\EscrApiConnector\Audit\Classes\AuditLogger;
use Hawb\EscrApiConnector\Audit\Classes\AuditEvent;
use Hawb\EscrApiConnector\Audit\Classes\AuditEventType;
use Hawb\EscrApiConnector\Audit\Classes\AuditOutcome;
use Hawb\EscrApiConnector\Audit\Contracts\AuditLoggerInterface;

class AuditLoggerTest extends TestCase {

  public function testCreationSucceeds(): void {

    $this->expectNotToPerformAssertions();

    $auditEvent = new AuditEvent(
        type: AuditEventType::ConsentGranted,
        outcome: AuditOutcome::Succeeded,
        actorUid: 'VALID-ACTOR-TEST-UID',
        subjectUid: 'VALID-SUBJECT-TEST-UID',
        consentVersion: 1,
        failureCode: null
    );

    $statementDouble = Mockery::mock(PDOStatement::class);
    $statementDouble
      ->shouldReceive('execute')
      ->with(Mockery::type('array'))
      ->once()
      ->andReturn(true);

    $pdoDouble = Mockery::mock(PDO::class);

    $pdoDouble->shouldReceive('prepare')
      ->once()
      ->with(Mockery::type('string'))
      ->andReturn($statementDouble);

    $logger = new AuditLogger($pdoDouble);
    $logger->record($auditEvent);
  }

  public function testCreationThrowsException(): void {

    $this->expectErrorLog();

    $auditEvent = new AuditEvent(
        type: AuditEventType::ConsentGranted,
        outcome: AuditOutcome::Succeeded,
        actorUid: 'VALID-ACTOR-TEST-UID',
        subjectUid: 'VALID-SUBJECT-TEST-UID',
        consentVersion: 1,
        failureCode: null
    );

    $pdoDouble = Mockery::mock(PDO::class);
    $pdoDouble->shouldReceive('prepare')
      ->withArgs(['query'])
      ->andThrow(new Exception('FAILURE IN TEST'));

    $logger = new AuditLogger($pdoDouble);
    $logger->record($auditEvent);
  }

}