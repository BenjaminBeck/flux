<?php
namespace FluidTYPO3\Flux\Tests\Unit\View;

/*
 * This file is part of the FluidTYPO3/Flux project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FluidTYPO3\Flux\Form;
use FluidTYPO3\Flux\Service\WorkspacesAwareRecordService;
use FluidTYPO3\Flux\Tests\Fixtures\Data\Records;
use FluidTYPO3\Flux\Tests\Unit\AbstractTestCase;
use FluidTYPO3\Flux\View\PageLayoutView;
use FluidTYPO3\Flux\View\PreviewView;
use TYPO3\CMS\Backend\Routing\Exception\ResourceNotFoundException;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext;
use TYPO3\CMS\Extbase\Mvc\Web\Request;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3\CMS\Fluid\View\TemplatePaths;

/**
 * PreviewViewTest
 */
class PreviewViewTest extends AbstractTestCase
{
    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $router = GeneralUtility::makeInstance(Router::class);
        try {
            $router->match('tce_db');
        } catch (ResourceNotFoundException $error) {
            $router->addRoute('tce_db', new Route('tce_db', []));
            $router->addRoute('new_content_element', new Route('new_content_element', []));
        }
        $GLOBALS['BE_USER'] = $this->getMockBuilder('TYPO3\\CMS\\Core\\Authentication\\BackendUserAuthentication')->setMethods(array('calcPerms'))->getMock();
        $GLOBALS['BE_USER']->expects($this->any())->method('calcPerms');
        $GLOBALS['LANG'] = $this->getMockBuilder('TYPO3\\CMS\\Lang\\LanguageService')->setMethods(array('sL'))->getMock();
        $GLOBALS['LANG']->expects($this->any())->method('sL')->will($this->returnArgument(0));
        $GLOBALS['TCA'] = array(
            'tt_content' => array(
                'columns' => array(
                    'CType' => array(
                        'config' => array(
                            'items' => array(
                                'foo'
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * @test
     */
    public function testGetOptionModeReturnsDefaultIfNoValidOptionsFound()
    {
        $instance = $this->createInstance();
        $options = array(PreviewView::OPTION_MODE => 'someinvalidvalue');
        $result = $this->callInaccessibleMethod($instance, 'getOptionMode', $options);
        $this->assertEquals(PreviewView::MODE_APPEND, $result);
    }

    /**
     * @test
     */
    public function returnsDefaultsWithoutForm()
    {
        $instance = $this->createInstance();
        $result = $this->callInaccessibleMethod($instance, 'getPreviewOptions');
        $this->assertEquals(array(
            PreviewView::OPTION_MODE => PreviewView::MODE_APPEND,
            PreviewView::OPTION_TOGGLE => true,
        ), $result);
    }

    /**
     * @test
     * @dataProvider getPreviewTestOptions
     * @param array $options
     * @param string $finalAssertionMethod
     * @return void
     */
    public function rendersPreviews(array $options, $finalAssertionMethod)
    {
        $provider = $this->objectManager->get('FluidTYPO3\\Flux\\Provider\\Provider');
        $form = Form::create(array('name' => 'test', 'options' => array(PreviewView::OPTION_PREVIEW => $options)));
        $grid = Form\Container\Grid::create(array());
        $grid->createContainer('Row', 'row')->createContainer('Column', 'column');
        $templatePathAndFilename = $this->getAbsoluteFixtureTemplatePathAndFilename(self::FIXTURE_TEMPLATE_PREVIEW);
        $recordService = $this->getMockBuilder(WorkspacesAwareRecordService::class)->setMethods(['get', 'getSingle'])->getMock();
        $recordService->expects($this->any())->method('get')->willReturn([]);
        $recordService->expects($this->any())->method('getSingle')->willReturn(['foo' => 'bar']);
        $provider->setGrid($grid);
        $provider->setForm($form);
        $provider->setTemplatePathAndFilename($templatePathAndFilename);
        $provider->injectRecordService($recordService);
        $provider->setExtensionKey('test');
        $pageLayoutView = $this->getMockBuilder(PageLayoutView::class)->setMethods(['initializeLanguages'])->getMock();
        $previewView = $this->getMockBuilder($this->createInstanceClassName())
            ->setMethods(array('getPageLayoutView'))
            ->getMock();
        $context = $this->objectManager->get(RenderingContext::class);
        $controllerContext = new ControllerContext();
        $controllerContext->setRequest(new Request());
        $context->setControllerContext($controllerContext);
        $templatePaths = $this->getMockBuilder(TemplatePaths::class)->setMethods(['resolveTemplateFileForControllerAndActionAndFormat'])->getMock();
        $templatePaths->expects($this->atLeastOnce())->method('resolveTemplateFileForControllerAndActionAndFormat')->willReturn(
            $templatePathAndFilename
        );
        $context->setTemplatePaths($templatePaths);
        $previewView->setRenderingContext($context);
        $previewView->expects($this->any())->method('getPageLayoutView')->willReturn($pageLayoutView);
        $previewView->injectWorkspacesAwareRecordService($recordService);
        $previewView->injectRecordService($recordService);
        $previewView->injectConfigurationService($this->objectManager->get('FluidTYPO3\\Flux\\Service\\FluxService'));
        $previewView->injectConfigurationManager(
            $this->objectManager->get('TYPO3\\CMS\\Extbase\\Configuration\\ConfigurationManager')
        );
        $preview = $previewView->getPreview($provider, Records::$contentRecordIsParentAndHasChildren);
        $this->$finalAssertionMethod($preview);
    }

    /**
     * @test
     */
    public function avoidsRenderPreviewSectionIfTemplateFileDoesNotExist()
    {
        $provider = $this->getMockBuilder('FluidTYPO3\\Flux\\Provider\\Provider')->setMethods(array('getTemplatePathAndFilename'))->getMock();
        $provider->expects($this->atLeastOnce())->method('getTemplatePathAndFilename')->willReturn(null);
        $previewView = $this->getMockBuilder($this->createInstanceClassName())->setMethods(array('dummy'))->getMock();
        $this->callInaccessibleMethod($previewView, 'renderPreviewSection', $provider, array());
    }

    /**
     * @param string $preview
     * @return void
     */
    protected function assertPreviewIsEmpty($preview)
    {
        $this->assertEquals('Preview text', trim($preview));
    }

    /**
     * @param string $preview
     * @return void
     */
    protected function assertPreviewComesAfterGrid($preview)
    {
        $this->assertStringStartsNotWith('Preview text', trim($preview));
    }

    /**
     * @param string $preview
     * @return void
     */
    protected function assertPreviewComesBeforeGrid($preview)
    {
        $this->assertStringStartsWith('Preview text', trim($preview));
    }

    /**
     * @param string $preview
     * @return void
     */
    protected function assertPreviewContainsToggle($preview)
    {
        $this->assertContains('<div class="grid-visibility-toggle" ', trim($preview));
    }

    /**
     * @return array
     */
    public function getPreviewTestOptions()
    {
        return array(
            array(
                array(PreviewView::OPTION_MODE => PreviewView::MODE_NONE, PreviewView::OPTION_TOGGLE => false),
                'assertPreviewIsEmpty'
            ),
            array(
                array(PreviewView::OPTION_MODE => PreviewView::MODE_PREPEND, PreviewView::OPTION_TOGGLE => false),
                'assertPreviewComesAfterGrid'
            ),
            array(
                array(PreviewView::OPTION_MODE => PreviewView::MODE_APPEND, PreviewView::OPTION_TOGGLE => false),
                'assertPreviewComesBeforeGrid'
            ),
            array(
                array(PreviewView::OPTION_MODE => PreviewView::MODE_PREPEND, PreviewView::OPTION_TOGGLE => true),
                'assertPreviewContainsToggle'
            )
        );
    }

    /**
     * @return object
     */
    protected function createInstance()
    {
        $instance = $this->getMockBuilder(PreviewView::class)->setMethods(['configurePageLayoutViewForLanguageMode'])->getMock();
        $instance->expects($this->any())->method('configurePageLayoutViewForLanguageMode')->willReturnArgument(0);
        return $instance;
    }
}
