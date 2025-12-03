const $modal = $('#modal');
const $modalIcon = $('#modalIcon');
const $modalHeading = $('#modalHeading');
const $modalDescription = $('#modalDescription');
const $modalCancelText = $('#modalCancelText');
const $modalConfirmText = $('#modalConfirmText');
const $modalCancelButton = $('#modalCancelButton');
const $modalConfirmButton = $('#modalConfirmButton');

let scrollPosition = 0;

const showModal = ({ type = 'success', iconPath = './icons.svg#placeholder', heading = 'This is a heading', description = 'This is a description', confirmText = 'Confirm', cancelText = 'Cancel', onConfirm = () => hideModal(), onCancel = () => hideModal() }) => {
    $modal.removeClass('--success --warning --error');
    $modal.addClass(`--${type}`);

    $modalHeading.text(`${heading}`);
    $modalDescription.text(`${description}`);
    $modalCancelText.text(`${cancelText}`);
    $modalConfirmText.text(`${confirmText}`);

    $modalIcon.html(`<use href="${iconPath}"></use>`);

    if (type === 'success') $modalCancelButton.hide();
    else $modalCancelButton.show();

    $modalCancelButton.off('click');
    $modalConfirmButton.off('click');

    $modalCancelButton.on('click', () => {
        if (onCancel) onCancel();
        hideModal();
    });

    $modalConfirmButton.on('click', () => {
        if (onConfirm) onConfirm();
        hideModal();
    });

    $modal.addClass('--active');

    scrollPosition = $(window).scrollTop();

    $('body').addClass('--modal-active');
    $('body').css('top', `-${scrollPosition}px`);
};

function hideModal() {
    $modal.removeClass('--active');
    $modal.removeClass('--success --warning --error');

    $('body').removeClass('--modal-active');
    $('body').css('top', '');
    $(window).scrollTop(scrollPosition);

    $modalCancelButton.off('click');
    $modalConfirmButton.off('click');
}
