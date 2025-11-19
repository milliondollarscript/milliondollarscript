import globals from 'globals';
import js from '@eslint/js';

export default [
    js.configs.recommended,
    {
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.jquery,
                wp: 'readonly',
            },
        },
        rules: {
            semi: ['error', 'always'],
            quotes: ['error', 'single'],
            indent: ['error', 4],
            'no-unused-vars': ['warn'],
        },
        ignores: ['node_modules/', 'vendor/', 'dist/', 'build/', '**/*.min.js'],
    },
];
