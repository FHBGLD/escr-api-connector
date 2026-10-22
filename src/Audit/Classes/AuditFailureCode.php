<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Audit\Classes;

enum AuditFailureCode: string
{
    case InvalidCredentials = 'invalid_credentials';
    case NotAdministrator = 'not_administrator';
    case AuthenticationUnavailable = 'authentication_unavailable';
    case StudentUidRequired = 'student_uid_required';
    case StudentNotFound = 'student_not_found';
    case StudentLookupFailed = 'student_lookup_failed';
    case InvalidCsrf = 'invalid_csrf';
    case ImpersonationReadOnly = 'impersonation_read_only';
    case ConsentNotConfirmed = 'consent_not_confirmed';
    case StudentDataInvalid = 'student_data_invalid';
    case OperationQueueFailed = 'operation_queue_failed';
    case ConsentStorageFailed = 'consent_storage_failed';
    case ConsentDeletionFailed = 'consent_deletion_failed';
    case PrivacyTextUnavailable = 'privacy_text_unavailable';
    case EscApiFailed = 'esc_api_failed';
    case OperationProcessingFailed = 'operation_processing_failed';
    case OperationStatusFailed = 'operation_status_failed';
}
