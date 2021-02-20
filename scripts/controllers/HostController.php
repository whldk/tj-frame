<?php
namespace scripts\controllers;

use models\AlibabaModel;
use vendor\base\Controller;

class HostController extends Controller
{
    public function actionRefresh()
    {
        $model = new AlibabaModel();
        $res = $model->DescribeInstanceMonitorData();
        return $this->response($res, $model, 201);
    }
}