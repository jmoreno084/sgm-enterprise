let csrfToken = '';
export function setCsrf(token) { csrfToken = token; }
export function request(path, options) {
    const settings = Object.assign({method: 'GET', credentials: 'same-origin'}, options || {});
    settings.headers = Object.assign({'Accept': 'application/json'}, settings.headers || {});
    if (settings.method !== 'GET' && settings.method !== 'HEAD') {
        settings.headers['X-CSRF-Token'] = csrfToken;
    }
    if (settings.body && !(settings.body instanceof FormData)) {
        settings.headers['Content-Type'] = 'application/json';
        settings.body = JSON.stringify(settings.body);
    }
    if (path.indexOf('/api/v1/') !== 0) { return Promise.reject(new Error('Ruta no permitida.')); }
    return fetch(path, settings).then(function (response) {
        if (response.status === 204) { return null; }
        return response.json().catch(function () {
            throw new Error('No fue posible interpretar la respuesta del servicio.');
        }).then(function (body) {
            if (!response.ok) {
                const error = new Error(body.error ? body.error.message : 'No fue posible completar la operación.');
                error.status = response.status;
                throw error;
            }
            if (body.data && body.data.csrf) { setCsrf(body.data.csrf); }
            return body;
        });
    });
}

