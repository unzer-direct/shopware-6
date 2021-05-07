import template from './sw-order-detail.html.twig';

const { Component} = Shopware;

Component.override('sw-order-detail', {
    template,

    computed: {
        
        showTabs() {
            return true;
        },
        
        quickpayActive() {
            return this.$route.name === 'sw.order.detail.quickpay'
        }
    },
});