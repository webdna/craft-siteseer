<?php

namespace webdna\craftsiteseer;

use Craft;
use Monolog\Formatter\LineFormatter;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\log\MonologTarget;
use craft\services\UserPermissions;
use craft\services\Utilities;
use webdna\craftsiteseer\models\Settings;
use webdna\craftsiteseer\services\ErrorService;
use webdna\craftsiteseer\services\ItineraryService;
use webdna\craftsiteseer\services\Siteseer as SiteseerService;
use webdna\craftsiteseer\services\VisitService;
use webdna\craftsiteseer\utilities\Siteseer as SiteseerUtility;
use yii\base\Event;
use yii\log\Logger;

/**
 * Siteseer plugin
 *
 * @method static Siteseer getInstance()
 * @method Settings getSettings()
 * @author webdna <info@webdna.co.uk>
 * @copyright webdna
 * @license https://craftcms.github.io/license/ Craft License
 * @property-read Siteseer $Siteseer
 * @property-read ItineraryService $itineraryService
 * @property-read VisitService $visitService
 * @property-read ErrorService $errorService
 */
class Siteseer extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    // public static function config(): array
    // {
    //     return [
    //         'components' => [
    //             // 'siteseer' => SiteseerService::class, 
                
    //         ],
    //     ];
    // }

    public function init(): void
    {
        parent::init();
        
        // Register plugin components
       $this->setComponents([
           'itineraryService' => ItineraryService::class,
            'visitService' => VisitService::class,
            'errorService' => ErrorService::class
       ]);
        
        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function() {
            $this->_attachEventHandlers();
        });


        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => 'siteseer',
            'categories' => ['siteseer'],
            'logContext' => false,
            'allowLineBreaks' => false,
            'formatter' => new LineFormatter(
                format: "%datetime% %message%\n",
                dateFormat: 'Y-m-d H:i:s',
            ),
        ]);
    }

    public static function log(string $message): void
    {
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'siteseer');
    }
    
    public static function error(string $message): void
    {
        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'siteseer');
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('siteseer/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function _attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/5.x/extend/events.html to get started)

        // C5 Method
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES, function (RegisterComponentTypesEvent $event): void {
                $event->types[] = SiteseerUtility::class;
        });

        //C4 method
        // Event::on(
        //     Utilities::class,
        //     Utilities::EVENT_REGISTER_UTILITY_TYPES,
        //     function (RegisterComponentTypesEvent $event) {
        //         $event->types[] = SiteseerUtility::class;
        //     });
       
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => "Siteseer",
                    'permissions' => [
                        'createSiteseerJob' => [
                            'label' => 'Queue a url list.',
                        ]
                    ]
                ];
                // $event->permissions['General']['accessCp']['nested']['accessPlugin-' . $this->id] = [
                //     'label' => Craft::t('app', 'Access {plugin}', ['plugin' => $this->name])
                // ];
            }
        );
    }
}
