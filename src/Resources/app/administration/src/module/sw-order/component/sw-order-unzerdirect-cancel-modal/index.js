import template from './sw-order-unzerdirect-cancel-modal.html.twig';
import './sw-order-unzerdirect-cancel-modal.scss';

const { Component, Mixin} = Shopware;

Component.register('sw-order-unzerdirect-cancel-modal', {
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
    },

    data() {
        return {
            isLoading: false,
        };
    },

    computed: {
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
        },
        
        async onConfirm() {
            try {
                this.isLoading = true;
                const response = await this.unzerdirectApiService.cancel(this.payment.id);
                this.$emit('success');
                this.createNotificationSuccess({
                    message: this.$tc('sw-order.unzerdirect.cancelRequestedNotification')
                })
                this.isLoading = false;
            } catch(e) {
                this.$emit('fail');
                this.createNotificationError({
                    message: this.$tc('sw-order.unzerdirect.cancelRequestFailedNotification')
                })
                this.isLoading = false;
            }
        }
    }

});
