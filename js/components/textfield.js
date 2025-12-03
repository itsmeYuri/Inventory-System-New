const setupPasswordVisibility = (iconClosePath, iconOpenPath) => {
    const toggles = $('.visibility-toggle');
    toggles.on('click', function () {
        const toggle = $(this);
        const input = toggle.closest('.component__textfield').find('input');
        const isVisibile = input.attr('type') === 'text';

        input.attr('type', isVisibile ? 'password' : 'text');

        const path = isVisibile ? iconClosePath : iconOpenPath;
        toggle.find('use').attr('href', path);
    });
};

setupPasswordVisibility('icons.svg#placeholder', 'icons.svg#search');
