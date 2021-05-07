import template from './sw-order-quickpay-capture-modal.html.twig';
import './sw-order-quickpay-capture-modal.scss';

const { Component, Mixin} = Shopware;

Component.register('sw-order-quickpay-capture-modal', {
    template,

    inject: [
        'quickpayApiService',
    ],
    
    mixins: [
        Mixin.getByName('notification')
    ],
    
    props: {
        payment: {
            type: Object,
            required: true
        },
        currency: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            isLoading: false,
            amount: 0.0
        };
    },

    computed: {
        maxAmount() {
            return (this.payment.amountAuthorized - this.payment.amountCaptured) / 100.0;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.amount = this.maxAmount
        },
        
        async onConfirm() {
            try {
                this.isLoading = true;
                const response = await this.quickpayApiService.capture(this.payment.id, parseInt(this.amount * 100));
                this.$emit('success');
                this.createNotificationSuccess({
                    message: this.$tc('sw-order.quickpay.captureRequestedNotification')
                })
                this.isLoading = false;
            } catch(e) {
                this.$emit('fail');
                this.createNotificationError({
                    message: this.$tc('sw-order.quickpay.captureRequestFailedNotification')
                })
                this.isLoading = false;
            }
        }
    }

});
