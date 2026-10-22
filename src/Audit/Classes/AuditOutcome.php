<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Audit\Classes;

enum AuditOutcome: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Pending = 'pending';
}
