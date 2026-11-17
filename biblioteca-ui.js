const normalizeText = (value = "") =>
    String(value)
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLocaleLowerCase("pt-BR");

const bindCardFilter = (input) => {
    if (!(input instanceof HTMLInputElement)) return;

    const cards = Array.from(document.querySelectorAll(".menu-card"));
    const emptyState = document.querySelector("[data-filter-empty]");

    input.addEventListener("input", () => {
        const query = normalizeText(input.value.trim());
        let visibleCount = 0;

        cards.forEach((card) => {
            const matches = normalizeText(card.textContent).includes(query);
            card.hidden = !matches;
            if (matches) visibleCount += 1;
        });

        if (emptyState) {
            emptyState.hidden = visibleCount > 0;
        }
    });
};

document.querySelectorAll("[data-card-filter]").forEach(bindCardFilter);

document.querySelectorAll("form[data-confirm]").forEach((form) => {
    form.addEventListener("submit", (event) => {
        const message = form.dataset.confirm?.trim();

        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
});