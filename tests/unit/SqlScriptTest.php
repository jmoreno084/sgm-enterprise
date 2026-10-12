<?php
declare(strict_types=1);
return function ($assert) {
    $sql = "-- comment;\nSELECT 'a;b'; DO $$ BEGIN PERFORM 1; END; $$; SELECT \"a;b\";";
    $items = Sgpm\Infrastructure\SqlScript::statements($sql);
    $assert(count($items) === 3, 'División respeta funciones y literales');
    $sql = "SELECT 'it''s;safe'; /* comment; */ SELECT 2;";
    $assert(count(Sgpm\Infrastructure\SqlScript::statements($sql)) === 2, 'Comillas escapadas y comentarios');
    foreach (glob(dirname(__DIR__,2) . '/migrations/*.sql') as $file) {
        $assert(count(Sgpm\Infrastructure\SqlScript::statements(file_get_contents($file))) > 0, 'Migración legible');
    }
};

