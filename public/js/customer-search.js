(() => {
    'use strict';

    const input = document.getElementById('menu-search');
    const dropdown = document.getElementById('menu-suggestions');
    if (!input || !dropdown || !window.warmindoMenu) return;

    const form = input.form;
    const category = form.elements.namedItem('category')?.value;
    const products = Object.values(window.warmindoMenu.catalog)
        .filter(product => !category || String(product.category_id) === category);
    const currency = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
    let matches = [];
    let activeIndex = -1;

    function close() {
        dropdown.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        activeIndex = -1;
    }

    function select(index) {
        input.value = matches[index].name;
        close();
        form.requestSubmit();
    }

    function suggest() {
        const keyword = input.value.trim().toLocaleLowerCase('id-ID');
        close();
        dropdown.replaceChildren();
        if (!keyword) return;

        matches = products.filter(product => product.name.toLocaleLowerCase('id-ID').includes(keyword)).slice(0, 6);
        matches.forEach((product, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'menu-suggestion';
            option.id = `menu-suggestion-${index}`;
            option.tabIndex = -1;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            const thumbnail = document.createElement('img');
            thumbnail.src = dropdown.dataset.placeholder;
            thumbnail.alt = '';
            const label = document.createElement('span');
            const start = product.name.toLocaleLowerCase('id-ID').indexOf(keyword);
            const highlight = document.createElement('mark');
            highlight.textContent = product.name.slice(start, start + keyword.length);
            label.append(product.name.slice(0, start), highlight, product.name.slice(start + keyword.length));
            const price = document.createElement('small');
            price.textContent = `Rp${currency.format(Number(product.price))}`;
            label.append(price);
            option.append(thumbnail, label);
            option.addEventListener('click', () => select(index));
            dropdown.append(option);
        });
        dropdown.hidden = matches.length === 0;
        input.setAttribute('aria-expanded', String(matches.length > 0));
    }

    input.addEventListener('input', suggest);
    input.addEventListener('focus', suggest);
    input.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            close();
            return;
        }
        if (dropdown.hidden || event.isComposing) return;
        if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            select(activeIndex);
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            activeIndex = (activeIndex + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length;
            [...dropdown.children].forEach((option, index) => option.setAttribute('aria-selected', String(index === activeIndex)));
            const option = dropdown.children[activeIndex];
            input.setAttribute('aria-activedescendant', option.id);
            option.scrollIntoView({ block: 'nearest' });
        }
    });
    document.addEventListener('pointerdown', event => {
        if (!form.contains(event.target)) close();
    });
    form.addEventListener('focusout', event => {
        if (!form.contains(event.relatedTarget)) close();
    });
    form.addEventListener('submit', close);
})();
