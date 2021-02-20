<?php
namespace vendor\base;

Interface IdentityInterface 
{
	static function findById($id);
	
	function getId();
	
	function getRole();
	
	function getAuthkey();
	
	function validateAuthKey($authKey);
	
	function setExtraCookies();
	
	function delExtraCookies();
	
	function renewExtraCookies();
}