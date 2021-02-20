<?php
namespace user\controllers;

use vendor\base\Controller;
use user\models\UserModel;
use user\models\SchoolAdminModel;
use user\models\IdentityModel;

class SchoolAdminController extends Controller
{
	protected $access = [
			'search' => ['admin', 'school_admin'],
			'view' => ['admin', 'school_admin'],
			'create' => ['admin', 'school_admin'],
			'update' => ['admin', 'school_admin'],
            'delete' => ['admin', 'school_admin']
	];
	protected $filter = [
			'search' => ['*' => ['default' => ['school_id' => null, 'status' => UserModel::STATUS_ACTIVE, 'search' => []]]],
			'view' => ['*' => ['require' => ['user_id'], 'default' => ['school_id' => null]]],
			'create' => ['admin' => ['require' => ['school_id']]],
			'update' => ['*' => ['require' => ['user_id'], 'range' => ['user_id' => 'access_user']]],
            'delete' => ['*' => ['require' => ['user_id']]],
	];
	

	public function actionSearch()
	{
		parent::page();
		$model = new SchoolAdminModel();
		$identity = $this->user->getIdentity();
		!$identity['school_id'] ?: $this->params['school_id'] = $identity['school_id'];
		$res = $model->_list($this->params['school_id'], $this->params['status'], $this->params['search'], $this->pagesize, $this->page);
		return $this->response($res, $model, 200);
	}
	
	public function actionView()
	{
		$model = new SchoolAdminModel();
		$identity = $this->user->getIdentity();
		!$identity['school_id'] ?: $this->params['school_id'] = $identity['school_id'];
		$res = $model->_get($this->params['school_id'], $this->params['user_id']);
		return $this->response($res, $model, 200);
	}
	
	public function actionCreate()
	{
		$model = new UserModel();
		$identity = $this->user->getIdentity();
		!$identity['school_id'] ?: $this->params['school_id'] = $identity['school_id'];
		$this->params['group'] = UserModel::GROUP_SCHOOL_ADMIN;
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
		return $identity->canAccessUsers($this->params['user_id'], IdentityModel::GROUP_SCHOOL_ADMIN);
	}
}