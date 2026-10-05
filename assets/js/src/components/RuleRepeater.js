import { Repeater } from "./Repeater";
import TomSelect from 'tom-select';

// Unique query param defeats a proxy/CDN that caches by URL and ignores Cache-Control.
// The counter matters: two searches can share a millisecond, and a repeated URL is a cache hit.
let noCacheSeq = 0;
const noCacheParam = () => `&_=${Date.now()}${noCacheSeq++}`;

/**
 * Repeater component.
 *
 * @since    1.0.0
 */
export class RuleRepeater extends Repeater {

    constructor(selector, data = [], readonly = false) {


        const renderName = (item, escape) => `<div>${escape(item.name)}</div>`;
        const renderTitle = (item, escape) => `<div>${escape(item.title)}</div>`;
        const fetchConfig = {
            method: 'GET',
            mode: 'same-origin',
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': alondra.api.nonce,
            }
        };

        const loadProduct = async function (query, callback) {
            if (readonly) return;
            const exclude = this.items.length ? `&ignore=${encodeURIComponent(this.items.join(','))}` : '';
            // debugger
            const response = await fetch(`${alondra.api.root}${alondra.api.namespace}/tiered-pricing/product/?search=${encodeURIComponent(query)}${exclude}${noCacheParam()}`, fetchConfig);
            const data = await response.json();
            callback(data);
        };

        const loadCategory = async function (query, callback) {
            if (readonly) return;
            const exclude = this.items.length ? `&exclude=${encodeURIComponent(this.items.join(','))}` : '';
            const response = await fetch(`${alondra.api.root}wc/v2/products/categories?search=${encodeURIComponent(query)}&per_page=3&_fields=id,name${exclude}${noCacheParam()}`, fetchConfig);
            const data = await response.json();
            callback(data);
        };

        const loadTag = async function (query, callback) {
            if (readonly) return;
            const exclude = this.items.length ? `&exclude=${encodeURIComponent(this.items.join(','))}` : '';
            const response = await fetch(`${alondra.api.root}wc/v2/products/tags?search=${encodeURIComponent(query)}&per_page=3&_fields=id,name${exclude}${noCacheParam()}`, fetchConfig);
            const data = await response.json();
            callback(data);
        };

        const loadUser = async function (query, callback) {
            if (readonly) return;
            const exclude = this.items.length ? `&exclude=${encodeURIComponent(this.items.join(','))}` : '';
            const response = await fetch(`${alondra.api.root}wp/v2/users?search=${encodeURIComponent(query)}&per_page=3${exclude}${noCacheParam()}`, fetchConfig);
            const data = await response.json();
            callback(data);
        };

        const loadRole = async function (query, callback) {
            if (readonly) return;
            const data = [];
            for (const role of alondra.roles) {
                if (!this.items.filter((item) => item === role.role).length && role.name.toLowerCase().includes(query.toLowerCase())) {
                    data.push(role);
                }
                if (data.length === 3) break;
            }
            callback(data);
        };

        const selectPlugins = readonly ? ['no_backspace_delete', 'no_active_items'] : ['no_backspace_delete', 'remove_button', 'no_active_items'];


        // call parent constructor
        super(selector, data,
            (newRow, previousRow, data) => {
                let products = [];
                let categories = [];
                let tags = [];
                let users = [];
                let roles = [];
                if (data) {
                    products = data.products;
                    categories = data.categories;
                    tags = data.tags;
                    users = data.users;
                    roles = data.roles;
                }

                const productsSelect = new TomSelect(newRow.querySelector('input[name="products"]'), {
                    valueField: 'id',
                    labelField: 'title',
                    searchField: ['title', 'id', 'sku'],
                    openOnFocus: false,
                    render: {
                        option: renderTitle,
                        item: renderTitle,
                    },
                    load: loadProduct,
                    create: false,
                    plugins: selectPlugins
                    // placeholder: 'Select product',
                });
                products.forEach(product => {
                    productsSelect.addOption(product);
                    productsSelect.addItem(product.id);
                });

                // control.addOption({id: 15});
                // control.addItem(15);

                const catSelect = new TomSelect(newRow.querySelector('input[name="categories"]'), {
                    valueField: 'id',
                    labelField: 'name',
                    searchField: ['name'],
                    render: {
                        option: renderName,
                        item: renderName,
                    },
                    load: loadCategory,
                    create: false,
                    plugins: selectPlugins
                    // placeholder: 'Select category',
                });
                categories.forEach(term => {
                    catSelect.addOption(term);
                    catSelect.addItem(term.id);
                });

                const tagSelect = new TomSelect(newRow.querySelector('input[name="tags"]'), {
                    valueField: 'id',
                    labelField: 'name',
                    searchField: ['name'],
                    render: {
                        option: renderName,
                        item: renderName,
                    },
                    load: loadTag,
                    plugins: selectPlugins,
                    create: false,
                    // placeholder: 'Select tags',
                });
                tags.forEach(term => {
                    tagSelect.addOption(term);
                    tagSelect.addItem(term.id);
                });

                const userSelect = new TomSelect(newRow.querySelector('input[name="users"]'), {
                    valueField: 'id',
                    labelField: 'name',
                    searchField: ['name'],
                    render: {
                        option: renderName,
                        item: renderName,
                    },
                    load: loadUser,
                    create: false,
                    plugins: selectPlugins
                    // placeholder: 'Select users',
                });
                users.forEach(user => {
                    userSelect.addOption(user);
                    userSelect.addItem(user.id);
                });

                const rolesSelect = new TomSelect(newRow.querySelector('input[name="profiles"]'), {
                    valueField: 'role',
                    labelField: 'name',
                    searchField: ['name'],
                    render: {
                        option: renderName,
                        item: renderName,
                    },
                    load: loadRole,
                    create: false,
                    plugins: selectPlugins
                    // placeholder: 'Select roles',
                });
                roles.forEach(role => {

                    const r = alondra.roles.filter((item) => item.role === role);

                    rolesSelect.addOption(r);
                    rolesSelect.addItem(role);
                });

                if (readonly) {
                    newRow.querySelectorAll('input,select').forEach(elem => elem.disabled = true);
                }
            },
            null,
            readonly);


    }
}