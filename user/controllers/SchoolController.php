<?php
namespace user\controllers;

use vendor\base\Controller;
use user\models\SchoolModel;

class SchoolController extends Controller
{
	protected $access = [
	    '*' => ['admin'],
        'search' => ['*'],
        'category' => ['*']
	];
	protected $filter = [
			'search' => ['*' => ['default' => ['search' => [], 'order' => null]]],
			'view' => ['*' => ['require' => ['id']]],
			'update' => ['*' => ['require' => ['id']]],
			'delete' => ['*' => ['require' => ['id']]],
	];

    public function actionCategory()
    {
        $model = new SchoolModel();
        $res = $model->_category();
        return $this->response($res, $model, 200);
    }

	public function actionSearch()
	{
		parent::page();
		$model = new SchoolModel();
		$res = $model->_list($this->params['search'], $this->params['order'], $this->pagesize, $this->page);
		return $this->response($res, $model, 200);
	}
	
    public function actionView()
	{
		$model = new SchoolModel();
		$res = $model->_get($this->params['id']);
		return $this->response($res, $model, 200);
	}
	
	public function actionCreate()
	{
		$model = new SchoolModel();
		$res = $model->_set(null, $this->params);
		return $this->response($res, $model, 201);
	}
	
	public function actionUpdate()
	{
		$model = new SchoolModel();
		$res = $model->_set($this->params['id'], $this->params);
		return $this->response($res, $model, 201);
	}
	
	public function actionDelete()
	{
		$model = new SchoolModel();
		$res = $model->_set($this->params['id'], null);
		return $this->response($res, $model, 201);
	}
}