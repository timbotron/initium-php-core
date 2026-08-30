<?php

namespace Initium;

use League\Plates\Engine;

/**
 * Builds the Plates engine shared by core auth views and app pages.
 *
 * Template resolution is app-first, core-fallback: templates are referenced with
 * the "app::" folder prefix, which looks in the app's override directory first
 * and falls back to core's bundled defaults. An app overrides any core view
 * (login, the basic layout, ...) just by dropping a same-named file in its own
 * templates directory — no vendor edits.
 *
 * The skeleton points at its templates directory once at boot, before routing:
 *
 *     \Initium\View::override(__DIR__ . '/../templates');
 */
class View {
    private static ?string $overrideDir = null;

    /** Register the app's templates directory as the override source. */
    public static function override(string $dir): void {
        self::$overrideDir = $dir;
    }

    /** A Plates engine with the "app::" folder resolving override-first, core-fallback. */
    public static function engine(): Engine {
        $coreDir = __DIR__ . '/../templates';
        $engine = new Engine($coreDir); // default dir = core = the fallback target

        $appDir = (self::$overrideDir !== null && is_dir(self::$overrideDir))
            ? self::$overrideDir
            : $coreDir;
        $engine->addFolder('app', $appDir, true); // fallback -> core default dir

        return $engine;
    }
}
