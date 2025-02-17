import './page/sw-order-list/'
import './page/sw-order-detail/'
import './view/sw-order-detail-unzerdirect/'
import './component/sw-order-unzerdirect-capture-modal/'
import './component/sw-order-unzerdirect-cancel-modal/'
import './component/sw-order-unzerdirect-refund-modal/'

import enGB from './snippet/en-GB.json';

const { Module, Locale } = Shopware;

Shopware.Module.register('sw-order-detail-unzerdirect-module', {
    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.order.detail') {
            currentRoute.children.push({
                name: 'sw.order.detail.unzerdirect',
                path: 'unzerdirect',
                component: 'sw-order-detail-unzerdirect',
                meta: {
                    parentPath: "sw.order.index",
                    privilege: 'order.viewer'
                }
            });
        }
        next(currentRoute);
    }
});

Locale.extend('en-GB', enGB);