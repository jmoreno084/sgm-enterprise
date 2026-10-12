<?php
declare(strict_types=1);
namespace Sgpm\Infrastructure;

class SqlScript
{
    public static function statements(string $script): array
    {
        $items = array();
        $buffer = '';
        $quote = '';
        $length = strlen($script);
        for ($i = 0; $i < $length; ++$i) {
            $char = $script[$i];
            $next = $i + 1 < $length ? $script[$i + 1] : '';
            if ($quote === '--') {
                $buffer .= $char;
                if ($char === "\n") { $quote = ''; }
                continue;
            }
            if ($quote === '/*') {
                $buffer .= $char;
                if ($char === '*' && $next === '/') { $buffer .= '/'; ++$i; $quote = ''; }
                continue;
            }
            if ($quote !== '') {
                if ($quote[0] === '$' && substr($script, $i, strlen($quote)) === $quote) {
                    $buffer .= $quote; $i += strlen($quote) - 1; $quote = ''; continue;
                }
                $buffer .= $char;
                if ($char === $quote) {
                    if ($next === $quote) { $buffer .= $next; ++$i; }
                    else { $quote = ''; }
                }
                continue;
            }
            if ($char === '-' && $next === '-') { $quote = '--'; $buffer .= '--'; ++$i; continue; }
            if ($char === '/' && $next === '*') { $quote = '/*'; $buffer .= '/*'; ++$i; continue; }
            if ($char === "'" || $char === '"') { $quote = $char; $buffer .= $char; continue; }
            if ($char === '$' && preg_match('/^\$(?:[A-Za-z_][A-Za-z0-9_]*)?\$/', substr($script, $i), $matches)) {
                $quote = $matches[0]; $buffer .= $quote; $i += strlen($quote) - 1; continue;
            }
            if ($char === ';') {
                if (trim($buffer) !== '') { $items[] = trim($buffer); }
                $buffer = '';
            } else { $buffer .= $char; }
        }
        if ($quote !== '' && $quote !== '--') { throw new \RuntimeException('Script SQL incompleto.'); }
        if (trim($buffer) !== '') { $items[] = trim($buffer); }
        return $items;
    }

    public static function run(Database $database, string $script)
    {
        foreach (self::statements($script) as $statement) { $database->execute($statement); }
    }
}

