import ApiService from 'src/core/service/api.service';

export default class UnzerDirectApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'unzerdirect') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'unzerdirectService';
    }
    
    async capture(id, amount) {
        return await this.httpClient.post('_action/unzerdirect/capture', {
            id: id,
            amount: amount
        },{
            headers: this.getBasicHeaders()
        });
    }
    
    async cancel(id) {
        return await this.httpClient.post('_action/unzerdirect/cancel', {
            id: id,
        },{
            headers: this.getBasicHeaders()
        });
    }
    
    async refund(id, amount) {
        return await this.httpClient.post('_action/unzerdirect/refund', {
            id: id,
            amount: amount
        },{
            headers: this.getBasicHeaders()
        });
    }
    
    async refresh(id) {
        return await this.httpClient.post('_action/unzerdirect/refresh', {
            id: id,
        },{
            headers: this.getBasicHeaders()
        });
    }
}