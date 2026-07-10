/*
 * Eslint config file（参照 tdesign-miniprogram-starter 模板）
 * Documentation: https://eslint.org/docs/user-guide/configuring/
 */
module.exports = {
  env: {
    es6: true,
    browser: true,
    node: true,
  },
  parserOptions: {
    ecmaVersion: 2018,
    // 本项目页面 JS 走 CommonJS（require/module.exports）
    sourceType: 'script',
  },
  globals: {
    wx: true,
    App: true,
    Page: true,
    getCurrentPages: true,
    getApp: true,
    Component: true,
    Behavior: true,
    requirePlugin: true,
    requireMiniProgram: true,
  },
  extends: ['eslint-config-airbnb-base', 'eslint-config-prettier'],
  plugins: ['prettier', 'import'],
  rules: {
    // 允许调用首字母大写的函数时没有 new 操作符
    'new-cap': 'off',
    'no-underscore-dangle': 'off',
    'no-param-reassign': 'off',
    eqeqeq: ['error', 'always', { null: 'ignore' }],
    'import/no-unresolved': 0,
    'import/prefer-default-export': 0,
    'import/extensions': 0,
    'import/no-dynamic-require': 0,
    'object-shorthand': 0,
    'no-shadow': 0,
    'consistent-return': 0,
    'func-names': 0,
    'class-methods-use-this': 0,
    'no-console': [2, { allow: ['warn', 'error'] }],
  },
};
