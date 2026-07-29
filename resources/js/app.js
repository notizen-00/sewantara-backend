const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const sidebar = document.querySelector('[data-sidebar]');
const searchInput = document.querySelector('[data-search-input]');
const bookingTable = document.querySelector('[data-booking-table]');

sidebarToggle?.addEventListener('click', () => {
    sidebar?.classList.toggle('hidden');
});

searchInput?.addEventListener('input', (event) => {
    const query = event.target.value.toLowerCase().trim();

    bookingTable?.querySelectorAll('tr').forEach((row) => {
        row.hidden = query.length > 0 && !row.textContent.toLowerCase().includes(query);
    });
});
