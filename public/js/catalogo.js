document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('catalogoSearch');
    const cards = [...document.querySelectorAll('.catalog-card')];
    const chips = [...document.querySelectorAll('.catalog-chip')];
    const count = document.getElementById('catalogoCount');
    const countLabel = document.getElementById('catalogoCountLabel');
    const empty = document.getElementById('catalogoEmpty');
    const clear = document.getElementById('catalogoClear');

    if (!search || cards.length === 0) {
        if (cards.length === 0 && empty) empty.hidden = false;
        return;
    }

    let category = 'all';

    const normalize = (value) => value
        .toLocaleLowerCase('es')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    const applyFilters = () => {
        const query = normalize(search.value.trim());
        let visible = 0;

        cards.forEach((card) => {
            const name = normalize(card.dataset.name || '');
            const matchesName = name.includes(query);
            const matchesCategory = category === 'all' || card.dataset.category === category;
            const show = matchesName && matchesCategory;

            card.hidden = !show;
            if (show) visible += 1;
        });

        if (count) count.textContent = String(visible);
        if (countLabel) countLabel.textContent = visible === 1 ? 'pastel disponible' : 'pasteles disponibles';
        if (empty) empty.hidden = visible !== 0;
    };

    search.addEventListener('input', applyFilters);

    chips.forEach((chip) => {
        chip.addEventListener('click', () => {
            chips.forEach((item) => item.classList.remove('active'));
            chip.classList.add('active');
            category = chip.dataset.category || 'all';
            applyFilters();
        });
    });

    clear?.addEventListener('click', () => {
        search.value = '';
        category = 'all';
        chips.forEach((item) => item.classList.toggle('active', item.dataset.category === 'all'));
        applyFilters();
        search.focus();
    });

    applyFilters();
});
