import './module/sw-order'
import UnzerDirectApiService from './core/service/unzerdirect.api.service'

const { Application } = Shopware;

Application.addServiceProvider('unzerdirectApiService', () => {
    return new UnzerDirectApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});