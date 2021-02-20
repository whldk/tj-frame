<?php
namespace user\controllers;

use vendor\base\Controller;
use user\models\UserModel;

class UserController extends Controller
{
	protected $access = [
			'*' => ['admin'],
			'avatar' => ['@'],
            'delete' => ['admin', 'school_admin'],
	];
	protected $filter = [
			'search' => ['*' => ['default' => ['school_id' => null, 'group' => null, 'status' => UserModel::STATUS_ACTIVE, 'search' => []]]],
			'view' => ['*' => ['require' => ['id']]],
			'update' => ['*' => ['require' => ['id']]],
			'delete' => ['*' => ['require' => ['id']]],
			'avatar' => ['*' => ['require' => ['zoom', 'x', 'y', 'w', 'h'], 'require-file' => ['avatar']]],
	];
	
	public function actionSearch()
	{
		parent::page();
		$model = new UserModel();
		$res = $model->_list($this->params['school_id'], $this->params['group'], $this->params['status'], $this->params['search'], $this->pagesize, $this->page);
		return $this->response($res, $model, 200);
	}
	
	public function actionView()
	{
		$model = new UserModel();
		$res = $model->_get($this->params['id']);
		return $this->response($res, $model, 200);
	}
	
	public function actionUpdate()
	{
		$model = new UserModel();
		$res = $model->_set($this->params['id'], $this->params);
		return $this->response($res, $model, 201);
	}
	
	public function actionDeactive()
	{
		$model = new UserModel();
		$res = $model->deactive($this->params['id']);
		return $this->response($res, $model, 201);
	}
	
	public function actionDelete()
	{
		$model = new UserModel();
		$res = $model->_set($this->params['id'], null);
		return $this->response($res, $model, 201);
	}
	
	public function actionAvatar()
	{
		$identity = $this->user->getIdentity();
		$model = new UserModel();
		$res = $model->set_avatar($identity['id'], $this->params['zoom'], $this->params['x'], $this->params['y'], $this->params['w'], $this->params['h']);
		return $this->response($res, $model, 200);
	}
}