document.addEventListener('click', function (event) {
    if (event.target.closest('[data-print]')) { event.preventDefault(); window.print(); }
});
