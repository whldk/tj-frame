<?php
/* ****** Table Names ****** */
define('TBL_SESSION', 'session');
define('TBL_SCHOOL', 'school');
define('TBL_USER', 'user');
define('TBL_ADMIN', 'admin');
define('TBL_STUDENT', 'student');
define('TBL_SCHOOL_CLASS', 'school_class');
define('TBL_USER_CLASS', 'user_class');

define('TBL_APP', 'app');
define('TBL_APP_TOKEN', 'app_token');
define('TBL_POST', 'post');
define('TBL_POST_CATEGORY', 'post_category');
define('TBL_RESOURCE_CATEGORY', 'resource_category');
define('TBL_RESOURCE', 'resource');
define('TBL_RESOURCE_FOLDER', 'resource_folder');
define('TBL_RESOURCE_SHARE_APPLY', 'resource_share_apply');
define('TBL_RESOURCE_INNER_REFERENCE', 'resource_inner_reference');

define('TBL_ESP_EXPERIMENT', 'esp_experiment');
define('TBL_ESP_EXPERIMENT_CATEGORY', 'esp_experiment_category');

define('TBL_EXAM', 'exam');
define('TBL_EXAM_ACCESS', 'exam_access');
define('TBL_EXAM_RECORD', 'exam_record');
define('TBL_EXAM_USER_STATS', 'exam_user_stats');
define('TBL_EXAM_CLASS_STATS', 'exam_class_stats');
define('TBL_EXAM_STATS', 'exam_stats');

define('TBL_EXAM_QUESTION_CASE', 'exam_question_case');
define('TBL_EXAM_QUESTION_A1', 'exam_question_a1');
define('TBL_EXAM_QUESTION_A3', 'exam_question_a3');
define('TBL_EXAM_QUESTION_B1', 'exam_question_b1');
define('TBL_EXAM_QUESTION_BL', 'exam_question_bl');
define('TBL_EXAM_QUESTION_OPTION', 'exam_question_option');
define('TBL_EXAM_QUESTION_OPTION_B1', 'exam_question_option_b1');

define('TBL_EXAM_MAIN', 'exam_main');
define('TBL_EXAM_PAPER', 'exam_paper');
define('TBL_EXAM_PAPER_QUESTION', 'exam_paper_question');
define('TBL_EXAM_PAPER_CATEGORIES', 'exam_paper_categories');



define('TBL_USER_GROUP', 'user_group');
define('TBL_USER_GROUP_TEST', 'user_group_test');
define('TBL_USER_GROUP_SHARE', 'user_group_share');
define('TBL_USER_GROUP_LIST', 'user_group_list');
/* ****** Rsa Keys ****** */

define('RSA_PR_KEY', DIR_APP . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'rsa_private_key.pem');
define('RSA_PB_KEY', DIR_APP . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'rsa_public_key.pem');
define('RSA_PB_KEY_BASE64', DIR_APP . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'rsa_public_key_base64.pem');

/* ****** Server Err Keys ****** */

define('ERR_SERVER', -1);
define('ERR_LOGIN', -403);
define('SERVER_ERR', 'server_err');
define('SERVER_ERR_DB', 'server_err_db');
define('SERVER_ERR_CONFIG', 'server_err_config');

/* ****** Resource Access Urls ****** */

define('RESOURCE_OUTER_HOST', 'mengoo.com/resources/ror/access');	//资源对外访问地址

/* ****** Resources Config Files ****** */
define('CONFIG_RESOURCE', __DIR__ . '/../resources/config');
define('CONFIG_RESOURCE_TYPES', CONFIG_RESOURCE . '/resourceTypes.php');
define('CONFIG_RESOURCE_MIMES', CONFIG_RESOURCE . '/resourceMimes.php');
define('CONFIG_RESOURCE_FOLDER_MIMES', CONFIG_RESOURCE . '/resourceFolderMimes.php');


/* ilab app */
//define('ILAB_SERVER_HOST', 'ilab-x.com');
//define('ILAB_APP_NAME', '基于ESP虚拟病人的气胸临床前整合实验教学');
//define('ILAB_APP_ISSUER_ID', 100109);
//define('ILAB_APP_SECRET_KEY', 'ndp64h');
//define('ILAB_APP_AES_KEY', 'qHRyucaM7OgGxAcSQ3fc+oh6MKAAeeJ80RMX/gIhufA=');

//define('ILAB_SERVER_HOST', '202.205.145.156:8017');
//define('ILAB_APP_NAME', '基于ESP虚拟病人的气胸临床前整合实验教学');
//define('ILAB_APP_ISSUER_ID', 100400);
//define('ILAB_APP_SECRET_KEY', '16jmp2');
//define('ILAB_APP_AES_KEY', 'SbYymvfZ8UjEmShxRAB0b1Dtaa0uGjDOOJa/f0Mbuo4=');