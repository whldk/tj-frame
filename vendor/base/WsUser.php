<?php
namespace vendor\base;

class WsUser
{
	/**
	 * @var IdentityInterface
	 */
	protected $identity = null;
	protected $role;
	
	public function __construct($config = [])
	{
		if (isset($config['identity']) && $config['identity'] instanceof IdentityInterface) {
			$this->identity = $config['identity'];
		} else {
			$this->identity = null;
		}
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