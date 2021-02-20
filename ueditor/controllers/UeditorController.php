<?php

namespace ueditor\controllers;

use vendor\base\Controller;
use vendor\base\Response;

class UeditorController extends Controller
{
	const DIR_UE = __DIR__ . '/..';
	const DIR_ACTIONS = __DIR__ . '/../actions';
	const DIR_CONFIG = __DIR__ . '/../config';
	const DIR_LIB = __DIR__ . '/../lib';
	
	protected $access = ['*' => ['admin']];
	
	public function actionIndex()
	{
		$CONFIG = require_once self::DIR_CONFIG . '/editor_config.php';
		$action = $_GET['action'];
		
		switch ($action) {
		    case 'config':
		        $result =  json_encode($CONFIG);
		        break;
		
		    /* 上传图片 */
		    case 'uploadimage':
		    /* 上传涂鸦 */
		    case 'uploadscrawl':
		    /* 上传视频 */
		    case 'uploadvideo':
		    /* 上传文件 */
		    case 'uploadfile':
		        $result = include(self::DIR_ACTIONS . "/action_upload.php");
		        break;
		
		    /* 列出图片 */
		    case 'listimage':
		        $result = include(self::DIR_ACTIONS . "/action_list.php");
		        break;
		    /* 列出文件 */
		    case 'listfile':
		        $result = include(self::DIR_ACTIONS . "/action_list.php");
		        break;
		
		    /* 抓取远程文件 */
		    case 'catchimage':
		        $result = include(self::DIR_ACTIONS . "/action_crawler.php");
		        break;
		
		    default:
		        $result = json_encode(array(
		            'state'=> '请求地址出错'
		        ));
		        break;
		}
		
		/* 输出结果 */
		if (isset($_GET["callback"])) {
		    if (preg_match("/^[\w_]+$/", $_GET["callback"])) {
		        $result = htmlspecialchars($_GET["callback"]) . '(' . $result . ')';
		    } else {
		        $result = json_encode(array(
		            'state'=> 'callback参数不合法'
		        ));
		    }
		}
		
		$this->response->format = Response::FORMAT_HTML;
		
		return $result;
	}
}