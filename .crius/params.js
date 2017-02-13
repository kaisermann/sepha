const minimist = require('minimist')
const args = minimist(process.argv.slice(2))

const isProduction = args.p || args.production
const isDebugging = args.d || args.debug

module.exports = {
  // Do not minify assets when '-d'
  debug: isDebugging,
  // Create sourcemaps when not in production mode
  maps: !isProduction,
  // Production mode, appends hash of file's content to its name
  production: isProduction,
  // Start BroswerSync when '--sync'
  sync: args.sync,
}
