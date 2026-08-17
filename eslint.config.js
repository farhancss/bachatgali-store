import js from '@eslint/js';
import vue from 'eslint-plugin-vue';
import ts from '@vue/eslint-config-typescript';

export default [
    js.configs.recommended,
    ...vue.configs['flat/recommended'],
    ...ts(),
    {
        files: ['resources/js/**/*.{ts,vue}'],
        rules: {
            'vue/multi-word-component-names': 'off',
            'vue/component-api-style': ['error', ['script-setup']],
            'vue/component-name-in-template-casing': ['error', 'PascalCase'],
            '@typescript-eslint/no-explicit-any': 'error',
            '@typescript-eslint/consistent-type-imports': 'error',
            'no-console': ['warn', { allow: ['warn', 'error'] }],
            eqeqeq: ['error', 'always'],
        },
    },
    {
        ignores: ['public/**', 'vendor/**', 'node_modules/**', 'storage/**'],
    },
];
