<?php

namespace webdna\craftsiteseer\services;

use Craft;
use craft\helpers\App;
use craft\helpers\Console;
use craft\helpers\FileHelper;
use craft\helpers\Queue;
use craft\helpers\UrlHelper;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Throwable;
use webdna\craftsiteseer\jobs\SiteseerJob;
use webdna\craftsiteseer\models\DestinationList;
use webdna\craftsiteseer\Siteseer;
use yii\base\Component;

/**
 * Visit Service service
 */
class VisitService extends Component
{
    public function createVisitJobs(DestinationList $destinationList, bool $takeSnapshots=false): void
    {
        Queue::push(new SiteseerJob([
            'destinations' => $destinationList->destinations,
            'siteId' => $destinationList->siteId,
            'takeSnapshots' => $takeSnapshots
        ]));
    }

    // uk oh, we've written this for multi visits, but the queue is currently doing single visits
    public function visit(DestinationList $destinationList, bool $takeSnapshots): void
    {
        $site = Craft::$app->getSites()->getSiteById($destinationList->siteId);
        $destinations = $destinationList->destinations;

        if (!$site) {
            return;
        }
        $client = new Client([
            'base_uri' => UrlHelper::siteUrl('/',[],null,$site->id),
        ]);
        
        $total = count($destinations);
        $count = 1;

        $request = function($destinations) use ($client) {
            foreach ($destinations as $destination) {
                yield new Request('GET', $destination->url, ['http_errors' => false]);
            }
        };

        $pool = new Pool($client, $request($destinations), [
            'concurrency' => App::env('CONCURRENT_REQUESTS', 3),
            'fulfilled' => function (Response $response, $index) use ($destinations, $total, &$count, $takeSnapshots) {
                $code = $response->getStatusCode();
                $url = $destinations[$index]->url;
                $output = "%y[$count/$total] $url : ".($code == 200 ? "%g" : "%r")." $code%n";
                // Console::output(Console::renderColoredString($output));
                if ($code == 200) {
                    Craft::info($output,'siteseer');
                    // should we take a snapshot here? 
                    if ($takeSnapshots) {
                        $this->_saveSnapshot($response, $destinations[$index]->url);                    
                    }
                } else {
                    Siteseer::getInstance()->errorService->saveErrorRecord($destinations[$index], $code);
                }
                $count++;
            },
            'rejected' => function ($exception, $index) use ($destinations, $total, &$count, $takeSnapshots) {
                $code = 'UNKNOWN';
                $url = $destinations[$index]->url;
                $output = "%y[$count/$total] $url : ".($code == 200 ? "%g" : "%r")." $code%n";
                try {
                    //code...
                    
                    if ($exception->getResponse()) {
                        $code = $exception->getResponse()->getStatusCode();
                    } else {
                        $code = 500;
                    }
                    
                    Siteseer::error($output);
                } catch (\Throwable $th) {
                    //throw $th;
                }
                Siteseer::getInstance()->errorService->saveErrorRecord($destinations[$index], $code);

                $count++;
            },
        ]);
        
        $promise = $pool->promise();
        $promise->wait();

    }

    private function _saveSnapshot(Response $response, string $path): void 
    {
        $pageContent = (string)$response->getBody();
        $storagePath = Craft::$app->getPath()->getStoragePath();
        $cachePath = $storagePath . '/' . 'site-seer';

        FileHelper::createDirectory($cachePath);
        // Get the root-relative path and split into segments
        $relativePath = trim(UrlHelper::rootRelativeUrl($path), '/');
        $segments = explode('/', $relativePath);

        // Determine directory and filename
        if (empty($relativePath)) {
            $dirPath = $cachePath;
            $filename = 'index.html';
        } else {
            $filename = array_pop($segments);
            // If last segment is empty, use index.html
            if ($filename === '') {
                $filename = 'index.html';
            } else {
                // Remove query string from filename
                $filename = preg_replace('/\?.*/', '', $filename) . '.html';
            }
            $dirPath = $cachePath . '/' . implode('/', $segments);
        }

        // Create directory if needed
        FileHelper::createDirectory($dirPath);

        $fullPath = $dirPath . '/' . $filename;

        // Inject cached banner and timestamp
            $timestamp = date('Y-m-d H:i:s');
            $banner = '<div style="position: fixed; width: 100%; top: 0;background:#ffeeba;color:#856404;padding:10px;text-align:center;font-family:sans-serif;font-size:14px;border-bottom:1px solid #ffeeba;">This is a <strong>cached page</strong> generated at ' . $timestamp . '</div>';
            $pageContent = preg_replace('/<body([^>]*)>/', '<body$1>' . $banner, $pageContent, 1);

        try {
            FileHelper::writeToFile($fullPath, $pageContent);
            Siteseer::log('did it work?');
        } catch (\Exception $exception) {
            Siteseer::error($exception->getMessage());
        }
    }

}
