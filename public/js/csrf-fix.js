// csrf-fix.js
class CSRFManager {
    constructor() {
        this.token = null;
        this.initialized = false;
        this.init();
    }

    init() {
        // Ambil token dari meta tag
        this.token = document.querySelector('meta[name="csrf-token"]')?.content;
        
        if (!this.token) {
            console.error('CSRF Token not found in meta tag!');
            this.fetchNewToken();
        } else {
            console.log('CSRF Token loaded:', this.token.substring(0, 20) + '...');
            this.initialized = true;
        }
        
        // Intercept semua fetch requests
        this.interceptFetch();
    }

    async fetchNewToken() {
        try {
            const response = await fetch('/api/csrf-token', {
                credentials: 'include'
            });
            
            if (response.ok) {
                const data = await response.json();
                this.token = data.token;
                
                // Update meta tag
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) {
                    meta.content = this.token;
                }
                
                console.log('New CSRF Token fetched:', this.token.substring(0, 20) + '...');
                this.initialized = true;
            }
        } catch (error) {
            console.error('Failed to fetch CSRF token:', error);
        }
    }

    interceptFetch() {
        const originalFetch = window.fetch;
        
        window.fetch = async (url, options = {}) => {
            // Jika ini POST/PUT/PATCH/DELETE request
            const method = options.method ? options.method.toUpperCase() : 'GET';
            const isWriteMethod = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);
            
            // Untuk request ke aplikasi kita sendiri
            const isInternalRequest = typeof url === 'string' && 
                (url.startsWith('/') || url.includes(window.location.hostname));
            
            if (isWriteMethod && isInternalRequest) {
                // Pastikan headers ada
                options.headers = options.headers || {};
                
                // Tambahkan CSRF token
                if (this.token) {
                    options.headers['X-CSRF-TOKEN'] = this.token;
                }
                
                // Tambahkan ke body jika JSON
                if (options.body && typeof options.body === 'string') {
                    try {
                        const data = JSON.parse(options.body);
                        if (!data._token && this.token) {
                            data._token = this.token;
                            options.body = JSON.stringify(data);
                        }
                    } catch (e) {
                        // Bukan JSON, skip
                    }
                }
                
                // Pastikan credentials include
                options.credentials = options.credentials || 'include';
                
                // Tambahkan header untuk AJAX
                options.headers['X-Requested-With'] = 'XMLHttpRequest';
                options.headers['Accept'] = 'application/json';
            }
            
            return originalFetch(url, options);
        };
    }

    getToken() {
        return this.token;
    }

    refreshToken() {
        return this.fetchNewToken();
    }
}

// Buat global instance
window.CSRF = new CSRFManager();

// Export untuk modules
export default window.CSRF;