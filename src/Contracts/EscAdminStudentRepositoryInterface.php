<?php

declare(strict_types=1);

namespace Hawb\EscrApiConnector\Contracts;

interface EscAdminStudentRepositoryInterface
{
    /**
     * @return list<array{uid: string, matriculation_number: string, first_name: string, last_name: string, study_type: string}>
     */
    public function listEligibleStudents(): array;
}
