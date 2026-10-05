const TerserPlugin = require("terser-webpack-plugin");
const path = require('path');

module.exports = (env, argv) => {

    const isProd = argv.mode !== 'development';

    const config = {
        devtool: isProd ? false : "source-map",
        module: {
            rules: []
        }
    };

    const assetsFrontPlugins = [];
    const assetsAdminPlugins = [];

    let assetsFrontEntry = "./assets/js/src/front.js";
    let assetsAdminEntry = "./assets/js/src/admin.js";

    if (isProd) {
        // Uglify JS
        config.optimization = {
            minimizer: [new TerserPlugin()],
        };
        // Babel
        config.module.rules.push(
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: "babel-loader",
                    options: {
                        presets: ['@babel/preset-env']
                    }
                }
            }
        );
    }
    // Assets for frontend.
    const assetsFront = Object.assign({}, config, {
        plugins: assetsFrontPlugins,
        entry: assetsFrontEntry,
        output: {
            path: path.resolve(__dirname, './assets/js'),
            filename: "front.js"
        },
    });
    // Assets for admin area.
    const assetsAdmin = Object.assign({}, config, {
        plugins: assetsAdminPlugins,
        entry: assetsAdminEntry,
        output: {
            path: path.resolve(__dirname, './assets/js'),
            filename: "admin.js",
        },
    });

    return [assetsFront, assetsAdmin]
};
