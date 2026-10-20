<?php

namespace webdna\craftsiteseer\services;

use Craft;
use craft\helpers\App;
use craft\helpers\Console;
use craft\helpers\FileHelper;
use craft\helpers\Queue;
use craft\helpers\UrlHelper;
use craft\console\Application as ConsoleApplication;
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
    /**
     * @return int the number of URLs that did not answer 200
     */
    public function visit(DestinationList $destinationList, bool $takeSnapshots, bool $consoleOutput = false): int
    {
        $settings = Siteseer::getInstance()->getSettings();
        $site = Craft::$app->getSites()->getSiteById($destinationList->siteId);
        $destinations = $destinationList->destinations;

        if (!$site) {
            return 0;
        }
        $client = new Client([
            'base_uri' => UrlHelper::siteUrl('/',[], $settings->useHttps ? 'https' : null,$site->id),
        ]);
        
        $total = count($destinations);
        $count = 1;
        $failures = 0;

        $request = function($destinations) use ($client) {
            foreach ($destinations as $destination) {
                yield new Request('GET', $destination->url, ['http_errors' => false]);
            }
        };

        $pool = new Pool($client, $request($destinations), [
            'concurrency' => App::env('CONCURRENT_REQUESTS', 3),
            'fulfilled' => function (Response $response, $index) use ($destinations, $total, &$count, &$failures, $takeSnapshots, $consoleOutput) {
                $code = $response->getStatusCode();
                $url = $destinations[$index]->url;
                $output = "%y[$count/$total] $url : ".($code == 200 ? "%g" : "%r")." $code%n";
                if ($consoleOutput && Craft::$app instanceof ConsoleApplication) {
                    Console::output(Console::renderColoredString($output));
                }
                if ($code == 200) {
                    Craft::info($output,'siteseer');
                    // should we take a snapshot here? 
                    if ($takeSnapshots) {
                        $this->_saveSnapshot($response, $destinations[$index]->url, $consoleOutput);                    
                    }
                } else {
                    Siteseer::getInstance()->errorService->saveErrorRecord($destinations[$index], $code);
                    $failures++;
                }
                $count++;
            },
            'rejected' => function ($exception, $index) use ($destinations, $total, &$count, &$failures, $consoleOutput) {
                $code = 'UNKNOWN';
                $url = $destinations[$index]->url;
                try {
                    if (method_exists($exception, 'getResponse') && $exception->getResponse()) {
                        $code = $exception->getResponse()->getStatusCode();
                    } else {
                        $code = 500;
                    }
                } catch (\Throwable $th) {
                }
                $output = "%y[$count/$total] $url : %r $code%n";
                if ($consoleOutput && Craft::$app instanceof ConsoleApplication) {
                    Console::output(Console::renderColoredString($output));
                }
                Siteseer::error($output);
                Siteseer::getInstance()->errorService->saveErrorRecord($destinations[$index], $code);
                $failures++;

                $count++;
            },
        ]);
        
        $promise = $pool->promise();
        $promise->wait();

        return $failures;
    }

    private function _saveSnapshot(Response $response, string $path, bool $consoleOutput = false): void 
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
            $banner = '<div style="position: fixed; width: 100%; top: 0;background:#ffeeba;color:#856404;padding:10px;text-align:center;font-family:sans-serif;font-size:14px;border-bottom:1px solid #ffeeba;z-index: 9999;">This is a <strong>cached page</strong> generated at ' . $timestamp . '</div>';
            $pageContent = preg_replace('/<body([^>]*)>/', '<body$1>' . $banner, $pageContent, 1);

        try {
            FileHelper::writeToFile($fullPath, $pageContent);
            Siteseer::log('did it work?');
            if ($consoleOutput && Craft::$app instanceof ConsoleApplication) {
                Console::output(Console::renderColoredString('%gSNAPPED'));
            }
        } catch (\Exception $exception) {
            Siteseer::error($exception->getMessage());
        }
    }

    public function deleteAllSnapshots(bool $consoleOutput = false): void
    {
        $storagePath = Craft::$app->getPath()->getStoragePath();
        $cachePath = $storagePath . '/' . 'site-seer';

        if (!is_dir($cachePath)) {
            return;
        }

        if ($consoleOutput && Craft::$app instanceof ConsoleApplication) {
            Console::output('Deleting Snaps');
        }

        try {
            FileHelper::removeDirectory($cachePath);
            if ($consoleOutput && Craft::$app instanceof ConsoleApplication) {
                Console::output('Snaps Deleted');
            }
        } catch (\Exception $exception) {
            Siteseer::error($exception->getMessage());
        }
    }

}
