<?php
namespace vendor\base;

class ApiResponse extends response
{
    use AppTrait;

    private $_cookies;

    protected function prepare()
    {
        if ($this->stream !== null) {
            return;
        }

        if ($this->format == static::FORMAT_JSON) {
            $this->setHeader('Content-Type', 'application/json; charset=UTF-8');
            $this->prepareData();
            if ($this->data !== null) {
                $options = $this->encodeOptions;
                if ($this->prettyPrint) {
                    $options |= JSON_PRETTY_PRINT;
                }
                $this->content = json_encode($this->data, $options);
            }
        } elseif ($this->format === self::FORMAT_RAW || $this->format == self::FORMAT_HTML) {
            if ($this->data !== null) {
                $this->content = strval($this->data);
            }
        } else {
            throw new \Exception("Unsupported response format: {$this->format}");
        }

        if (is_array($this->content)) {
            throw new \Exception('Response content must not be an array.');
        } elseif (is_object($this->content)) {
            if (method_exists($this->content, '__toString')) {
                $this->content = $this->content->__toString();
            } else {
                throw new \Exception('Response content must be a string or an object implementing __toString().');
            }
        }
    }

    protected function prepareData()
    {
        $request = $this->request;
        if ($request->usePostCookies) {
            $this->data = ['data' => $this->data, 'cookies' => []];
            //cookie of current session
            $session = $this->session;
            if ($sessionId = $session->getId()) {
                $sessionName = $session->getName();
                $this->data['cookies'][$sessionName] = ['name' => $sessionName, 'value' => $sessionId];
            }
            if (!$this->_cookies instanceof Cookies) {
                return;
            }
            $this->data['cookies'] += $this->_cookies->getAll();
            foreach ($this->data['cookies'] as &$cookie) {
                if (isset($cookie['expire']) && $cookie['expire'] != 1  && $request->enableCookieValidation && in_array($cookie['name'], $request->validateCookies)) {
                    $cookie['value'] = $this->security->hashData(serialize([$cookie['name'], $cookie['value']]), $request->cookieValidationKey);
                }
            }
        }
    }

    protected function sendCookies()
    {
        if (!$this->_cookies instanceof Cookies) {
            return;
        }

        $request = $this->request;
        $this->_cookies->send($request->enableCookieValidation, $request->cookieValidationKey, $request->validateCookies);
    }


}