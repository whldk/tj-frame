<?php namespace controllers;

use models\TestsModel;
use vendor\base\Controller;

/**
 * 框架测试说明
 * Class TestsController
 * @package controllers
 */
class TestsController extends Controller
{
    //实例
    /**
     * $access  校验用户权限配置
     * ['*' => 'admin, school_admin', 'teacher', 'student']  四种身份
     * ['*' => '*', '@']  * 代表不限制权限, @ 代表必须是登录用户
     * @var array
     */
    public $access = [
        '*' => ['@']
    ];

    /**
     * $filter 参数过滤验证器
     * ['*' => '* 代表所有的用户都需要接受验证']
     * ['require', 'require-file', 'range', 'default']
     * @var array
     */
    public $filter = [
        '*' => [
            '*' => [
                'require' => ['id'],
                'default' => 'checkTest',
                'range' => 'checkMode'
            ]
        ],
        'index' => [
            '*' => [
                'require' => [
                     'status', 'mode', 'password',
                    'msg' => [
                        'status' => '状态不可为空',
                        'mode' => '模式不可为空'
                    ]
                ],
                'require-file' => [
                    'upload', 'img',
                    'msg' => [
                        'upload' => 'upload 文件上传不可为空',
                        'img' => 'img 文件上传不可为空'
                    ]
                ],
//                'range' => [
//                    'status' => [TestsModel::OPEN, TestsModel::CLOSE, TestsModel::DELETE],
//                    'mode' => 'checkMode',
//                    'msg' => [
//                        'status' => 'status 不在正确范围内',
//                        'mode' => 'mode 参数直接返回的 false'
//                    ],
//                ],
                'default' => [
                    'id' => 'id'
                ]
            ]
        ]
    ];

    public function actionIndex()
    {
        //能接受成功、说明权限、基础的参数验证已经成功了
        //var_dump(count($this->params));
        $model = new TestsModel();
        $res = $model->_set(null, $this->params);
        return $this->response($res, $model,201);
    }

    //来自range的方法验证
    protected function checkMode()
    {
        return true;
    }

    //来自default的默认方法
    public function checkTest()
    {
       return true;
    }
}