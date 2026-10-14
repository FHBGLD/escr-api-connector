<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Audit\Classes;

enum AuditEventType: string
{
    case AdministratorLogin = 'administrator.login';
    case ImpersonationStart = 'administrator.impersonation.start';
    case ImpersonationStop = 'administrator.impersonation.stop';
    case EscRegistrationRequest = 'esc.registration.request';
    case EscRegistrationAttempt = 'esc.registration.attempt';
    case EscRevocationRequest = 'esc.revocation.request';
    case EscRevocationAttempt = 'esc.revocation.attempt';
    case ConsentGranted = 'privacy.consent.granted';
    case ConsentRevoked = 'privacy.consent.revoked';
}
