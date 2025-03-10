<?php

/**
 * @file classes/services/PKPStatsSushiService.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsSushiService
 * @ingroup services
 *
 * @brief Helper class that encapsulates COUNTER R5 SUSHI statistics business logic
 */

namespace PKP\services;

use PKP\db\DAORegistry;
use PKP\services\queryBuilders\PKPStatsSushiQueryBuilder;

class PKPStatsSushiService
{
    /**
     * Get a QueryBuilder object with the passed args
     */
    public function getQueryBuilder(array $args = []): PKPStatsSushiQueryBuilder
    {
        $statsQB = new PKPStatsSushiQueryBuilder();
        $statsQB
            ->filterByContexts($args['contextIds'])
            ->filterByInstitution((int) $args['institutionId'])
            ->before($args['dateEnd'])
            ->after($args['dateStart']);

        if (!empty($args['yearsOfPublication'])) {
            $statsQB->filterByYOP($args['yearsOfPublication']);
        }
        if (!empty($args['submissionIds'])) {
            $statsQB->filterBySubmissions($args['submissionIds']);
        }

        return $statsQB;
    }

    /**
     * Do usage stats data already exist for the given month
     *
     * @param string $month Month in the form YYYYMM
     */
    public function monthExists(string $month): bool
    {
        $statsQB = new PKPStatsSushiQueryBuilder();
        return $statsQB->monthExists($month);
    }

    /**
     * Get earliest date, the COUNTER R5 started at
     * R5 is introduced in the release 3.4.0.0, so get the date installed of the release 3.4.0.0 or the first version used after that
     */
    public function getEarliestDate(): string
    {
        $versinDao = DAORegistry::getDAO('VersionDAO'); /* @var VersionDAO $versinDao */
        return $versinDao->getInstallationDate(3400);
    }

    /**
     * Delete daily usage metrics for a month
     *
     * @param string $month Month in the form YYYYMM
     */
    public function deleteDailyMetrics(string $month): void
    {
        $statsQB = new PKPStatsSushiQueryBuilder();
        $statsQB->deleteDailyMetrics($month);
    }

    /**
     * Delete monthly usage metrics for a month
     *
     * @param string $month Month in the form YYYYMM
     */
    public function deleteMonthlyMetrics(string $month): void
    {
        $statsQB = new PKPStatsSushiQueryBuilder();
        $statsQB->deleteMonthlyMetrics($month);
    }

    /**
     * Aggregate daily usage metrics by a month
     *
     * @param string $month Month in the form YYYYMM
     */
    public function addMonthlyMetrics(string $month): void
    {
        $statsQB = new PKPStatsSushiQueryBuilder();
        $statsQB->addMonthlyMetrics($month);
    }
}
