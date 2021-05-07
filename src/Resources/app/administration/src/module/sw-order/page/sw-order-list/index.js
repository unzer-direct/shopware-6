import template from './sw-order-list.html.twig';

const { Component } = Shopware;

Component.override('sw-order-list', {
    template,

    inject: ['quickpayApiService', 'acl'],

    data() {
        return {
            showCaptureModal: null,
            showCancelModal: null,
            showRefundModal: null,
        }
    },

    computed: {
        orderCriteria() {
            const criteria = this.$super('orderCriteria');
            criteria.addAssociation('transactions.quickpayPayment');

            return criteria;
        }
    },

    methods: {
        getOrderColumns() {
            const columns = this.$super('getOrderColumns');
            
            columns.push({
                property: 'transactions.last().extensions.quickpayPayment',
                dataIndex: 'transactions.quickpayPayment',
                label: 'sw-order.list.columnQuickpayActions',
                allowResize: true,
                sortable: false
            });
            
            return columns;
        },
        
        quickpayCanCapture(item) {
            if(!item.transactions.last().extensions)
                return
            
            const payment = item.transactions.last().extensions.quickpayPayment;
            if(!payment)
                return;
            
            return payment.status === 5 // Fully Authorized
                || payment.status === 12; // Partially captured
        },
        
        quickpayCanCancel(item) {
            if(!item.transactions.last().extensions)
                return
            
            const payment = item.transactions.last().extensions.quickpayPayment;
            if(!payment)
                return;
            
            return payment.status === 5 // Fully Authorized
                || payment.status === 0; // Created
        },
        
        quickpayCanRefund(item) {
            if(!item.transactions.last().extensions)
                return
            
            const payment = item.transactions.last().extensions.quickpayPayment;
            if(!payment)
                return;
            
            return payment.status === 15 // Partially captured
                || payment.status === 12 // Partially captured
                || payment.status === 32; // Partially refunded
        },
        
        onModalClosed(action) {
            this.showCaptureModal = null;
            this.showCancelModal = null;
            this.showRefundModal = null;
            
            if(action !== 'closed') {
                this.$nextTick(() => {
                    this.getList(); 
                });
            }
        }
    }
});
