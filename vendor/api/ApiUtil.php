<?php
namespace vendor\api;

class ApiUtil
{
	const ERR_NONE = 0;
	const ERR_EXPIRED = 1;
	const ERR_BODY = 2;
	const ERR_SIGN = 3;
	
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
    public static function canonicalizedXHeader($headers, $headerPrefix = 'x-mg-')
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
    
    public static function authorize(
    		$signature, 
    		$secret, 
    		$method, 
    		$path, 
    		$params, 
    		$headers, 
    		$body)
    {
    	if (!isset($headers['Date']) || self::isExpired($headers['Date'])) {
    		return self::ERR_EXPIRED;
    	}
    	if ($body && self::md5($body) !== @$headers['Content-Md5']) {
    		return self::ERR_BODY;
    	}
    	if (false === self::verifySignature($signature, $secret, $method, $path, $params, $headers)) {
    		return self::ERR_SIGN;
    	}
    	
    	return self::ERR_NONE;
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
        
		$content = $method . "\n";
		isset($headers['Content-Md5']) === false ?: $content .= $headers['Content-Md5'] . "\n";
		isset($headers['Content-Type']) === false ?: $content .= $headers['Content-Type'] . "\n";
        $content .= $headers['Date'] . "\n";
        $content .= self::canonicalizedXHeader($headers, 'x-mg-') . "\n";
        $content .= self::canonicalizedPath($path, $params);
        return self::hmacSHA256($content, $secret);
    }
    
    /**
     * @return boolean
     */
    public static function verifySignature($signature, $secret, $method, $path, $params, $headers)
    {
        $calSignature = self::getAuthorizationSignature($secret, $method, $path, $params, $headers);
        return $signature === $calSignature;
    }
    
    public static function isExpired($gmt, $maxDiffInSeconds = 3000)
    {
    	$gmtTime = strtotime($gmt);
    	if ($gmtTime === false) {
    		return false;
    	}
    	
    	$diff = abs(time() - $gmtTime);
    	
    	return $diff > $maxDiffInSeconds;
    }
}

