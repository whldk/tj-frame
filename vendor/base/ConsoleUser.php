<?php
namespace vendor\base;

class ConsoleUser
{
	/**
	 * @var IdentityInterface
	 */
	protected $identity = null;
	protected $role;
	
	public function __construct($config = [])
	{
	}
	
	public function getRole()
	{
		if (!$this->role) {
			$this->role = $this->identity ? $this->identity->getRole() : '?';
		}
		return $this->role;
	}
	
	public function getIdentity()
	{
		return $this->identity;
	}
		
}