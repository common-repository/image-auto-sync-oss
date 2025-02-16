<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit6617121b726e3b845b8ff5291979d4de
{
    public static $prefixLengthsPsr4 = array (
        'O' => 
        array (
            'OSS\\' => 4,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'OSS\\' => 
        array (
            0 => __DIR__ . '/..' . '/aliyuncs/oss-sdk-php/src/OSS',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit6617121b726e3b845b8ff5291979d4de::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit6617121b726e3b845b8ff5291979d4de::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
