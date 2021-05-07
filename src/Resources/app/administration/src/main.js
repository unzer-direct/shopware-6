import './module/sw-order'
import QuickpayApiService from './core/service/quickpay.api.service'

const { Application } = Shopware;

Application.addServiceProvider('quickpayApiService', () => {
    return new QuickpayApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});