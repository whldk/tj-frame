<?php
namespace vendor\base;

/**
 * @see BaseModule 用于Module代理
 */
trait ProxyModuleTrait
{
    /**
     * {@inheritDoc}
     * @see \vendor\base\BaseModule::proxyTo()
     */
    public function proxyTo()
    {
        return parent::getModuleName();
    }
    
    /**
     * @param string $name 这个已经是父类的名字
     * @return string
     */
    protected static function getModuleConfig($name)
    {
        $config = parent::getModuleConfig($name);
        $selfConfig = self::getModuleConfig(self::getModuleName());
        
        //合并组件
        if (isset($selfConfig['components'])) {
            $selfConfig['components'] = array_merge($config['components'], $selfConfig['components']);
        }
        
        //覆盖配置
        $config = $selfConfig + $config;
        
        return $config;
    }
}