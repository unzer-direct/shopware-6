import template from './sw-order-detail-unzerdirect.html.twig';

const { Component, } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-order-detail-unzerdirect', {
    template,

    inject: [
        'repositoryFactory',
        'unzerdirectApiService',
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
        
        unzerdirectPayment() {
            return this.transaction.extensions.unzerdirectPayment;
        },
        
        operations() {
            return this.unzerdirectPayment.operations;
        },
        
        operationColumns() {
            return [
                {
                    property: 'createdAt',
                    dataIndex: 'createdAt',
                    label: 'sw-order.unzerdirect.operationCreatedAt',
                    allowResize: false,
                    sortable: false
                },
                {
                    property: 'type',
                    dataIndex: 'type',
                    label: 'sw-order.unzerdirect.operationType',
                    allowResize: false,
                    sortable: false
                },
                {
                    property: 'amount',
                    dataIndex: 'amount',
                    label: 'sw-order.unzerdirect.operationAmount',
                    allowResize: false,
                    sortable: false
                },
                {
                    property: 'status',
                    dataIndex: 'status',
                    label: 'sw-order.unzerdirect.operationStatus',
                    allowResize: false,
                    sortable: false
                }
            ]
        },
        
        orderCriteria() {
            const criteria = new Criteria(1, 1);

            criteria.addAssociation('transactions.unzerdirectPayment.operations')
            criteria.addAssociation('currency')
            criteria.getAssociation('transactions').addSorting(Criteria.sort('createdAt'));
            criteria.getAssociation('transactions.unzerdirectPayment.operations')
                .addSorting(Criteria.sort('createdAt'), Criteria.sort('unzerdirectOperationId'));

            return criteria;
        },
        
        currency() {
            return this.order.currency;
        },
        
        paymentStatus() {
            return this.$tc('sw-order.unzerdirect.status.' + this.unzerdirectPayment.status);
        },
        
        amountTotal() {
            return this.unzerdirectPayment.amount / 100.0
        },
        
        amountAuthorized() {
            return this.unzerdirectPayment.amountAuthorized / 100.0
        },
        
        amountCaptured() {
            return this.unzerdirectPayment.amountCaptured / 100.0
        },
        
        amountRefunded() {
            return this.unzerdirectPayment.amountRefunded / 100.0
        },
        
        canCapture() {
            return this.unzerdirectPayment.status === 5 // Fully Authorized
                || this.unzerdirectPayment.status === 12; // Partially captured
        },
        
        canCancel() {
            return this.unzerdirectPayment.status === 5 // Fully Authorized
                || this.unzerdirectPayment.status === 0; // Created 
        },
        
        canRefund() {
            return this.unzerdirectPayment.status === 15 // Partially captured
                || this.unzerdirectPayment.status === 12 // Partially captured
                || this.unzerdirectPayment.status === 32; // Partially refunded
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
                return this.$tc('sw-order.unzerdirect.failedTypes.' + item.type);
            
            return this.$tc('sw-order.unzerdirect.types.' + item.type);
        },
        
        getAmount(item) {
            if(item.amount === 0)
                return '-';
            
            return (item.amount / 100.0) + ' ' + this.currency.symbol;
        },
        
        getStatus(item) {
            if(!item.status)
                return '-';
            
            return this.$tc('sw-order.unzerdirect.statusCodes.' + item.status);
        },
        
        async refresh() {
            this.$emit('loading-change', true);
            try {
                await this.unzerdirectApiService.refresh(this.unzerdirectPayment.id);
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
