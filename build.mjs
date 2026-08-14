/*
 * Build the package's runtime assets into resources/dist/, which is COMMITTED.
 *
 * ⚠️ esbuild rather than Vite, per research R6. Filament's `filament:assets`
 * command copies registered files AS-IS without resolving imports, so anything
 * using `import` must be bundled first — and Filament's own guidance recommends
 * esbuild specifically for Alpine components.
 *
 * ⚠️ The OUTPUT is committed. A host must get a correctly styled, working tree
 * with no bundler, no npm and no theme edit of its own (constitution Principle VI).
 * A stale committed bundle ships broken code that every local check reports as
 * green, so rebuild before completion — never at install time.
 */
import * as esbuild from 'esbuild'

const shared = {
    bundle: true,
    minify: true,
    sourcemap: false,
    logLevel: 'info',
}

await esbuild.build({
    ...shared,
    entryPoints: ['resources/css/tree.css'],
    outfile: 'resources/dist/tree.css',
})

await esbuild.build({
    ...shared,
    entryPoints: ['resources/js/tree.js'],
    outfile: 'resources/dist/tree.js',
    format: 'esm',
    platform: 'browser',
})
