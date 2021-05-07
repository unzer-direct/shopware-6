import './page/sw-order-list/'
import './page/sw-order-detail/'
import './view/sw-order-detail-quickpay/'
import './component/sw-order-quickpay-capture-modal/'
import './component/sw-order-quickpay-cancel-modal/'
import './component/sw-order-quickpay-refund-modal/'

import enGB from './snippet/en-GB.json';

const { Module, Locale } = Shopware;

Shopware.Module.register('sw-order-detail-quickpay-module', {
    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.order.detail') {
            currentRoute.children.push({
                name: 'sw.order.detail.quickpay',
                path: 'quickpay',
                component: 'sw-order-detail-quickpay',
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