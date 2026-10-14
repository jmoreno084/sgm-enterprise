<?php
declare(strict_types=1);
namespace Sgpm\Infrastructure;

class Vendor
{
    public static function shapefile()
    {
        if (!class_exists('Shapefile\ShapefileAutoloader')) {
            require dirname(__DIR__,2).'/vendor/php-shapefile/src/Shapefile/ShapefileAutoloader.php';
            \Shapefile\ShapefileAutoloader::register();
        }
    }
    public static function pdf()
    {
        if (!class_exists('TTFontFile')) { require dirname(__DIR__,2).'/vendor/tfpdf/font/unifont/ttfonts.php'; }
        if (!class_exists('tFPDF')) { require dirname(__DIR__,2).'/vendor/tfpdf/tfpdf.php'; }
    }
}
