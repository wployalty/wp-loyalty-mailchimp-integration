const webpack = require("webpack");
const react = new webpack.ProvidePlugin({
    React: "react",
});
const MiniCssExtractPlugin = require("mini-css-extract-plugin");

module.exports = {
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
    plugins: [react, new MiniCssExtractPlugin({
        filename: "../assets/admin/css/dist/style.css"
    })],
};
