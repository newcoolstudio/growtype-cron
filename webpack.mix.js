let mix = require('laravel-mix');

mix.setPublicPath('./public');
mix.setResourceRoot('./../');

mix
    .sass('resources/styles/growtype-cron.scss', 'styles');

mix
    .js('resources/scripts/growtype-cron.js', 'scripts');

mix
    .copyDirectory('resources/images', 'public/images');

mix
    .sourceMaps()
    .version();
