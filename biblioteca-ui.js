document.querySelectorAll("[data-card-filter]").forEach((input) => {
    const cards = Array.from(document.querySelectorAll(".menu-card"));
    const emptyState = document.querySelector("[data-filter-empty]");

    const normalize = (value) => value
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLocaleLowerCase("pt-BR");

    input.addEventListener("input", () => {
        const query = normalize(input.value.trim());
        let visibleCount = 0;

        cards.forEach((card) => {
            const matches = normalize(card.textContent).includes(query);
            card.hidden = !matches;
            visibleCount += matches ? 1 : 0;
        });

        if (emptyState) {
            emptyState.hidden = visibleCount > 0;
        }
    });
});

document.querySelectorAll("form[data-confirm]").forEach((form) => {
    form.addEventListener("submit", (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});