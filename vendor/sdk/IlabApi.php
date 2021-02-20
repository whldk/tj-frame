<?php
namespace vendor\sdk;

class IlabApi
{
    protected static $appName = ILAB_APP_NAME;
    protected static $issuerId = ILAB_APP_ISSUER_ID;

    public static function log($ilabUserName, $childProjectTitle, $status, $score, $started_at, $ended_at, $time)
    {
        $data = [
            'username' => (string)$ilabUserName,
            'projectTitle' => self::$appName,
            'childProjectTitle' => (string)$childProjectTitle,	//id-name-alias
            'status' => (int)$status,
            'score' => (int)$score,
            'startDate' => (int)$started_at,
            'endDate' => (int)$ended_at,
            'timeUsed' => (int)$time,
            'issuerId' => (string)self::$issuerId,
        ];

        $params = [
            'xjwt' => IlabJwt::getJwt($data),
        ];

        $result = IlabClient::sendRequest('POST', 'project/log/upload', $params, [], '');

        //log info
        $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $params = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!$result) {
            IlabJwt::log("POST project/log/upload failed. Data(json):{$data}, Params(json):{$params}");
            return false;
        } else {
            IlabJwt::log("POST project/log/upload succeeded. Data(json):{$data}, Params:{$params}, Result:{$result}");
            $result = json_decode($result, true);
            return $result['code'] == 0;
        }
    }

    public static function getUser($username, $password)
    {
        $nonce = self::generateNonce();
        $cnonce = self::generateNonce();
        $passwordHash = strtoupper(self::sha256($nonce . strtoupper(self::sha256($password)) . $cnonce));

        $params = [
            'username' => $username,
            'password' => $passwordHash,
            'nonce' => $nonce,
            'cnonce' => $cnonce,
        ];
        $result = IlabClient::sendRequest('POST', 'sys/api/user/validate', $params, [], '');

        //log info
        $params = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!$result) {
            IlabJwt::log("POST sys/api/user/validate failed. Params(json):{$params}");
            return [];
        } else {
            IlabJwt::log("POST sys/api/user/validate succeeded. Params:{$params}, Result:{$result}");
            $result = json_decode($result, true);
            return $result;
        }
    }

    protected static function sha256($data)
    {
        return hash('sha256', $data);
    }

    protected static function generateNonce()
    {
        $chars =['0','1','2','3','4','5','6','7','8','9','A','B','C','D','E','F'];
        shuffle($chars);
        return implode('', array_slice($chars, 0, 16));
    }
}