<?php

namespace daccess1\tilda;

use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\httpclient\Client;

class TildaApi extends Component
{
    const API_BASE_URL = 'http://api.tildacdn.info';
    const API_STATUS_SUCCESS = 'FOUND';
    const API_STATUS_ERROR = 'ERROR';

    const GET_PROJECT_LIST = '/v1/getprojectslist';
    const GET_PROJECT_INFO = '/v1/getproject';
    const GET_PROJECT_INFO_EXPORT = '/v1/getprojectexport';
    const GET_PAGE_LIST = '/v1/getpageslist';
    const GET_PAGE_INFO = '/v1/getpage';
    const GET_PAGE_INFO_FULL = '/v1/getpagefull';
    const GET_PAGE_EXPORT = '/v1/getpageexport';
    const GET_PAGE_EXPORT_FULL = '/v1/getpagefullexport';

    /** @var  string */
    public $assetsPath;
    /** @var  string */
    public $assetsUrl;
    /** @var  string */
    public $publicKey;
    /** @var  string */
    public $secretKey;
    /** @var  TildaExportPage */
    public $pageObj;
    /** @var  Client */
    public $client;
    /** @var  integer */
    public $defaultProjectID;

    public function init()
    {
        $this->client = new Client([
            'transport' => 'yii\httpclient\CurlTransport',
            'baseUrl' => self::API_BASE_URL
        ]);

        if (!$this->publicKey) {
            throw new InvalidConfigException("publicKey can't be empty!");
        }
        if (!$this->secretKey) {
            throw new InvalidConfigException("secretKey can't be empty!");
        }
        if (!$this->assetsPath) {
            throw new InvalidConfigException("assetsPath can't be empty!");
        } elseif($this->assetsPath[strlen($this->assetsPath)-1] != DIRECTORY_SEPARATOR) {
            $this->assetsPath .= DIRECTORY_SEPARATOR;
        }
        if (!$this->assetsUrl) {
            throw new InvalidConfigException("assetsPath can't be empty!");
        } elseif($this->assetsUrl[strlen($this->assetsUrl)-1] != '/') {
            $this->assetsUrl .= '/';
        }
        if (!$this->defaultProjectID)
            $this->defaultProjectID = 0;
    }

    public function getPages($projectID = 0)
    {
        if (!$projectID) {
            if (!$this->defaultProjectID)
                throw new InvalidConfigException("projectID can't be empty.");

            $projectID = $this->defaultProjectID;
        }
        $request = $this->client->createRequest()
            ->setMethod('get')
            ->setUrl(self::GET_PAGE_LIST)
            ->setData([
                'publickey' => $this->publicKey,
                'secretkey' => $this->secretKey,
                'projectid' => $projectID,
            ])->send();

        if ($request->isOk) {
            if (isset($request->data['status']) && $request->data['status'] == self::API_STATUS_SUCCESS) {
                $pageIDs = array_column($request->data['result'], 'id');

                foreach ($pageIDs as $pageID) {
                    $this->getPage($pageID);
                }
            }
        } elseif (isset($request->data['status']) && $request->data['status'] == self::API_STATUS_ERROR) {
            \Yii::warning($request->data['message'], 'yii2-tilda-api');
        }
    }

    /**
     * Returns list of pages in project as array ['id' => 'title']
     * If no project ID is provided uses default setting
     * @param int $projectID
     * @return array
     */
    public function listPages($projectID = 0)
    {
        if (!$projectID) {
            if (!$this->defaultProjectID)
                throw new InvalidConfigException("projectID can't be empty.");

            $projectID = $this->defaultProjectID;
        }
        $request = $this->client->createRequest()
            ->setMethod('get')
            ->setUrl(self::GET_PAGE_LIST)
            ->setData([
                'publickey' => $this->publicKey,
                'secretkey' => $this->secretKey,
                'projectid' => $projectID,
            ])->send();

        if ($request->isOk) {
            if (isset($request->data['status']) && $request->data['status'] == self::API_STATUS_SUCCESS) {
                $pageIDs = array_column($request->data['result'],'title', 'id');
                return $pageIDs;
            }
        } elseif (isset($request->data['status']) && $request->data['status'] == self::API_STATUS_ERROR) {
            \Yii::warning($request->data['message'], 'yii2-tilda-api');
        }
    }

    public function getPage($pageID)
    {
        $request = $this->client->createRequest()
            ->setMethod('get')
            ->setUrl(self::GET_PAGE_EXPORT)
            ->setData([
                'publickey' => $this->publicKey,
                'secretkey' => $this->secretKey,
                'pageid' => $pageID,
            ])->send();

        if ($request->isOk) {
            if (isset($request->data['status']) && $request->data['status'] == self::API_STATUS_SUCCESS) {
                $this->pageObj = new TildaExportPage($request->data['result'], $this->assetsPath, $this->assetsUrl);
            }
        } elseif (isset($request->data['status']) && $request->data['status'] == self::API_STATUS_ERROR) {
            \Yii::warning($request->data['message'], 'yii2-tilda-api');
        }
    }

    public function loadPage($pageID) {
        return TildaRender::loadPage($pageID);
    }

    public function verifyPublicKey($publicKey) {
        return ($this->publicKey == $publicKey) ? true : false;
    }

    /**
     * Renders ActiveForm-compatiable select widget
     * @param $model
     * @param $field
     * @param int $projectID
     * @return mixed
     */
    public function renderPageSelect($model,$field,$projectID = 0) {
        if (!$projectID) {
            if (!$this->defaultProjectID)
                throw new InvalidConfigException("projectID can't be empty.");

            $projectID = $this->defaultProjectID;
        }
        $config = [
            'model' => $model,
            'field' => $field,
            'project' => $projectID,
        ];
        $result = TildaPageSelect::widget($config);

        return $result;
    }

    /**
     * @param Controller $controller
     * @param integer $pageID
     * @return string
     */
    public function registerAssets($controller,$pageID) {
        $page = TildaRender::loadAssets($pageID);
        return $controller->renderPartial('@vendor/daccess1/yii2-tilda-api/views/register-assets',[
            'page' => $page
        ]);
    }

    public function renderHtml($pageID) {
        return TildaRender::loadHtml($pageID);
    }
}
