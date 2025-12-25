import './page/wsc-variant-updater-index';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('wsc-variant-updater', {
    type: 'plugin',
    name: 'wsc-variant-updater',
    title: 'wsc-variant-updater.general.mainMenuItemGeneral',
    description: 'wsc-variant-updater.general.descriptionTextModule',
    color: '#ff3d58',
    icon: 'regular-cog',

    routes: {
        index: {
            component: 'wsc-variant-updater-index',
            path: 'index'
        }
    },

    settingsItem: [{
        group: 'plugins',
        to: 'wsc.variant.updater.index',
        icon: 'regular-cog',
        name: 'wsc-variant-updater',
        label: 'wsc-variant-updater.general.mainMenuItemGeneral'
    }],

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    }
});
