<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit61b22369cb2951fb075fd4ff2d5838af
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'PhpOffice\\PhpPresentation\\' => 26,
            'PhpOffice\\Common\\' => 17,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'PhpOffice\\PhpPresentation\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpoffice/phppresentation/src/PhpPresentation',
        ),
        'PhpOffice\\Common\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpoffice/common/src/Common',
        ),
    );

    public static $classMap = array (
        'PclZip' => __DIR__ . '/..' . '/pclzip/pclzip/pclzip.lib.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit61b22369cb2951fb075fd4ff2d5838af::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit61b22369cb2951fb075fd4ff2d5838af::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit61b22369cb2951fb075fd4ff2d5838af::$classMap;

        }, null, ClassLoader::class);
    }
}