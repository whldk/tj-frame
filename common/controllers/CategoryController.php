<?php namespace common\controllers;

use vendor\base\Controller;
use common\models\CategoryModel;
use vendor\exceptions\InvalidConfigException;

abstract class CategoryController extends Controller
{
	protected $access = [
			'*' => ['admin'],
			'list' => ['*'],
			'adminList' => ['admin'],
			'view' => ['*'],
	];
	
	protected $filter = [
			'list' => [
					'*' => [
							'default' => [
                                    'pid' => '',
									'all' => 0,
							]
					],
			],
			'adminList' => [
					'*' => [
							'default' => [
									'pid' => '',
									'status' => null,
									'order' => [],
									'all' => 0,
							]
					],
			],
			'*' => [
					'*' => [
							'require' => ['id'],
					],
			],
			'create' => [],
			'view' => [
					'*' => [
							'require' => ['link_md5'],
					],
			],
			'setOrder' => [
					'*' => [
							'require' => ['id', 'target_id'],
					],
			],
	];
	
	/**
	 * @var \common\models\CategoryModel
	 */
	protected $model = null;
	
	public function __construct($actionMethod)
	{
		if (!in_array(CategoryModel::class, class_parents($this->model))) {
			throw new InvalidConfigException();
		}
		parent::__construct($actionMethod);
	}
	
	public function actionList()
	{
		$res = $this->model::getByPid(
				$this->params['pid'],
				$this->model::STATUS_ACTIVE,
				[],
				$this->params['all']
		);
		return $this->response($res);
	}
	
	public function actionAdminList()
	{
		$res = $this->model::getByPid(
				$this->params['pid'],
				$this->params['status'],
				$this->params['order'],
				$this->params['all']
		);
		
		return $this->response($res);
	}
	
	public function actionCreate()
	{
		$model = new $this->model;
		$res = $model->_set(null, $this->params);
		return $this->response($res, $model, 201);
	}
	
	public function actionView()
	{
		$res = $this->model::_get($this->params['id']);
		return $this->response($res);
	}
	
	public function actionUpdate()
	{
		$model = new $this->model;
		$res = $model->_set($this->params['id'], $this->params);
		return $this->response($res, $model, 204);
	}
	
	public function actionSetOrder()
	{
		$model = new $this->model;
		$res = $model->set_order($this->params['id'], $this->params['target_id']);
		return $this->response($res, $model, 204);
	}
	
	public function actionDelete()
	{
		$model = new $this->model;
		$res = $model->_set($this->params['id'], null);
		return $this->response($res, $model, 204);
	}
}