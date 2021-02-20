<?php

namespace vendor\base;

class ApiRequest extends Request
{
    public $usePostCookies = false;

    public function __construct($config = [])
    {
        parent::__construct($config);

        //使用参数类cookie
        if (isset($config['usePostCookies'])) {
            $this->usePostCookies = $config['usePostCookies'] ? true : false;
        }
        if ($this->usePostCookies) {
            //处理body参数，并且重置cookie
            $this->getBodyParams();
        }
    }

    public function getBodyParams()
    {
        //var_dump($_POST);
        if ($this->_bodyParams === null) {
            $rawContentType = $this->getContentType();
            if (($pos = strpos($rawContentType, ';')) !== false) {
                // e.g. application/json; charset=UTF-8
                $contentType = substr($rawContentType, 0, $pos);
            } else {
                $contentType = $rawContentType;
            }

            if ($contentType == 'application/json') {
                $decode = json_decode($this->getRawBody(), true);
                $this->_bodyParams = $decode ? $decode : [];
                if ($this->usePostCookies) {
                    //处理body里面的cookies
                    if (isset($this->_bodyParams['cookies'])) {
                        $_COOKIE = (array)$this->_bodyParams['cookies'];
                    }
                    $this->_bodyParams = isset($this->_bodyParams['data']) ? $this->_bodyParams['data'] : [];
                }
            } elseif ($this->getMethod() === 'POST') {
                if (isset($_POST['cookies'])) {
                    $_COOKIE = json_decode($_POST['cookies'], true) ?: [];
                    unset($_POST['cookies']);
                }
                // PHP has already parsed the body so we have all params in $_POST
                $this->_bodyParams = $_POST;
            } else {
                $this->_bodyParams = [];
                mb_parse_str($this->getRawBody(), $this->_bodyParams);
            }
        }

        return $this->_bodyParams;
    }


}