import './module/sw-order'
import UnzerdirectApiService from './core/service/unzerdirect.api.service'

const { Application } = Shopware;

Application.addServiceProvider('unzerdirectApiService', () => {
    return new UnzerdirectApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});