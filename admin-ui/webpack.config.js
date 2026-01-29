const webpack = require("webpack");
const react = new webpack.ProvidePlugin({
    React: "react",
});
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
                use: ["style-loader", "css-loader", "postcss-loader"],
            },
        ],
    },
    plugins: [react],
};
