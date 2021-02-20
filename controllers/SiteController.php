<?php namespace controllers;

use vendor\sdk\IlabJwt;
use vendor\base\{Controller, Upload, UploadManager};
use user\models\ {
    LoginLogModel, LoginModel, SchoolModel,
    TempUserModel, UserModel,
    BatchSignupModel
};

class SiteController extends Controller
{
    protected $access = [
        '*' => ['*'],
        'batchSignup' => ['school_admin', 'teacher'],
        'getTpl' => ['school_admin', 'teacher'],
        'profile' => ['@'],
        'passwordReset' => ['@'],
        'upload' => ['@'],
        'userReset' => ['@'],
    ];

    protected $filter = [
        'index' => [],
        'upload' => [
            '*' => [
                'require' => ['m', 'field', 'mime']
            ]
        ],
        'batchSignup' => [
            '*' => [
                'require' => ['group', 'overwrite'],
            ],
            'school_admin' => [
                'default' => 'access_class',
            ],
            'teacher' => [
                'default' => 'access_class',
            ],
        ],
        'pk' => [
            '*' => [
                'default' => ['base64' => 1]
            ],
        ],
        'isGuest' => [
            '*' => [
                'default' => ['school_alias' => null],
            ]
        ],
        'school' => [
            '*' => [
                'require' => ['school_alias'],
            ]
        ],
        'login' => [
            '*' => [
                'require' => ['school_alias', 'username', 'password', 'remember'],
            ]
        ],
        'adminLogin' => [
            '*' => [
                'require' => [
                    'username', 'password', 'remember',
                    'msg' => [
                        'username' => '用户名不可为空',
                        'password' => '密码不可为空',
                        'remember' => '记住我不可为空'
                    ]
                ],
            ]
        ],
        'passwordReset' => [
            '*' => [
                'require' => ['old_password', 'password']
            ]
        ],
        'userReset' => [
            '*' => [
                'require' => ['old_password', 'password', 'realname', 'gender']
            ]
        ],
        'sourceCount' => [
            '*' => [
                'default' => [
                    'mode' => 0 //默认 esp + wx 入口
                ]
            ]
        ],
        'schoolList' => []
    ];


    public function actionIndex()
    {
        return 'Home Page';
    }

    public function actionUpload()
    {
        $dir = $this->params['m'];
        $dir .= DIRECTORY_SEPARATOR . $this->params['field'];

        $upload = new UploadManager($dir);
        $res = $upload->upload(null, $this->params['mime'], [], null, false, false);

        if ($res === false) {
            $errors = $upload->errors();
            if (isset($errors[Upload::ERR_UPLOAD]) || isset($errors[Upload::ERR_MIME])) {
                $this->response->setStatusCode(400);
            } else {
                $this->response->setStatusCode(500);
            }
            $res = ['error' => $errors];
        } else {
            $this->response->setStatusCode(201);
            $res = [];
        }

        $res['urls'] = $upload->getUrls();

        return $res;
    }

    public function actionBatchSignup()
    {
        $data = $file = null;

        //是文件，读取数据并返回
        if (isset($_FILES['batch_signup']['tmp_name'])) {
            $file = $_FILES['batch_signup']['tmp_name'];
            if (!is_file($file)) {
                $this->addError('上传文件无效');
                return $this->response(null, $this, 400);
            }

            $model = new BatchSignupModel();
            if (!$model->setData($data, $file, $this->params['group'])) {
                return $this->response(null, $model, 400);
            }
            return $this->response($model->getData());
        }

        //是数据，开始批量插入
        if (isset($this->params['data'])) {
            $data = (array)$this->params['data'];
            if (!$data) {
                $this->addError('data 上传数据为空');
                return $this->response(null, $this, 400);
            }
        } else {
            $this->addError('data 上传数据为空');
            return $this->response(null, $this, 400);
        }

        $model = new BatchSignupModel();

        if (!$model->setData($data, $file, $this->params['group'])) {
            return $this->response(null, $model, 400);
        }

        $res = $model->batch_signup($this->params['school_id'], $this->params['class_id'], !!$this->params['overwrite']);

        return $this->response($res, $model, 201);
    }

