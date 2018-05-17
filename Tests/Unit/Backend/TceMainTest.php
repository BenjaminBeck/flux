<?php
namespace FluidTYPO3\Flux\Tests\Unit\Backend;

/*
 * This file is part of the FluidTYPO3/Flux project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FluidTYPO3\Flux\Backend\TceMain;
use FluidTYPO3\Flux\Provider\ProviderInterface;
use FluidTYPO3\Flux\Service\RecordService;
use FluidTYPO3\Flux\Tests\Fixtures\Data\Records;
use FluidTYPO3\Flux\Tests\Unit\AbstractTestCase;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

/**
 * TceMainTest
 */
class TceMainTest extends AbstractTestCase
{

    /**
     * @return void
     */
    public function setUp()
    {
        $configurationManager = $this->getMockBuilder(ConfigurationManager::class)->getMock();
        $fluxService = $this->objectManager->get('FluidTYPO3\Flux\Service\FluxService');
        $fluxService->injectConfigurationManager($configurationManager);
        $GLOBALS['TYPO3_DB'] = $this->getMockBuilder(
            'TYPO3\\CMS\\Core\\Database\\DatabaseConnection'
        )->setMethods(
            array('exec_SELECTgetSingleRow', 'exec_SELECTgetRows')
        )->disableOriginalConstructor()->getMock();
        $GLOBALS['TYPO3_DB']->expects($this->any())->method('exec_SELECTgetRows')->willReturn(false);
        $GLOBALS['TCA'] = array(
            'tt_content' => array(
                'columns' => array(
                    'pi_flexform' => array()
                )
            )
        );
    }

    /**
     * @test
     */
    public function canExecuteDataPostProcessHook()
    {
        $instance = $this->getInstance();
        $tceMainParent = $this->getCallerInstance();
        $record = Records::$contentRecordWithoutParentAndWithoutChildren;
        $result = $instance->processDatamap_postProcessFieldArray('update', 'tt_content', $record['uid'], $record, $tceMainParent);
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function canExecuteDataPostProcessHookWithoutRecord()
    {
        $instance = $this->getInstance();
        $recordService = $this->getMockBuilder(RecordService::class)->setMethods(['getSingle'])->getMock();
        $recordService->expects($this->once())->method('getSingle')->willReturn(['foo']);
        ObjectAccess::setProperty($instance, 'recordService', $recordService, true);

        $tceMainParent = $this->getCallerInstance();
        $record = array();
        $result = $instance->processDatamap_postProcessFieldArray('update', 'tt_content', null, $record, $tceMainParent);
        $this->assertNull($result);
    }

    /**
     * @return DataHandler
     */
    protected function getCallerInstance()
    {
        /** @var DataHandler $tceMainParent */
        $tceMainParent = GeneralUtility::makeInstance('TYPO3\CMS\Core\DataHandling\DataHandler');
        return $tceMainParent;
    }

    /**
     * @return TceMain
     */
    protected function getInstance()
    {
        $tceMainInstance = new TceMain();
        ObjectAccess::setProperty($tceMainInstance, 'cachesCleared', false, true);
        return $tceMainInstance;
    }

    /**
     * @test
     */
    public function executeConfigurationProviderMethodCallsMethodOnProviders()
    {
        $command = 'postProcessDatabaseOperation';
        $mock = $this->getMockBuilder($this->createInstanceClassName())->setMethods(array('resolveRecordUid', 'ensureRecordDataIsLoaded'))->getMock();
        $mock->expects($this->once())->method('resolveRecordUid')->willReturn(1);
        $mock->expects($this->once())->method('ensureRecordDataISLoaded')->willReturnArgument(2);
        $caller = $this->getCallerInstance();
        $row = array('uid' => 1);
        $arguments = array('status' => $command, 'id' => 1, 'row' => $row);
        $provider = $this->getMockBuilder('FluidTYPO3\\Flux\\Provider\\Provider')->setMethods(array($command))->getMock();
        $provider->expects($this->exactly(2))->method($command);
        $providers = array($provider, $provider);
        $configurationService = $this->getMockBuilder('FluidTYPO3\\Flux\\Service\\FluxService')->setMethods(array('resolveConfigurationProviders'))->getMock();
        $configurationService->expects($this->once())->method('resolveConfigurationProviders')->willReturn($providers);
        $mock->injectConfigurationService($configurationService);
        $result = $this->callInaccessibleMethod($mock, 'executeConfigurationProviderMethod', $command, 'void', 1, $row, $arguments, ProviderInterface::class, $caller);
        $this->assertEquals($row, $result);
    }
}
