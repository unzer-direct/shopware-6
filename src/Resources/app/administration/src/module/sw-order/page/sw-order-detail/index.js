import template from './sw-order-detail.html.twig';

const { Component} = Shopware;

Component.override('sw-order-detail', {
    template,

    computed: {
        
        showTabs() {
            return true;
        },
        
        unzerdirectActive() {
            return this.$route.name === 'sw.order.detail.unzerdirect'
        }
    },
});