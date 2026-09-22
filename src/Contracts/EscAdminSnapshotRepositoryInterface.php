<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Contracts;

use Hawb\MemberCard\Classes\EscAdminOverviewPage;
use Hawb\MemberCard\Classes\EscAdminOverviewFilter;
use Hawb\MemberCard\Classes\EscAdminOverviewRecord;
use Hawb\MemberCard\Classes\EscAdminSnapshotSummary;

interface EscAdminSnapshotRepositoryInterface
{
    /**
     * Atomically replaces the complete snapshot.
     *
     * @param list<EscAdminOverviewRecord> $records
     */
    public function replace(array $records): EscAdminSnapshotSummary;

    public function page(
        int $requestedPage,
        int $perPage,
        ?EscAdminOverviewFilter $filter = null,
    ): EscAdminOverviewPage;
}
