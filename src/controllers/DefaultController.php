<?php

namespace webdna\craftsiteseer\controllers;

use webdna\craftsiteseer\Siteseer;
use webdna\craftsiteseer\models\Error as ErrorModel;


use Craft;
use craft\web\Controller;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\AdminTable;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use yii\web\Response;

/**
 * Default controller
 */
class DefaultController extends Controller
{
    public $defaultAction = 'create-jobs';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * sightseer/default action
     */
    public function actionCreateJobs(): Response
    {
        $this->requirePermission('createSiteseerJob');
        $itineraryService = Siteseer::getInstance()->itineraryService;
        $sections = $this->request->getBodyParam('sections', []);
        $groups = $this->request->getBodyParam('groups', []);
        $types = $this->request->getBodyParam('types', []);
        $manual = $this->request->getBodyParam('manual', []);
        $includeConfig = $this->request->getBodyParam('includeconfig', false);
        $takeSnapshots = (bool)$this->request->getBodyParam('snapshot', false);
        $siteIds = $this->request->getBodyParam('sites',[]);

        if ($sections === '*') {
            $sections = $itineraryService->allSectionIds();
        }
        if ($groups === '*') {
            $groups = $itineraryService->allGroupIds();
        }
        if ($types === '*') {
            $types = $itineraryService->allTypeIds();
        }
        if ($siteIds === '*') {
            $siteIds = $itineraryService->allSiteIds();
        }

        $destinations = Siteseer::getInstance()->itineraryService->allTheUrls(
        $sections,
        $groups,
        $types,
        $manual,
        $siteIds,
        [
            'includeConfig' => (bool)$includeConfig
            ]
        );

        // each top level is a site
        if (count($destinations)) {
            foreach ($destinations as $destinationList) {
                # code...
                // Siteseer::getInstance()->visitService->visit($destinationList, $takeSnapshots);
                Siteseer::getInstance()->visitService->createVisitJobs($destinationList, $takeSnapshots);
            }
        }

        return $this->redirectToPostedUrl();

    }

    public function actionGetSiteseerErrorTable(): Response
    {
        // $this->requireAcceptsJson();
        $request = Craft::$app->getRequest();

        $page = $request->getParam('page', 1);
        // $sort = $request->getParam('sort', null);
        $limit = $request->getParam('per_page', 10);
        $code = $request->getParam('code', '');
        $siteId = $request->getParam('siteId', null);
        $offset = ($page - 1) * $limit;

        $errors = Siteseer::getInstance()->errorService->getErrors($limit, $offset, $siteId, $code);

        $rows = [];


        foreach ($errors['errors'] as $error) {
            $element = Craft::$app->getElements()->getElementById($error->elementId,null, $error->siteId);
            $rows[] = [
                'title' => $error->id,
                'link' => Html::tag('a', $error->url, [
                    'href' => $error->url,
                    'target' => '_blank'
                ]),
                'code' => Html::tag('a', $error->code, [
                    'href' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/' . $error->code,
                    'target' => '_blank'
                ]),
                'site' => Craft::$app->getSites()->getSiteById($error->siteId)->name,
                'editUrl' => $element ? Html::tag('a', Craft::t('siteseer', 'Edit'), [
                    'href' => $element->getCpEditUrl(),
                    'target' => '_blank'
                ]) : null
            ];
        }

        return $this->asJson([
            'pagination' => AdminTable::paginationLinks($page, $errors['total'], $limit),
            'data' => $rows
        ]);
    }
}
