<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Common\Data\Data;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class SeoPlugin
 * @package Grav\Plugin
 */
class SeoPlugin extends Plugin
{
    /** @var array
     *  Required for Grav 1.7 support:
     *  https://learn.getgrav.org/16/advanced/grav-development/grav-17-upgrade-guide#blueprints
     */
    public $features = [
        'blueprints' => 0, // Use priority 0
    ];

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

        // If in an Admin page.
        if ($this->isAdmin()) {
            $this->enable([
                'onGetPageBlueprints' => ['onGetPageBlueprints', 0],
                'onGetPageTemplates' => ['onGetPageTemplates', 0],
                'onAdminSave' => ['onAdminSave', 0],
            ]);
            return;
        }

        // Enable the main events we are interested in
        $this->enable([
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
        ]);
    }

    /**
     *  Manipulate the page object data before it is saved to the filesystem.
     */
    public function onAdminSave(Event $event)
    {
        $page = $event['object'];

        // When saving plugin configurations we should ignore the event
        if (!($page instanceof \Grav\Common\Page\Page)) {
            return;
        }

        $config = $this->mergeConfig($page);

        // Check if seo plugin should be activated for this page
        if (!$config->get('active', true)) {
            return;
        }

        $header = new Data((array)$page->header());
        $structured_data = $header->get('structured_data', []);

        foreach ($structured_data as $key => $data) {
            // The settings can be defined in the plugin or on a per-page basis.
            $settings = new Data($config->get($key, []));

            // Dynamically add geocoordinates with geocoding plugin, if available
            if (!$settings->get('resolve_geolocation', $config->get('resolve_geolocation', false))) {
                return;
            }

            $data = new Data($data);

            switch ($key) {
                case 'local_business':
                    $this->resolveLocalBusinessGeoLocation($data);
                    break;
                case 'place':
                    $this->resolvePlaceGeoLocation($data);
                    break;
            }

            // Add changes to header
            $header->set('structured_data.' . $key, $data->toArray());
        }

        // Save header to page
        $page->header($header->toArray());
    }

    private function resolveLocalBusinessGeoLocation(Data $data)
    {
        // Dynamically add geocoordinates with geocoding plugin, if available
        if ($this->config->get('plugins.geocoding.enabled') &&
            $data->get('address.street') &&
            $data->get('address.postal_code') &&
            $data->get('address.locality') &&
            $data->get('address.region') &&
            $data->get('address.country') &&
            $data->get('geo') === null)
        {
            // Get geocoordinates using geocoding plugin
            $query = $data->get('address.street') . ', ' .
              $data->get('address.postal_code') . ', ' .
              $data->get('address.locality') . ', ' .
              $data->get('address.region');
            $geo = $this->grav['geocoding']->getLocation($query, $data->get('address.country'));

            // Set coordinates
            if ($geo === null){
                $this->grav['messages']->add('Unable to add geolocation for structured data local business: ' . $query, 'warning');
            }
            else {
                $data->set('geo.lat', $geo->lat);
                $data->set('geo.lon', $geo->lon);
            }
        }
    }

    private function resolvePlaceGeoLocation(Data $data)
    {
        // Dynamically add geocoordinates with geocoding plugin, if available
        if ($this->config->get('plugins.geocoding.enabled') &&
            $data->get('name') &&
            $data->get('geo') === null)
        {
            // Get geocoordinates using geocoding plugin
            $query = $data->get('name');
            $geo = $this->grav['geocoding']->getLocation($query);

            // Set coordinates
            if ($geo === null){
                $this->grav['messages']->add('Unable to add geolocation for structured data place: ' . $query, 'warning');
            }
            else {
                $data->set('geo.lat', $geo->lat);
                $data->set('geo.lon', $geo->lon);
            }
        }
    }

    /**
     * Always add those twig variables and/or assets
     */
    public function onTwigSiteVariables()
    {
        $locator = $this->grav['locator'];
        $page = $this->grav['page'];
        $config = $this->mergeConfig($page);

        // Check if seo plugin should be activated for this page
        if (!$config->get('active', true)) {
            return;
        }

        // Add all template path as schema for locator
        // TODO temporary workaround until https://github.com/getgrav/grav/pull/3059 is merged.
        $locator->addPath('templates', '', $this->grav['twig']->twig_paths);

        // General default setting
        $add_json_ld = $config->get('add_json_ld', true);

        $header = new Data((array)$page->header());
        $structured_data = $header->get('structured_data', []);

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

                # TODO use a proper microdata inlining option:
                # https://github.com/getgrav/grav/issues/3042#issuecomment-731952118
                $this->grav['assets']->addInlineJs($json_ld, ['type' => 'application/ld+json', 'position' => 'after']);
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

    /**
     * Add blueprint directory.
     */
    public function onGetPageBlueprints(Event $event): void
    {
        $types = $event->types;
        $types->scanBlueprints('plugin://' . $this->name . '/blueprints');
    }

    /**
     * Add templates directory.
     */
    public function onGetPageTemplates(Event $event): void
    {
        $types = $event->types;
        $types->scanTemplates('plugin://' . $this->name . '/templates');
    }
}
