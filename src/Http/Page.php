<?php
declare(strict_types=1);
namespace Sgpm\Http;

class Page
{
    public static function render(string $template,array $values=array()): Response
    {
        // Template paths are source-code constants, never request values.
        $page=$values; ob_start(); require dirname(__DIR__,2).'/views/'.$template.'.php';
        return new Response(ob_get_clean(),200,array('Content-Type'=>'text/html; charset=utf-8','Cache-Control'=>'no-store'));
    }
}
