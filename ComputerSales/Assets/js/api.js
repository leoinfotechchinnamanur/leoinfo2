// ComputerSales/Assets/js/api.js
export class API {
    constructor(baseUrl) {
        this.baseUrl = baseUrl;
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    }
    
    async request(endpoint, options = {}) {
        const url = this.baseUrl + endpoint;
        
        const defaults = {
            headers: {
                'X-CSRF-Token': this.csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };
        
        if (options.body && !(options.body instanceof FormData)) {
            defaults.headers['Content-Type'] = 'application/json';
        }
        
        const response = await fetch(url, { ...defaults, ...options });
        
        if (response.status === 401) {
            window.location.href = '/auth/login.php?redirect=' + encodeURIComponent(location.pathname);
            throw new Error('Authentication required');
        }
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return response.json();
    }
    
    get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    }
    
    post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    
    delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
}