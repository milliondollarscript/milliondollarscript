import globals from 'globals';
import js from '@eslint/js';

export default [
    {
        ignores: [
            'node_modules/**',
            'vendor/**',
            'dist/**',
            'build/**',
            '**/*.min.js',
            'src/Assets/js/fire/**',
            'src/Core/js/third-party/**',
        ],
    },
    {
        files: ['eslint.config.js'],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                ...globals.node,
            },
        },
    },
    {
        ...js.configs.recommended,
        files: [
            'src/Assets/js/**/*.js',
            'src/Core/js/**/*.js',
            'src/Core/admin/js/**/*.js',
        ],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'script',
            globals: {
                ...globals.browser,
                ...globals.jquery,
                wp: 'readonly',
                ajaxurl: 'readonly',
                MDS: 'readonly',
                mdsWizard: 'readonly',
                mdsPageManagement: 'readonly',
                mdsPageCreator: 'readonly',
                mdsExtensions: 'readonly',
                mdsAdmin: 'readonly',
                mdsNfs: 'readonly',
                mdsLogActions: 'readonly',
                mdsFireData: 'readonly',
                millionDollarScript: 'readonly',
                main: 'readonly',
            },
        },
        rules: {
            'no-unused-vars': ['warn'],
        },
    },
];
