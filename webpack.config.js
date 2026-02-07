const Encore = require('@symfony/webpack-encore');
const BrowserSyncPlugin = require('browser-sync-webpack-plugin');

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
  .setOutputPath('public/build/')
  .setPublicPath('/build')
  .addEntry('app', './assets/app.js')
  .enableReactPreset()
  .enableSingleRuntimeChunk()

  .addPlugin(
    new BrowserSyncPlugin(
      {
        proxy: 'http://127.0.0.1:8000',
        files: [
          'templates/**/*.twig',
          'assets/**/*.{js,css,scss}',
          'public/build/*.js',
        ],
        open: true,
        notify: false,
        reloadDelay: 200,
      },
      { reload: true }
    )
  )
;

module.exports = Encore.getWebpackConfig();
