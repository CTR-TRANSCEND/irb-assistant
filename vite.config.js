import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Outstanding #54 — sub-path deployment fix.
//
// HTML asset URLs emitted by Laravel's @vite() helper are prefixed with
// APP_URL → on ignet they correctly become /irb-assistant/build/...
//
// BUT url() refs INSIDE the compiled CSS bundle (Inter font @font-face
// declarations from @fontsource/inter) are stamped at build time using
// Vite's `base` setting. Without an explicit base they bake in as
// /build/inter-... and resolve to https://ignet.org/build/... in
// production → 404.
//
// The deploy invocation reads VITE_APP_BASE from the environment so we
// don't need different config files per deploy target:
//   production: VITE_APP_BASE=/irb-assistant/build/ npm run build
//   dev:        npm run dev (default /build/ matches local Laravel server)

const buildBase = process.env.VITE_APP_BASE || '/build/';

export default defineConfig({
    base: buildBase,
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
