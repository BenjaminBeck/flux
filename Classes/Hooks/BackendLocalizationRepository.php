<?php

namespace FluidTYPO3\Flux\Hooks;

/*
 * This file is part of the FluidTYPO3/Flux project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FluidTYPO3\Flux\Provider\Interfaces\ControllerProviderInterface;
use FluidTYPO3\Flux\Service\FluxService;
use FluidTYPO3\Flux\Service\RecordService;
use FluidTYPO3\Flux\Service\WorkspacesAwareRecordService;
use FluidTYPO3\Flux\Utility\ColumnNumberUtility;
use FluidTYPO3\Flux\ViewHelpers\Form\DataViewHelper;
use TYPO3\CMS\Backend\Domain\Repository\Localization\LocalizationRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;


/**
 * Class ContentIconHookSubscriber
 */
class BackendLocalizationRepository extends LocalizationRepository
{

    /**
     * @var FluxService
     */
    protected $configurationService;


    /**
     * @param FluxService $configurationService
     * @return void
     */
    public function injectConfigurationService(FluxService $configurationService)
    {
        $this->configurationService = $configurationService;
    }

    /**
     * Get records for copy process
     *
     * @param int $pageId
     * @param int $colPos
     * @param int $destLanguageId
     * @param int $languageId
     * @param string $fields
     * @return \Doctrine\DBAL\Driver\Statement
     */
    public function getRecordsToCopyDatabaseResult($pageId, $colPos, $destLanguageId, $languageId, $fields = '*')
    {
        $result = parent::getRecordsToCopyDatabaseResult($pageId, $colPos, $destLanguageId, $languageId, $fields);


        $ttContentUids = [];

        while ($row = $result->fetch()) {
            $ttContentUids[] = $row['uid'];

            if ($row['tx_flux_children']) {

                $configurationService = GeneralUtility::makeInstance(ObjectManager::class)->get(FluxService::class);
                $provider = $configurationService->resolvePrimaryConfigurationProvider(
                    'tt_content',
                    'pi_flexform',
                    $row,
                    null,
                    ControllerProviderInterface::class
                );
                $grid = $provider->getGrid($row);
                if (true === empty($grid)) {
                    continue;
                }
                $gridConfiguration = $grid->build();
                $nestedColumnIds = [];
                foreach ($gridConfiguration["rows"] as $gridRow) {
                    foreach ($gridRow["columns"] as $column) {
                        $nestedColumnIds[] = ColumnNumberUtility::calculateColumnNumberForParentAndColumn($row["uid"], $column["colPos"]);
                    }
                }
                foreach ($nestedColumnIds as $nestedColumnId) {
                    // Get original uid of existing elements triggered language / colpos
                    $queryBuilder = $this->getQueryBuilderWithWorkspaceRestriction('tt_content');
                    $queryBuilder->select(...GeneralUtility::trimExplode(',', $fields, true))
                        ->from('tt_content')
                        ->where(
                            $queryBuilder->expr()->eq(
                                'tt_content.sys_language_uid',
                                $queryBuilder->createNamedParameter($languageId, \PDO::PARAM_INT)
                            ),
                            $queryBuilder->expr()->eq(
                                'tt_content.colPos',
                                $queryBuilder->createNamedParameter($nestedColumnId, \PDO::PARAM_INT)
                            ),
                            $queryBuilder->expr()->eq(
                                'tt_content.pid',
                                $queryBuilder->createNamedParameter($pageId, \PDO::PARAM_INT)
                            )
                        )
                        ->orderBy('tt_content.sorting');
                    $nestedContentRecords = $queryBuilder->execute();


                    while ($nestedRow = $nestedContentRecords->fetch()) {
                        $ttContentUids[] = $nestedRow['uid'];
                    }
                }


            }


        }


        $queryBuilder = $this->getQueryBuilderWithWorkspaceRestriction('tt_content');
        $queryBuilder->select(...GeneralUtility::trimExplode(',', $fields, true))
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->in(
                    'tt_content.uid',
                    $ttContentUids
                )
            )
            ->orderBy('tt_content.sorting');
        $newResult = $queryBuilder->execute();


//        foreach($ttContentUids as $ttContentUid){
//
//        }


        return $newResult;

    }
}
