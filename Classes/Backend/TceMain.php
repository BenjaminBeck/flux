<?php

namespace FluidTYPO3\Flux\Backend;

/*
 * This file is part of the FluidTYPO3/Flux project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FluidTYPO3\Flux\Hooks\HookHandler;
use FluidTYPO3\Flux\Provider\ProviderInterface;
use FluidTYPO3\Flux\Service\FluxService;
use FluidTYPO3\Flux\Service\RecordService;
use FluidTYPO3\Flux\Utility\ColumnNumberUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;

/**
 * TCEMain
 */
class TceMain
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var FluxService
     */
    protected $configurationService;

    /**
     * @var RecordService
     */
    protected $recordService;

    /**
     * @var boolean
     */
    static private $cachesCleared = false;

    /**
     * @param ObjectManagerInterface $objectManager
     * @return void
     */
    public function injectObjectManager(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @param FluxService $configurationService
     * @return void
     */
    public function injectConfigurationService(FluxService $configurationService)
    {
        $this->configurationService = $configurationService;
    }

    /**
     * @param RecordService $recordService
     * @return void
     */
    public function injectRecordService(RecordService $recordService)
    {
        $this->recordService = $recordService;
    }

    /**
     * CONSTRUCTOR
     */
    public function __construct()
    {
        $this->injectObjectManager(GeneralUtility::makeInstance(ObjectManager::class));
        $this->injectConfigurationService($this->objectManager->get(FluxService::class));
        $this->injectRecordService($this->objectManager->get(RecordService::class));
    }

    /**
     * @param string $status The TCEmain operation status, fx. 'update'
     * @param string $table The table TCEmain is currently processing
     * @param string $id The records id (if any)
     * @param array $fieldArray The field names and their values to be processed
     * @param DataHandler $reference Reference to the parent object (TCEmain)
     * @return void
     */
    public function processDatamap_postProcessFieldArray($status, $table, $id, &$fieldArray, &$reference)
    {

        // if record already exists
        if (is_integer($id)) {

            if (false && isset($GLOBALS['TCA']['pages']['columns']['l18n_parent'])) {
                $record = $this->recordService->get($table, 'sys_language_uid, l18n_parent', "uid = $id");
                $recordLanguageUid = $record[0]['sys_language_uid'];

                // BUGFIX Typo3 Issue https://forge.typo3.org/issues/85013
                if ('tt_content' == $table && !array_key_exists('colPos', $fieldArray)) {
                    $uidInDefaultLanguage = $record[0]['l18n_parent'];
                    $fieldArray['colPos'] = (int)($reference->datamap[$table][$uidInDefaultLanguage]['colPos'] ?? 0);
                }

                // BUGFIX IRRE
                if ($recordLanguageUid == '0') {
                    $fieldArray['tx_flux_parent'] = ColumnNumberUtility::calculateParentUid($fieldArray['colPos']);
                } else {
                    $parentRecordUid = ColumnNumberUtility::calculateParentUid($fieldArray['colPos']);
                    $parentRecord = $this->recordService->get($table, 'uid', "l18n_parent = $parentRecordUid AND sys_language_uid = $recordLanguageUid");
                    $fieldArray['tx_flux_parent'] = (int) $parentRecord[0]['uid'];
                }
            }
        }
    }
}
