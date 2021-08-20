const path = require("path");
const glob = require("glob");
const defaultConfig = require("./node_modules/@wordpress/scripts/config/webpack.config");

module.exports = {
  ...defaultConfig,
  entry: glob.sync("./js/blocks/**/index.js"),
  output: {
    filename: "blocks.js",
    path: path.resolve(__dirname, "js")
  },
  module: {
    ...defaultConfig.module,
    rules: [
      ...defaultConfig.module.rules,
      {
        test: /\.svg$/,
        use: ["@svgr/webpack", "url-loader"]
      },
      {
        test: /\.scss$/,
        use: [
          {
            loader: "style-loader"
          },
          {
            loader: "postcss-loader",
            options: {
              plugins: [require("autoprefixer")()]
            }
          },
          {
            loader: "sass-loader"
          }
        ]
      }
    ]
  }
};
