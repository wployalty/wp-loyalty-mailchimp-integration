const webpack = require("webpack");
const react = new webpack.ProvidePlugin({
    React: "react",
});
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const CssMinimizerPlugin = require("css-minimizer-webpack-plugin");

module.exports = (env, argv) => {
    const isProd = argv && argv.mode === "production";
    const cssFilename = isProd
        ? "../assets/admin/css/dist/style.min.css"
        : "../assets/admin/css/dist/style.css";

    return {
    entry: "./src/index.js",
    output: {
        path: __dirname,
        filename: "../assets/admin/js/dist/[name].bundle.js",
    },
    module: {
        rules: [
            {
                test: /.js$/,
                exclude: /node_modules/,
                loader: "babel-loader",
                options: {
                    presets: ["@babel/preset-env"],
                    plugins: [
                        "@babel/plugin-transform-runtime",
                        "transform-class-properties",
                    ],
                },
            },
            {
                test: /\.css$/,
                use: [MiniCssExtractPlugin.loader, "css-loader", "postcss-loader"],
            },
        ],
    },
    optimization: {
        minimizer: [
            "...",
            new CssMinimizerPlugin(),
        ],
    },
    plugins: [react, new MiniCssExtractPlugin({
        filename: cssFilename,
    })],
};
};
