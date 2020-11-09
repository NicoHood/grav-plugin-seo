<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Common\Data\Data;

/**
 * Class SeoPlugin
 * @package Grav\Plugin
 */
class SeoPlugin extends Plugin
{
    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => [
                ['autoload', 100000], // TODO: Remove when plugin requires Grav >=1.7
                ['onPluginsInitialized', 0]
            ]
        ];
    }

    /**
    * Composer autoload.
    *is
    * @return ClassLoader
    */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Enable the main events we are interested in
        $this->enable([
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0]
        ]);
    }

    /**
     * Always add those twig variables and/or assets
     */
    public function onTwigSiteVariables()
    {
        $locator = $this->grav['locator'];
        $page = $this->grav['page'];
        $config = $this->mergeConfig($page);
        $header = new Data((array)$page->header());
        $structured_data = $header->get('structured_data', []);

        if (!$config->get('active', true)) {
            return;
        }

        // Add all template path as schema for locator
        // TODO temporary workaround until https://github.com/getgrav/grav/pull/3059 is merged.
        $locator->addPath('templates', '', $this->grav['twig']->twig_paths);

        // General default setting
        $add_json_ld = $config->get('add_json_ld', true);

        foreach ($structured_data as $key => $data) {
            // Check if a template exists for the given data
            $template = 'structured-data/json-ld/' . $key . '/' . $key . '.json.twig';
            if ($locator->findResource('templates://' . $template, true, false) === false) {
                continue;
            }

            // The settings can be defined in the plugin or on a per-page basis.
            $settings = new Data($config->get($key, []));

            // Check if json_ld should be added to page
            if ($settings->get('add_json_ld', $add_json_ld)) {
                // Generate json-ld
                $json_ld = $this->grav['twig']->processTemplate(
                  $template,
                  [
                    'data' => $data,
                    'page' => $page,
                    'settings' => $settings
                  ]);

                $this->grav['assets']->addInlineJs($json_ld, ['type' => 'application/ld+json']);
            }
        }
    }

    /**
     * Add templates directory to twig lookup paths.
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }
}