    public function actionPk()
    {
        $res = [
            'pk' => file_get_contents($this->params['base64'] ? RSA_PB_KEY_BASE64 : RSA_PB_KEY),
            'nonce' => $this->security->generateRandomString(13),
            'ts' => time()
        ];

        $this->session->set('nonce', $res['nonce']);
        $this->session->set('ts', $res['ts']);

        return $this->response($res);
    }

    public function actionLogin()
    {

        $model = new LoginModel();
        $res = $model->login($this->params['school_alias'], $this->params['username'], $this->params['password'], $this->params['remember']);
        if ($res) {
            $identity = $this->user->getIdentity();
            $profile = $identity->profile();
            $res = $profile;
            //新增登录日志记录
            $loginLogModel =  new LoginLogModel();
            $login = $loginLogModel->LoginLog($res, LoginLogModel::SOURCE_WEB);
            if ($login) {
                $res += $login;
            }
        }

        return $this->response($res, $model, 200);
    }

    public function actionAdminLogin()
    {
        $model = new LoginModel();
        $res = $model->login(null, $this->params['username'], $this->params['password'], $this->params['remember']);
        if ($res) {
            $identity = $this->user->getIdentity();
            $profile = $identity->profile();
            $res = $profile;
            //新增登录日志记录
            $loginLogModel =  new LoginLogModel();
            $login = $loginLogModel->LoginLog($res, LoginLogModel::SOURCE_WEB);
            if ($login) {
                $res += $login;
            }
        }
        return $this->response($res, $model, 200);
    }

    public function actionIsGuest()
    {
        if ($this->user->isGuest()) {
            return $this->attemptLogin();
        }

        $identity = $this->user->getIdentity();
        $profile = $identity->profile();
        if (@$profile['school_alias'] === $this->params['school_alias']) {
            $loginLogModel =  new LoginLogModel();
            $login = $loginLogModel->LoginLog($profile, LoginLogModel::SOURCE_WEB);
            if ($login) {
                $profile += $login;
            }
            return ['status' => 0, 'profile' => $profile];
        }

        $this->user->logout();
        return $this->attemptLogin();
    }

    protected function attemptLogin()
    {
        if (!in_array($this->params['school_alias'], ['zy'], true)) {
            return ['status' => 1];
        }

        $request = $this->request;
        $referer = $request->getReferrer();

        $prefix = 'http://mengoo.doctor-u.cn/clinic/login.html?token=';
        $prefixLen = strlen($prefix);

        if (strpos($referer, $prefix) === 0) {
            $token = urldecode(substr($referer, $prefixLen));
            return $this->ilabLogin($token);
        } else {
            return $this->tempLogin();
        }
    }

    /**
     * ['status' => xxx, ...] status指的是isGuest
     */
    protected function ilabLogin($token)
    {
        $ilabUserFromToken = IlabJwt::getBody($token);

        if (!$ilabUserFromToken) {
            return ['status' => 1];
        }

        $ilabUser = TempUserModel::getIlabUserByIlabId($ilabUserFromToken['id']);
        if ($ilabUser) {
            $loginModel = new LoginModel();
            $login = $loginModel->loginByUserId($ilabUser['user_id'], UserModel::GROUP_STUDENT, 1);
            $profile = $this->user->getIdentity()->profile();
            $loginLogModel =  new LoginLogModel();
            $loginLog = $loginLogModel->LoginLog($profile, LoginLogModel::SOURCE_TEMP);
            if ($loginLog) {
                $profile += $loginLog;
            }
            return $login ? ['status' => 0, 'profile' => $profile] : ['status' => 1];
        } else {
            return $this->tempLogin(
                $ilabUserFromToken['un'] . time() . $this->security->generateRandomString(3),
                'ilab_' . $ilabUserFromToken['dis'],
                TempUserModel::TEMP_ILAB,
                $ilabUserFromToken);
        }
    }

