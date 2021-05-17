import template from './sw-order-unzerdirect-capture-modal.html.twig';
import './sw-order-unzerdirect-capture-modal.scss';

const { Component, Mixin} = Shopware;

Component.register('sw-order-unzerdirect-capture-modal', {
    template,

    inject: [
        'unzerdirectApiService',
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
                const response = await this.unzerdirectApiService.capture(this.payment.id, parseInt(this.amount * 100));
                this.$emit('success');
                this.createNotificationSuccess({
                    message: this.$tc('sw-order.unzerdirect.captureRequestedNotification')
                })
                this.isLoading = false;
            } catch(e) {
                this.$emit('fail');
                this.createNotificationError({
                    message: this.$tc('sw-order.unzerdirect.captureRequestFailedNotification')
                })
                this.isLoading = false;
            }
        }
    }

});
