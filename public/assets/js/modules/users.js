import {request} from './http.js';
const form = document.getElementById('user-form');
const membership = document.getElementById('membership-form');
const status = document.getElementById('users-status');
let page = 1;
let total = 0;
let busy = false;
function message(text) { status.textContent = text; status.focus(); }
function action(label, callback) {
    const button = document.createElement('button'); button.type = 'button';
    button.className = 'btn btn-sm btn-outline-primary mr-2'; button.textContent = label;
    button.addEventListener('click', callback); return button;
}
function edit(user) {
    ['id', 'version', 'correo', 'nombre', 'numero_documento', 'estado'].forEach(function (key) { form.elements[key].value = user[key]; });
    form.elements.es_administrador.checked = user.es_administrador;
    form.elements.password.value = ''; form.elements.password.required = false;
    form.elements.nombre.focus();
}
function loadUsers() {
    status.textContent = 'Cargando usuarios…';
    const correo = document.getElementById('search-correo').value;
    return request('/api/v1/usuarios?page=' + page + '&correo=' + encodeURIComponent(correo)).then(function (result) {
        const list = document.getElementById('users-list'); list.textContent = ''; total = result.data.total;
        result.data.items.forEach(function (user) {
            const row = document.createElement('tr');
            [user.nombre, user.correo, user.estado].forEach(function (value) { const cell = document.createElement('td'); cell.textContent = value; row.appendChild(cell); });
            const buttons = document.createElement('td');
            buttons.appendChild(action('Editar', function () { edit(user); }));
            buttons.appendChild(action('Eliminar', function () {
                if (!window.confirm('¿Eliminar este usuario? Si tiene historial, puedes desactivarlo desde Editar.')) { return; }
                request('/api/v1/usuarios/' + user.id, {method: 'DELETE', body: {version: user.version}}).then(loadUsers).catch(function (error) { message(error.message); });
            })); row.appendChild(buttons); list.appendChild(row);
        });
        document.getElementById('page-status').textContent = 'Página ' + page + ' · ' + total + ' usuarios';
        document.getElementById('previous-page').disabled = page === 1;
        document.getElementById('next-page').disabled = page * 25 >= total;
        status.textContent = total ? '' : 'No hay usuarios con estos filtros.';
    });
}
function loadMembers() {
    const company = membership.elements.empresa_id.value;
    const list = document.getElementById('memberships-list'); list.textContent = '';
    membership.elements.id.value = ''; membership.elements.version.value = '';
    if (!company) { return Promise.resolve(); }
    return request('/api/v1/empresas/' + company + '/usuarios').then(function (result) {
        result.data.items.forEach(function (member) {
            const item = document.createElement('li');
            const label = document.createElement('span'); label.textContent = member.nombre + ' · ' + member.rol + ' · ' + member.estado + ' ';
            item.appendChild(label);
            item.appendChild(action('Editar', function () {
                ['id', 'version', 'usuario_id', 'rol', 'estado'].forEach(function (key) { membership.elements[key].value = member[key]; });
                membership.elements.rol.focus();
            }));
            item.appendChild(action('Revocar', function () {
                if (!window.confirm('¿Revocar esta membresía?')) { return; }
                request('/api/v1/empresas/' + company + '/usuarios/' + member.id, {method: 'DELETE', body: {version: Number(member.version)}}).then(loadMembers).catch(function (error) { message(error.message); });
            })); list.appendChild(item);
        });
    });
}
form.elements.password.required = true;
request('/api/v1/sesion').then(function (result) {
    if (!result.data.autenticado || !result.data.usuario.es_administrador) { window.location.assign('/login'); return; }
    result.data.empresas.forEach(function (company) { const option = document.createElement('option'); option.value = company.id; option.textContent = company.razon_social; membership.elements.empresa_id.appendChild(option); });
    return Promise.all([loadUsers(), loadMembers()]);
}).catch(function (error) { message(error.message); });
form.addEventListener('submit', function (event) {
    event.preventDefault(); if (busy) { return; } busy = true;
    const id = form.elements.id.value;
    const data = {correo: form.elements.correo.value, nombre: form.elements.nombre.value, numero_documento: form.elements.numero_documento.value, estado: form.elements.estado.value, es_administrador: form.elements.es_administrador.checked};
    if (id) { data.version = Number(form.elements.version.value); }
    if (!id || form.elements.password.value) { data.password = form.elements.password.value; }
    request('/api/v1/usuarios' + (id ? '/' + id : ''), {method: id ? 'PUT' : 'POST', body: data}).then(function () { form.reset(); form.elements.id.value = ''; form.elements.version.value = ''; form.elements.password.required = true; return loadUsers(); }).catch(function (error) { message(error.message); }).then(function () { form.elements.password.value = ''; busy = false; });
});
document.getElementById('new-user').addEventListener('click', function () { form.reset(); form.elements.id.value = ''; form.elements.version.value = ''; form.elements.password.required = true; form.elements.nombre.focus(); });
document.getElementById('search-users').addEventListener('submit', function (event) { event.preventDefault(); page = 1; loadUsers().catch(function (error) { message(error.message); }); });
document.getElementById('previous-page').addEventListener('click', function () { if (page > 1) { page -= 1; loadUsers().catch(function (error) { message(error.message); }); } });
document.getElementById('next-page').addEventListener('click', function () { if (page * 25 < total) { page += 1; loadUsers().catch(function (error) { message(error.message); }); } });
membership.elements.empresa_id.addEventListener('change', function () { loadMembers().catch(function (error) { message(error.message); }); });
document.getElementById('new-membership').addEventListener('click', function () { membership.elements.id.value = ''; membership.elements.version.value = ''; membership.elements.usuario_id.value = ''; membership.elements.usuario_id.focus(); });
membership.addEventListener('submit', function (event) {
    event.preventDefault(); if (busy) { return; } busy = true;
    const id = membership.elements.id.value;
    const data = {usuario_id: Number(membership.elements.usuario_id.value), rol: membership.elements.rol.value, estado: membership.elements.estado.value};
    if (id) { data.version = Number(membership.elements.version.value); }
    request('/api/v1/empresas/' + membership.elements.empresa_id.value + '/usuarios' + (id ? '/' + id : ''), {method: id ? 'PUT' : 'POST', body: data}).then(loadMembers).catch(function (error) { message(error.message); }).then(function () { busy = false; });
});
document.getElementById('logout').addEventListener('click', function () { request('/api/v1/sesion', {method: 'DELETE', body: {}}).then(function () { window.location.assign('/login'); }).catch(function (error) { message(error.message); }); });
