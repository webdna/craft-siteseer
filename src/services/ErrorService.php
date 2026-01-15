<?php

namespace webdna\craftsiteseer\services;

use Craft;
use webdna\craftsiteseer\models\Error as ErrorModel;
use webdna\craftsiteseer\records\Error as ErrorRecord;

use craft\db\Query;
use craft\db\Table;
use Throwable;
use webdna\craftsiteseer\models\Destination;
use webdna\craftsiteseer\Siteseer;
use yii\base\Component;

/**
 * Error Service service
 */
class ErrorService extends Component
{
    public function getErrors(?int $limit = 10, ?int $offset = 0, ?int $siteId = null, string $code = ''): array
    {
        $errors = [];

        $results = $this->_createErrorQuery([
            'limit' => $limit,
            'offset' => $offset,
            'siteId' => $siteId,
            'code' => $code,
        ]);

        foreach ($results['results'] as $result) {
            $error = new ErrorModel();
            $error->id = $result['id'];
            $error->elementId = $result['elementId'];
            $error->siteId = $result['siteId'];
            $error->url = $result['url'];
            $error->code = $result['code'];
            $error->editUrl = '';

            if ($error->elementId) {
                $element = Craft::$app->getElements()->getElementById($error->elementId);
                if ($element) {
                    $error->editUrl = $element->getCpEditUrl();
                }
            }
            
            $errors[] = $error;
        }

        return [
            'total' => $results['total'],
            'errors' => $errors
        ];
    }   
    
    public function saveErrorRecord(Destination $destination, $code): void
    {
        try {
            $error = ErrorRecord::find()
                ->where([
                    'elementId' => $destination->elementId,
                    'siteId' => $destination->siteId,
                ])
                ->one();

            if (!$error) {
                $error = new ErrorRecord();
                $error->elementId = $destination->elementId;
                $error->siteId = $destination->siteId;
            }

            $error->url = $destination->url;
            $error->code = $code;
            $error->save();
        } catch (Throwable $e ) {
            Siteseer::error( $e->getMessage());
            Craft::$app->getErrorHandler()->logException($e);
        }

        return;
    }

    public function deleteError(ErrorModel $error): void
    {
        try {
            $record = ErrorRecord::findOne($error->id);
            if ($record) {
                $record->delete();
            }
        } catch (Throwable $e) {
            Siteseer::error($e->getMessage());
            Craft::$app->getErrorHandler()->logException($e);
        }

        return;
    }

    private function _createErrorQuery(array $config = []): array
    {
        $limit = array_key_exists('limit', $config) ? $config['limit'] : 100;
        $offset = (array_key_exists('offset', $config) && $config['offset']) ? $config['offset'] : 0;
        $siteId = (array_key_exists('siteId', $config) && $config['siteId']) ? ('ss.' . $config['siteId']) : null;
        $code = (array_key_exists('code', $config) && $config['code']) ? ('ss.' . $config['code']) : null;

        $query = ErrorRecord::find()->orderBy($config['orderBy'] ?? 'dateCreated DESC');
            
        if ($siteId) {
            $query->andWhere(['siteId' => $siteId]);
        }
        if ($code) {
            $query->andWhere(['code' => $code]);
        }
        $total = $query->count();
        $query->limit($limit);
        $query->offset($offset);

        return [
            'results' => $query->all(),
            'total' => $total
        ];
    }
}
