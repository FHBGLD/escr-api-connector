<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Audit\Contracts;

use Hawb\EscrApiConnector\Audit\Classes\AuditEvent;

interface AuditLoggerInterface
{
    public function record(AuditEvent $event): void;
}