    /**
     * ['status' => xxx, ...] status指的是isGuest
     */
    protected function tempLogin($username = null, $realname = null, $temp = TempUserModel::TEMP_RANDOM, $ilabUserFromToken = null)
    {
        $school = SchoolModel::getByAlias($this->params['school_alias']);
        if (!$school) {
            return ['status' => 1];
        }
        $res = TempUserModel::generateTempUser([
            'school_id' => $school[0]['id'],
            'username' => $username === null ? $this->params['school_alias'] . '_' . time() . $this->security->generateRandomString(3) : $username,
            'realname' => $realname === null ? $this->params['school_alias'] : $realname,
            'password' => '123456',
        ], $temp, $ilabUserFromToken);

        if ($res) {
            $loginModel = new LoginModel();
            $login = $loginModel->loginByUserId($res['id'], UserModel::GROUP_STUDENT, 1);
            $profile = $this->user->getIdentity()->profile();

            $loginLogModel =  new LoginLogModel();
            $loginLog = $loginLogModel->LoginLog($profile, LoginLogModel::SOURCE_TEMP);
            if ($loginLog) {
                $profile += $loginLog;
            }

            return $login ? ['status' => 0, 'profile' => $profile] : ['status' => 1];
        } else {
            return ['status' => 1];
        }
    }

    public function actionSchool()
    {
        $res = SchoolModel::getByAlias($this->params['school_alias']);
        return $this->response($res);
    }

    public function actionProfile()
    {
        $identity = $this->user->getIdentity();
        $profile = $identity->profile();
        return $this->response($profile, $identity, 200);
    }

    public function actionLogout()
    {
        if ($this->user->isGuest()) {
            return ['status' => 1];
        }

        $this->user->logout();
        return ['status' => $this->user->isGuest() ? 1 : 0];
    }

    public function actionGetTpl()
    {
        $index = isset($_GET['tpl']) ? (in_array($_GET['tpl'], [0 ,1]) ? $_GET['tpl'] : 1) : 1;

        $tpl = [
            [DIR_APP . DIRECTORY_SEPARATOR .'/upload/tpl/teacherTemplate.xlsx', '老师模板.xlsx'],
            [DIR_APP . DIRECTORY_SEPARATOR .'/upload/tpl/studentTemplate.xlsx', '学生模板.xlsx']
        ];

        $this->response->sendFile($tpl[$index][0], $tpl[$index][1]);
    }

    public function actionPasswordReset()
    {
        $identity = $this->user->getIdentity();
        if (!LoginModel::verify_password($this->params['old_password'], $identity['password'])) {
            $this->addError('原始密码验证错误');
            return $this->response(null);
        }

        $userModel = new UserModel();
        $res = $userModel->_set($identity['id'], ['password' => $this->params['password']]);
        return $this->response($res, $userModel, 204);
    }

    public function actionUserReset()
    {
        $identity = $this->user->getIdentity();

        //验证密码
        if (!password_verify($this->params['old_password'], $identity['password'])) {
            $this->addError('密码验证错误');
            return $this->response(null);
        }

        $userModel = new UserModel();
        $res = $userModel->_set($identity['id'], [
            'password' => $this->params['password'],
            'realname' => $this->params['realname'],
            'gender' => $this->params['gender']
        ]);
        return $this->response($res, $userModel, 204);
    }

    protected function access_class()
    {
        $identity = $this->user->getIdentity();
        $role = $this->user->getRole();

        $this->params['school_id'] = $identity['school_id'];

        if ($role === 'school_admin') {
            $this->params['class_id'] = null;
        } else {
            $this->params['class_id'] = $identity->getClassId();
            $this->params['group'] = UserModel::GROUP_STUDENT;
        }
    }

    /**
     * 获取登陆入口统计
     * @return array|null
     * @throws \vendor\exceptions\InvalidConfigException
     */
    public function actionSourceCount()
    {
        $model = new LoginLogModel();
        $identity = $this->user->getIdentity();
        $this->params['school_id'] = $identity['school_id'] ?? null;
        $res = $model->SourceCount($this->params['school_id'], $this->params['mode']);
        return $this->response($res, $model, 204);
    }

    public function actionSchoolList()
    {
        $model = new SchoolModel();
         $res = $model::_select(null, ['id', 'pinyin', 'name', 'alias'], null,[['pinyin','desc']]);
        return $this->response($res, $model, 201);
    }

}
