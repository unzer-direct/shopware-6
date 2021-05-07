import template from './sw-order-detail-quickpay.html.twig';

const { Component, } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-detail-quickpay', {
    template,

    inject: [
        'repositoryFactory',
        'quickpayApiService',
        'acl'
    ],

    props: {
        orderId: {
            type: String,
            required: true
        },

        isLoading: {
            type: Boolean,
            required: true
        },

        isEditing: {
            type: Boolean,
            required: true
        },

        isSaveSuccessful: {
            type: Boolean,
            required: true
        }
    },

    data() {
        return {
            order: null,
            showCaptureModal: false,
            showCancelModal: false,
            showRefundModal: false
        };
    },

    computed: {
        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        transaction() {
            for (let i = 0; i < this.order.transactions.length; i += 1) {
                if (this.order.transactions[i].stateMachineState.technicalName !== 'cancelled') {
                    return this.order.transactions[i];
                }
            }
            return this.order.transactions.last();
        },
        
        quickpayPayment() {
            return this.transaction.extensions.quickpayPayment;
        },
        
        operations() {
            return this.quickpayPayment.operations;
        },
        
        operationColumns() {
            return [
                {
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    label: 'sw-order.quickpay.operationCreatedAt',
                    allowResize: false,
                    sortable: false
                },
                {
                    property: 'type',
                    dataIndex: 'type',
                    label: 'sw-order.quickpay.operationType',
                    allowResize: false,
                    sortable: false
                },
                {
                    property: 'amount',
                    dataIndex: 'amount',
                    label: 'sw-order.quickpay.operationAmount',
                    allowResize: false,
                    sortable: false
                },
                {
                    property: 'status',
                    dataIndex: 'status',
                    label: 'sw-order.quickpay.operationStatus',
                    allowResize: false,
                    sortable: false
                }
            ]
        },
        
        orderCriteria() {
            const criteria = new Criteria(1, 1);

            criteria.addAssociation('transactions.quickpayPayment.operations')
            criteria.addAssociation('currency')
            criteria.getAssociation('transactions').addSorting(Criteria.sort('createdAt'));
            criteria.getAssociation('transactions.quickpayPayment.operations')
                .addSorting(Criteria.sort('createdAt'), Criteria.sort('quickpayOperationId'));

            return criteria;
        },
        
        currency() {
            return this.order.currency;
        },
        
        paymentStatus() {
            return this.$tc('sw-order.quickpay.status.' + this.quickpayPayment.status);
        },
        
        amountTotal() {
            return this.quickpayPayment.amount / 100.0
        },
        
        amountAuthorized() {
            return this.quickpayPayment.amountAuthorized / 100.0
        },
        
        amountCaptured() {
            return this.quickpayPayment.amountCaptured / 100.0
        },
        
        amountRefunded() {
            return this.quickpayPayment.amountRefunded / 100.0
        },
        
        canCapture() {
            return this.quickpayPayment.status === 5 // Fully Authorized
                || this.quickpayPayment.status === 12; // Partially captured
        },
        
        canCancel() {
            return this.quickpayPayment.status === 5 // Fully Authorized
                || this.quickpayPayment.status === 0; // Created 
        },
        
        canRefund() {
            return this.quickpayPayment.status === 15 // Partially captured
                || this.quickpayPayment.status === 12 // Partially captured
                || this.quickpayPayment.status === 32; // Partially refunded
        }
        
    },

    watch: {
        orderId() {
            this.createdComponent();
        },
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        getTypeName(item) {
            const failed = item.status && item.status !== '20000';
            
            if(failed)
                return this.$tc('sw-order.quickpay.failedTypes.' + item.type);
            
            return this.$tc('sw-order.quickpay.types.' + item.type);
        },
        
        getAmount(item) {
            if(item.amount === 0)
                return '-';
            
            return (item.amount / 100.0) + ' ' + this.currency.symbol;
        },
        
        getStatus(item) {
            if(!item.status)
                return '-';
            
            return this.$tc('sw-order.quickpay.statusCodes.' + item.status);
        },
        
        async refresh() {
            this.$emit('loading-change', true);
            try {
                await this.quickpayApiService.refresh(this.quickpayPayment.id);
                this.reloadEntityData();
            } catch(e) {
                this.$emit('loading-change', false);
            }
        },
        
        createdComponent() {
            this.reloadEntityData();
            this.$root.$on('language-change', this.reloadEntityData);
        },

        destroyedComponent() {
            this.$root.$off('language-change', this.reloadEntityData);
        },

        onModalClosed(action) {
            this.showCaptureModal = false;
            this.showCancelModal = false;
            this.showRefundModal = false;
            
            if(action !== 'closed') {
                this.$nextTick(() => {
                    this.reloadEntityData(); 
                });
            }
        },

        reloadEntityData() {
            this.$emit('loading-change', true);

            return this.orderRepository.get(this.orderId, Shopware.Context.api, this.orderCriteria).then((response) => {
                this.order = response;
                this.$emit('loading-change', false);
                return Promise.resolve();
            }).catch(() => {
                this.$emit('loading-change', false);
                return Promise.reject();
            });
        },
    }
});
