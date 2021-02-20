<?php
namespace vendor\sdk;
class SdkUtil
{
    /**
     * uppercase md5
     * @return string 
     */
    public static function md5($str)
    {
        return strtoupper(md5($str));
    }
    
    /**
     * hmac sha256 with secret key
     * @return string
     */
    public static function hmacSHA256($str, $secret)
    {
        $signature = hash_hmac("sha256", $str, $secret, false);
        return $signature;
    }
    

    /**
     * urlEncode
     * @return string
     */
    public static function urlencode($str)
    {
        return urlencode($str);
    }
    
    /**
     * @param array assc array of query params like k=v
     * @return string
     */
    public static function buildQuery($params, $prefix = '?')
    {
        ksort($params, SORT_REGULAR);	//compare items normally (don't change types)
        
        $query = '';
        foreach ($params as $k => $v) {
            $v = self::urlencode($v);
            $query .= "&$k=$v";
        }
        $query[0] = $prefix;
        
        return $query;
    }
    
    /**
     * Get canonicalizedHeader string
     * @return string
     */
    public static function canonicalizedXHeader($headers, $headerPrefix)
    {
    	$headerPrefix = strtolower($headerPrefix);
    	
        ksort($headers, SORT_REGULAR);
        
        $xheaderStr = '';
        
        $first = true;
        foreach ($headers as $k => $v) {
			$k = strtolower($k);
        	if (strpos($k, $headerPrefix) === 0) { // x-mg- header
        		if ($first === true) {
        			$xheaderStr .= $k . ':' . $v;
        			$first = false;
        		} else {
        			$xheaderStr .= "\n" . $k . ':' . $v;
        		}
        	}
        }
        
        return $xheaderStr;
    }
    
    /**
     * Get canonicalizedPath string
     * @return string
     */
    public static function canonicalizedPath($path, $params = [])
    {
        $params = (array)$params;

        if ($params !== []) {
            ksort($params, SORT_REGULAR);
            
            $query = '';
            foreach ($params as $k => $v) {
                  $query .= "&$k=$v";	//no urlencode
            }
            $query[0] = '?';
            
            return $path . $query;
        }
        
        return $path;
    }
    
    /**
     * Get request authorization string as defined.
     *
     * @return string
     */
    public static function getAuthorizationSignature($secret, $method, $path, $params, $headers)
    {
        if (!$secret) {
            return '';
        }
        $method = strtoupper($method);

		$content = $method . "\n";
		isset($headers['Content-Md5']) === false ?: $content .= $headers['Content-Md5'] . "\n";
		isset($headers['Content-Type']) === false ?: $content .= $headers['Content-Type'] . "\n";
        $content .= $headers['Date'] . "\n";
        $content .= self::canonicalizedXHeader($headers, 'x-mg-') . "\n";
        $content .= self::canonicalizedPath($path, $params);
        $sign = self::hmacSHA256($content, $secret);
        return $sign;
    }

    /**
     * Get the local machine ip address.
     * @return string
     */
    public static function getLocalIp()
    {
    	try { // if exec can be used
    		$out = $stats = null;
    		
    		$preg = "/\A((([0-9]?[0-9])|(1[0-9]{2})|(2[0-4][0-9])|(25[0-5]))\.){3}(([0-9]?[0-9])|(1[0-9]{2})|(2[0-4][0-9])|(25[0-5]))\Z/";
    
    		if (PATH_SEPARATOR === ':') { // linux
    			exec("ifconfig", $out, $stats);
    			if (!empty($out)) {
    				if (isset($out[1]) && strstr($out[1], 'addr:')) {
    					$tmpArray = explode(":", $out[1]);
    					$tmpIp = explode(" ", $tmpArray[1]);
    					if (preg_match($preg, trim($tmpIp[0])))
    						return trim($tmpIp[0]);
    				}
    			}
    		} else { // windows PATH_SEPARATOR==';'
    			exec("ipconfig", $out, $stats);
    			if (!empty($out)) {
    				foreach ($out AS $row) {
    					if (strstr($row, "IP") && strstr($row, ":") && !strstr($row, "IPv6")) {
    						$tmpIp = explode(":", $row);
    						if (preg_match($preg, trim($tmpIp[1])))
    							return trim($tmpIp[1]);
    					}
    				}
    			}
    		}
    	} catch (\Exception $e){
    		 
    	}
    
    	if (isset($_ENV["HOSTNAME"]))
    		$MachineName = $_ENV["HOSTNAME"];
    	elseif (isset($_ENV["COMPUTERNAME"]))
    		$MachineName = $_ENV["COMPUTERNAME"];
    	else
    		$MachineName = "";
    	if ($MachineName !== "")
    		return $MachineName;
    
    	return '127.0.0.1';
    }
    
    /**
     * If $ipStr is raw IP address, return true.
     * @return bool
     */
    public static function isIp($ipStr)
    {
    	$ipArr = explode(".", $ipStr);
    	$count = count($ipArr);
    	for ($i = 0; $i < $count; $i++)
    		if ($ipArr[$i] > 255)
    			return false;
    	
    	return !!preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/", $ipStr);
    }
}

