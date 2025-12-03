let activePage = 1;
let totalPage = 20;

const createPagination = (active, total) => {
    const MAX_PAGES_TO_SHOW = 7;
    const SIDE_PAGES = 3;

    const $container = $('.component__pagination-button');
    $container.empty();

    if (total <= 1) return;

    $container.append(`<div class="pagination-button__action --switch ${active === 1 ? '--disabled' : ''}">Prev</div>`);

    let startingPage = 1;
    let endingPage = total;

    if (total > MAX_PAGES_TO_SHOW + 2) {
        startingPage = Math.max(2, active - SIDE_PAGES);
        endingPage = Math.min(total - 1, active + SIDE_PAGES);

        if (active <= SIDE_PAGES + 1) {
            startingPage = 2;
            endingPage = MAX_PAGES_TO_SHOW + 1;
        } else if (active >= total - SIDE_PAGES) {
            endingPage = total - 1;
            startingPage = total - MAX_PAGES_TO_SHOW;
        }
    } else {
        startingPage = 1;
        endingPage = total;
    }

    if (startingPage > 1) {
        $container.append(`<div class="pagination-button__action ${active === 1 ? '--active' : ''}">1</div>`);
        if (startingPage > 2) $container.append(`<div class="pagination-button__action">...</div>`);
    }

    for (let i = startingPage; i <= endingPage; i++) {
        if (i > 0 && i <= total) $container.append(`<div class="pagination-button__action ${i === active ? '--active' : ''}">${i}</div>`);
    }

    if (endingPage < total) {
        if (endingPage < total - 1) $container.append(`<div class="pagination-button__action">...</div>`);
        $container.append(`<div class="pagination-button__action ${active === total ? '--active' : ''}">${total}</div>`);
    }

    $container.append(`<div class="pagination-button__action --switch ${active === total ? '--disabled' : ''}">Next</div>`);
};

function populateDropdown(totalPage) {
    const $select = $('.component__pagination .component__select select');
    $select.empty();

    for (let i = 1; i <= totalPage; i++) $select.append(`<option value="${i}">${i}</option>`);
}

function updatePage(newPage) {
    if (newPage < 1 || newPage > totalPage) return;
    activePage = newPage;

    createPagination(activePage, totalPage);
    $('.component__pagination .component__select select').val(activePage);
}

function handlePaginationClick() {
    const $clickedButton = $(this);
    const action = $clickedButton.text().trim();

    let newPage = activePage;
    if ($clickedButton.hasClass('--disabled') || action === '...') return;

    if ($clickedButton.hasClass('--switch')) newPage = action === 'Prev' ? Math.max(1, activePage - 1) : Math.min(totalPage, activePage + 1);
    else newPage = parseInt(action);

    if (newPage !== activePage) updatePage(newPage);
}

// *** HOW TO CALL *** //
populateDropdown(totalPage);
$('.component__pagination-page span:last-child').text(`of ${totalPage}`);

createPagination(activePage, totalPage);
$('.component__pagination .component__select select').val(activePage);

$('.component__pagination-button').on('click', '.pagination-button__action', handlePaginationClick);
$('.component__pagination .component__select select').on('change', function () {
    updatePage(parseInt($(this).val()));
});
