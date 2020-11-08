<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitdd7a5ca43724080a8540d835e1227745
{
    public static $prefixLengthsPsr4 = array (
        'G' => 
        array (
            'Grav\\Plugin\\Seo\\' => 16,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Grav\\Plugin\\Seo\\' => 
        array (
            0 => __DIR__ . '/../..' . '/classes',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Grav\\Plugin\\SeoPlugin' => __DIR__ . '/../..' . '/seo.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitdd7a5ca43724080a8540d835e1227745::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitdd7a5ca43724080a8540d835e1227745::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitdd7a5ca43724080a8540d835e1227745::$classMap;

        }, null, ClassLoader::class);
    }
}
