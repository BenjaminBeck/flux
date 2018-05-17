<?php
namespace FluidTYPO3\Flux\Backend;

/*
 * This file is part of the FluidTYPO3/Flux project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FluidTYPO3\Flux\Core;
use FluidTYPO3\Flux\Helper\ContentTypeBuilder;
use FluidTYPO3\Flux\Provider\Provider;
use FluidTYPO3\Flux\Provider\ProviderInterface;
use FluidTYPO3\Flux\Utility\ContextUtility;
use FluidTYPO3\Flux\Utility\ExtensionNamingUtility;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\TableConfigurationPostProcessingHookInterface;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3Fluid\Fluid\Exception;

/**
 * Table Configuration (TCA) post processor
 *
 * Simply loads the Flux service and lets methods
 * on this Service load necessary configuration.
 */
class TableConfigurationPostProcessor implements TableConfigurationPostProcessingHookInterface
{
    /**
     * @param array $parameters
     * @return void
     */
    public function includeStaticTypoScriptHook(array $parameters, TemplateService $caller)
    {
        // This method will be called once for every static TS template inclusion. Obviously, we like to avoid spamming
        // the method (which involves flushing the queued registrations after running them) but because of the way TYPO3
        // loads TS, we need to do a little juggling first.
        // The point of this check is to run through all so-called "content rendering templates" which are TS templates
        // that are specially registered because they must be loaded first. If we can detect that any one of these TS
        // templates have been loaded, we can safely spool our queued registrations. The result is that these automatic
        // content type registrations always come immediately after a content rendering template, but will only be
        // loaded in FE if there actually IS a content template loaded.
        foreach ($GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates'] ?? [] as $contentRenderingTemplateId) {
            if ($parameters['templateId'] === 'ext_' . $contentRenderingTemplateId) {
                // Calling processData also flushes the spooled content type registrations.
                $this->processData();
            }
        }
    }

    /**
     * @return void
     */
    public function processData()
    {
        $this->spoolQueuedContentTypeRegistrations(Core::getQueuedContentTypeRegistrations());
        Core::clearQueuedContentTypeRegistrations();
    }

    /**
     * @param array $queue
     * @return void
     */
    public static function spoolQueuedContentTypeTableConfigurations(array $queue)
    {
        $contentTypeBuilder = new ContentTypeBuilder();
        foreach ($queue as $queuedRegistration) {
            list ($providerExtensionName, $templatePathAndFilename) = $queuedRegistration;
            $contentType = static::determineContentType($providerExtensionName, $templatePathAndFilename);
            $contentTypeBuilder->addBoilerplateTableConfiguration($contentType);
        }
    }

    /**
     * @param string $providerExtensionName
     * @param string $templatePathAndFilename
     * @return string
     */
    protected static function determineContentType($providerExtensionName, $templatePathAndFilename)
    {
        // Determine which plugin name and controller action to emulate with this CType, base on file name.
        $controllerExtensionName = $providerExtensionName;
        $emulatedPluginName = ucfirst(pathinfo($templatePathAndFilename, PATHINFO_FILENAME));
        $extensionSignature = str_replace('_', '', ExtensionNamingUtility::getExtensionKey($controllerExtensionName));
        $fullContentType = $extensionSignature . '_' . strtolower($emulatedPluginName);
        return $fullContentType;
    }

    /**
     * @param string $providerExtensionName
     * @param string $controllerName
     * @return boolean
     */
    protected static function controllerExistsInExtension($providerExtensionName, $controllerName)
    {
        $controllerClassName = str_replace('.', '\\', $providerExtensionName) . '\\Controller\\' . $controllerName . 'Controller';
        return class_exists($controllerClassName);
    }

    /**
     * @param array $queue
     * @return void
     */
    protected function spoolQueuedContentTypeRegistrations(array $queue)
    {
        $contentTypeBuilder = new ContentTypeBuilder();
        foreach ($queue as $queuedRegistration) {
            /** @var ProviderInterface $provider */
            list ($providerExtensionName, $templateFilename, $providerClassName) = $queuedRegistration;
            try {
                $provider = $contentTypeBuilder->configureContentTypeFromTemplateFile(
                    $providerExtensionName,
                    $templateFilename,
                    $providerClassName ?? Provider::class
                );

                Core::registerConfigurationProvider($provider);

                $controllerExtensionName = $providerExtensionName;
                if (!static::controllerExistsInExtension($providerExtensionName, 'Content')) {
                    $controllerExtensionName = 'FluidTYPO3.Flux';
                }

                $contentType = static::determineContentType($providerExtensionName, $templateFilename);
                $pluginName = ucfirst(pathinfo($templateFilename, PATHINFO_FILENAME));
                $contentTypeBuilder->registerContentType($controllerExtensionName, $contentType, $provider, $pluginName);

            } catch (Exception $error) {
                if (!ContextUtility::getApplicationContext()->isProduction()) {
                    throw $error;
                }
            }
        }
    }
}
