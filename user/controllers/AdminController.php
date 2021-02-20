<?php
namespace user\controllers;

use vendor\base\Controller;
use user\models\AdminModel;

class AdminController extends Controller
{
	protected $access = [
			'*' => ['admin'],
	];
	protected $filter = [
			'search' => ['*' => ['default' => ['status' => AdminModel::STATUS_ACTIVE, 'search' => []]]],
			'view' => ['*' => ['require' => ['id']]],
			'update' => ['*' => ['require' => ['id']]],
			'deactive' => ['*' => ['require' => ['id']]],
			'delete' => ['*' => ['require' => ['id']]],
	];
	
	public function actionSearch()
	{
		parent::page();
		$model = new AdminModel();
		$res = $model->_list($this->params['status'], $this->params['search'], $this->pagesize, $this->page);
		return $this->response($res, $model, 200);
	}
	
	public function actionView()
	{
		$model = new AdminModel();
		$res = $model->_get($this->params['id']);
		return $this->response($res, $model, 200);
	}
	
	public function actionCreate()
	{
		$model = new AdminModel();
		$res = $model->_set(null, $this->params);
		return $this->response($res, $model, 201);
	}
	
	public function actionUpdate()
	{
		$model = new AdminModel();
		$res = $model->_set($this->params['id'], $this->params);
		return $this->response($res, $model, 201);
	}
	
	public function actionDeactive()
	{
		$model = new AdminModel();
		$res = $model->deactive($this->params['id']);
		return $this->response($res, $model, 201);
	}
	
	public function actionDelete()
	{
		$model = new AdminModel();
		$res = $model->_set($this->params['id'], null);
		return $this->response($res, $model, 201);
	}
	
}