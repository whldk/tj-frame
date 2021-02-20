<?php
namespace user\controllers;

use vendor\base\Controller;
use user\models\UserModel;
use user\models\TeacherModel;
use user\models\IdentityModel;

class TeacherController extends Controller
{
	protected $access = [
			'search' => ['school_admin', 'teacher'],
			'*' => ['school_admin'],
	];
	protected $filter = [
			'search' => ['*' => ['default' => ['status' => UserModel::STATUS_ACTIVE, 'search' => []]]],
			'view' => ['*' => ['require' => ['user_id']]],
			'create' => [],
			'update' => ['*' => ['require' => ['user_id'], 'range' => ['user_id' => 'access_user']]],
			'delete' => ['*' => ['require' => ['user_id'], 'range' => ['user_id' => 'access_user']]],
	];
	

	public function actionCategory()
    {
        $identity = $this->user->getIdentity();
        $model = new TeacherModel();
        $res = $model->_category($identity['school_id']);
        return $this->response($res, $model, 200);
    }

	public function actionSearch()
	{
		parent::page();
		$model = new TeacherModel();
		$identity = $this->user->getIdentity();
		$res = $model->_list($identity['school_id'], $this->params['status'], $this->params['search'], $this->pagesize, $this->page);
		return $this->response($res, $model, 200);
	}
	
	public function actionView()
	{
		$model = new TeacherModel();
		$identity = $this->user->getIdentity();
		$res = $model->_get($identity['school_id'], $this->params['user_id']);
		return $this->response($res, $model, 200);
	}
	
	public function actionCreate()
	{
		$model = new UserModel();
		$identity = $this->user->getIdentity();
		$this->params['school_id'] = $identity['school_id'];
		$this->params['group'] = UserModel::GROUP_TEACHER;
		$res = $model->_set(null, $this->params);
		return $this->response($res, $model, 201);
	}
	
	public function actionUpdate()
	{
		$model = new UserModel();
		$res = $model->_set($this->params['user_id'], $this->params);
		return $this->response($res, $model, 201);
	}
	
	public function actionDelete()
	{
		$model = new UserModel();
		$res = $model->_set($this->params['user_id'], null);
		return $this->response($res, $model, 201);
	}
	
	protected function access_user()
	{
		/*@var $identity \user\models\IdentityModel */
		$identity = $this->user->getIdentity();
		return $identity->canAccessUsers($this->params['user_id'], IdentityModel::GROUP_TEACHER);
	}
	
}