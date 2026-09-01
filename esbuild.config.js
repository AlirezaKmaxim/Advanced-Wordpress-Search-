/**
 * esbuild Configuration Script for Alireza Smart Search
 *
 * Provides build and watch pipelines using the esbuild JavaScript API.
 */

const esbuild = require('esbuild');

const isWatch = process.argv.includes('--watch');

/**
 * Common configuration options for esbuild.
 */
const commonConfig = {
    entryPoints: ['./assets/js/src/index.js'],
    bundle: true,
    target: ['es2020'],
    format: 'iife',
    banner: {
        js: '/*! Alireza Smart Search | (c) Alireza KMaxim | GPL-2.0 License */',
    },
    sourcemap: true,
};

async function build() {
    try {
        if (isWatch) {
            console.log('⚡ [esbuild] Starting watch mode for JavaScript...');
            
            const ctx = await esbuild.context({
                ...commonConfig,
                outfile: './assets/js/alireza-search.min.js',
                minify: true,
            });

            await ctx.watch();
            console.log('👀 [esbuild] Watching for JS changes in ./assets/js/src/ ...');
            return;
        }

        console.log('🚀 [esbuild] Building JavaScript bundles...');

        // 1. Minified Production Bundle
        const prodResult = await esbuild.build({
            ...commonConfig,
            outfile: './assets/js/alireza-search.min.js',
            minify: true,
            metafile: true,
        });

        // 2. Unminified Development Bundle
        await esbuild.build({
            ...commonConfig,
            outfile: './assets/js/alireza-search.js',
            minify: false,
        });

        // Output bundle size and analysis summary
        const text = await esbuild.analyzeMetafile(prodResult.metafile);
        console.log(text);
        console.log('✅ [esbuild] JavaScript bundles created successfully!');
    } catch (error) {
        console.error('❌ [esbuild] Build failed:', error);
        process.exit(1);
    }
}

build();
