<?php
namespace user\controllers;

use vendor\base\Controller;
use user\models\SchoolClassModel;
use user\models\UserClassModel;

class SchoolClassController extends Controller
{
	protected $access = [
			'*' => ['school_admin'],
            'list' => ['school_admin', 'teacher'],
			'search' => ['school_admin', 'teacher'],
			'view' => ['school_admin', 'teacher'],
			'options' => ['school_admin', 'teacher']
	];
	protected $filter = [
			'search' => [
					'*' => [
							'default' => [
									'search' => [],
									'grade' => null,
									'status' => null,
							]
					]
			],
            'list' => [],
			'options' => [
					'*' => [
							'default' => [
									'search' => [],
							]
					]
			],
			'view' => ['*' => ['require' => ['id']]],
			'update' => ['*' => ['require' => ['id']]],
			'delete' => ['*' => ['require' => ['id']]],
			'addTeacher' => ['*' => ['require' => ['id', 'user_id']]],
			'delTeacher' => ['*' => ['require' => ['id', 'user_id']]],
			'deactivate' => ['*' => ['require' => ['grade'],]],
	];

    /**
     * 校管、或老师 获取班级分类
     * @return array|null
     * @throws \vendor\exceptions\InvalidConfigException
     */
	public function actionList()
    {
        $model = new SchoolClassModel();
        $identity = $this->user->getIdentity();
        $user_id = null;
        if ($identity->getRole() == 'teacher') {
            $user_id = $identity['id'];
        }
        $res = $model->_list_not_page($identity['school_id'], $user_id);
        return $this->response($res, $model, 200);
    }

	public function actionSearch()
	{
		parent::page();
		$model = new SchoolClassModel();
		$identity = $this->user->getIdentity();
		if ($this->user->getRole() === 'teacher') {
			$user_id = $identity['id'];
		} else {
			$user_id = null;
		}
		$res = $model->_list(
		    $identity['school_id'],
            $user_id,
            $this->params['grade'],
            $this->params['status'],
            $this->params['search'],
            $this->pagesize,
            $this->page
        );
		return $this->response($res, $model, 200);
	}
	
	public function actionView()
	{
		$model = new SchoolClassModel();
		$res = $model->_get($this->params['id']);
		return $this->response($res, $model, 200);
	}
	
	public function actionCreate()
	{
		$identity = $this->user->getIdentity();
		$this->params['school_id'] = $identity['school_id'];
		
		$model = new SchoolClassModel();
		$res = $model->_set(null, $this->params);
		return $this->response($res, $model, 201);
	}
	
	public function actionUpdate()
	{
		$model = new SchoolClassModel();
		$res = $model->_set($this->params['id'], $this->params);
		return $this->response($res, $model, 201);
	}
	
	public function actionDelete()
	{
		$model = new SchoolClassModel();
		$res = $model->_set($this->params['id'], null);
		return $this->response($res, $model, 201);
	}

	public function actionOptions()
	{
		parent::page(true);
		$model = new SchoolClassModel();
		$identity = $this->user->getIdentity();
		
		if ($this->user->getRole() === 'teacher') {
			$user_id = $identity['id'];
		} else {
			$user_id = null;
		}
		
		$res = $model->options($identity['school_id'], $user_id, 
				$this->params['search'], $this->pagesize, $this->page);
		
		return $this->response($res, $model, 200);
	}
	
	public function actionDeactivate()
	{
		$identity = $this->user->getIdentity();
		$model = new SchoolClassModel();
		$res = $model->deactivate($identity['school_id'], $this->params['grade']);
		return $this->response($res, $model, 201);
	}
	
	public function actionAddTeacher()
	{
		$model = new UserClassModel();
		$identity = $this->user->getIdentity();
		$res = $model->_set($identity['school_id'], $this->params['id'], $this->params['user_id'], []);
		return $this->response($res, $model, 201);
	}
	
	public function actionDelTeacher()
	{
		$model = new UserClassModel();
		$identity = $this->user->getIdentity();
		$res = $model->_set($identity['school_id'], $this->params['id'], $this->params['user_id'], null);
		return $this->response($res, $model, 201);
	}
}