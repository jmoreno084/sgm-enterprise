import {request} from './http.js';
export function notice(message, focus) {
    const status = document.getElementById('module-status');
    status.textContent = message;
    if (focus) { status.focus(); }
}
export function failure(error) { notice(error.message, true); }
export function button(label, callback) {
    const node = document.createElement('button'); node.type = 'button';
    node.className = 'btn btn-sm btn-outline-primary mr-2'; node.textContent = label;
    node.addEventListener('click', callback); return node;
}
export function link(label, href) {
    const node = document.createElement('a'); node.textContent = label; node.href = href; node.className = 'mr-2'; return node;
}
export function cell(row, value) { const node = document.createElement('td'); node.textContent = value === null ? 'No disponible' : value; row.appendChild(node); return node; }
export function fill(form, data) {
    Array.prototype.forEach.call(form.elements, function (field) {
        if (field.name && Object.prototype.hasOwnProperty.call(data, field.name)) { field.value = data[field.name] === null ? '' : data[field.name]; }
    });
}
export function values(form) {
    const data = {};
    Array.prototype.forEach.call(form.elements, function (field) { if (field.name && field.name !== 'id' && !field.disabled) { data[field.name] = field.value; } });
    if (data.version) { data.version = Number(data.version); } else { delete data.version; }
    return data;
}
export function editor(id, enabled) {
    const node = document.getElementById(id); node.hidden = !enabled; node.disabled = !enabled;
}
export function paging(load) {
    let page = 1;
    document.getElementById('previous-page').addEventListener('click', function () { if (page > 1) { page -= 1; load(); } });
    document.getElementById('next-page').addEventListener('click', function () { page += 1; load(); });
    return {current: function () { return page; }, reset: function () { page = 1; }, show: function (data) {
        document.getElementById('page-status').textContent = 'Página ' + page + ' · ' + data.total + ' registros';
        document.getElementById('previous-page').disabled = page === 1;
        document.getElementById('next-page').disabled = page * data.page_size >= data.total;
        notice(data.total ? '' : 'No hay registros con estos filtros.', false);
    }};
}
export function permissions(company) {
    return request('/api/v1/sesion').then(function (result) {
        const data = result.data;
        const membership = data.empresas.filter(function (item) { return Number(item.id) === Number(company); })[0];
        return {admin: data.usuario.es_administrador, edit: data.usuario.es_administrador || Boolean(membership && membership.rol === 'GEOLOGO')};
    });
}
export function submitting(form, work) {
    let busy = false;
    form.addEventListener('reset', function () {
        ['id', 'version'].forEach(function (name) { if (form.elements[name]) { form.elements[name].value = ''; } });
    });
    form.addEventListener('submit', function (event) {
        event.preventDefault(); if (busy) { return; } busy = true;
        const submit = form.querySelector('[type="submit"]'); submit.disabled = true;
        Promise.resolve().then(work).catch(failure).then(function () { busy = false; submit.disabled = false; });
    });
}
