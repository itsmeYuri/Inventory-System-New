const initializeTabComponent = (tabGroup) => {
    tabGroup.on('click', (event) => {
        const $targetTab = $(event.target).closest('span');
        if (!$targetTab.length) return;

        activateTab($targetTab, $componentTab);
    });
};

function activateTab(tab, tabGroup) {
    tabGroup.find('.component__tab-navigation').removeClass('--active');
    tab.addClass('--active');

    const tabContent = tab.data('content');
    tabGroup.find('.component__tab-content').removeClass('--active');
    tabGroup.find(`#${tabContent}`).addClass('--active');
}

// *** HOW TO CALL *** //
const $componentTab = $('#componentTab');
initializeTabComponent($componentTab);
