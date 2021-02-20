<?php
namespace user\controllers;

use user\models\SchoolClassModel;
use vendor\base\Controller;
use user\models\UserModel;
use user\models\StudentModel;
use user\models\IdentityModel;
use vendor\base\Helpers;

class StudentController extends Controller
{
	protected $access = [
			'*' => ['school_admin'],
			'search' => ['school_admin', 'teacher'],
			'view' => ['school_admin', 'teacher'],
			'create' => ['school_admin', 'teacher'],
			'update' => ['school_admin', 'teacher'],
			'setMainClass' => ['school_admin', 'teacher'],
			'setClass' => ['school_admin', 'teacher'],
			'delete' => ['school_admin', 'teacher'],
	];
	protected $filter = [
			'search' => [
					'*' => [
							'default' => [
									'status' => UserModel::STATUS_ACTIVE, 
									'class_id' => null,
									'search' => []
							]
					]
			],
			'view' => ['*' => ['require' => ['user_id']]],
			'create' => [
					'*' => [
							'require' => ['class_id'], 
							'range' => ['class_id' => 'access_class']
					]
			],
			'update' => [
					'*' => [
							'require' => ['user_id'],
							'default' => ['class_id' => null],
							'range' => ['user_id' => 'access_user']
					]
			],
			'setMainClass' => ['*' => ['require' => ['class_id', 'user_id'], 'range' => ['class_id' => 'access_class', 'user_id' => 'access_user']]],
			'setClass' => ['*' => ['require' => ['class_id', 'user_id'], 'range' => ['class_id' => 'access_class', 'user_id' => 'access_user']]],
			'delete' => [
					'*' => [
							'require' => ['user_id', 'class_id'],
							'range' => ['user_id' => 'access_user']
					]
			],
	];

    public function actionSearch()
    {
        parent::page();
        $model = new StudentModel();
        $identity = $this->user->getIdentity();
        if ($identity->getRole() === 'teacher') {
            $this->params['class_id'] = isset($this->params['class_id']) &&  $this->params['class_id'] ?
                array_intersect($identity->getClassId(), (array)$this->params['class_id']) : $identity->getClassId();
        }
        $res = $model->_list($identity['school_id'], $this->params['class_id'], $this->params['status'], $this->params['search'], $this->pagesize, $this->page);
        return $this->response($res, $model, 200);
    }

	public function actionView()
	{
		$model = new StudentModel();
		$identity = $this->user->getIdentity();
		$res = $model->_get($identity['school_id'], $this->params['user_id']);
		return $this->response($res, $model, 200);
	}
	
	public function actionCreate()
	{
		$model = new StudentModel();
		$identity = $this->user->getIdentity();
		$this->params['school_id'] = $identity['school_id'];
		$this->params['group'] = UserModel::GROUP_STUDENT;
		$res = $model->_set($this->params['school_id'], null, $this->params['class_id'], $this->params);
		return $this->response($res, $model, 201);
	}
	
	public function actionUpdate()
	{
		$model = new StudentModel();
		$identity = $this->user->getIdentity();
		$res = $model->_set($identity['school_id'], $this->params['user_id'], $this->params['class_id'], $this->params);
		return $this->response($res, $model, 201);
	}
	
	public function actionSetMainClass()
	{
		$identity = $this->user->getIdentity();
		$model = new StudentModel();
		$res = $model->set_main_class($identity['school_id'], $this->params['user_id'], $this->params['class_id']);
		return $this->response($res, $model, 201);
	}
	
// 	public function actionSetClass()
// 	{
// 		$identity = $this->user->getIdentity();
// 		$model = new StudentModel();
// 		$res = $model->_set_class($identity['school_id'], $this->params['class_id'], $this->params['user_id']);
// 		return $this->response($res, $model, 201);
// 	}

    public function actionDelete()
    {
        $identity = $this->user->getIdentity();
		$model = new StudentModel();
		$res = $model->_del($identity['school_id'], $this->params['user_id'], $this->params['class_id'], null);
		return $this->response($res, $model, 201);
    }

//	public function actionDelete()
//	{
//		$identity = $this->user->getIdentity();
//		$model = new StudentModel();
//		$res = $model->_set($identity['school_id'], $this->params['user_id'], $this->params['class_id'], null);
//		return $this->response($res, $model, 201);
//	}
	
	protected function access_class()
	{
		/*@var $identity \user\models\IdentityModel */
		$identity = $this->user->getIdentity();
		return $identity->canAccessClasses($this->params['class_id']);
	}
	
	protected function access_user()
	{
		/*@var $identity \user\models\IdentityModel */
		$identity = $this->user->getIdentity();
		return $identity->canAccessUsers($this->params['user_id'], IdentityModel::GROUP_STUDENT);
	}
}