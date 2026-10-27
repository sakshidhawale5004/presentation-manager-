// Open / close the sidebar on phones and tablets
document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var toggle  = document.getElementById('sidebarToggle');

    if (!sidebar || !overlay || !toggle) { return; }

    function openMenu()  { sidebar.classList.add('open');    overlay.classList.add('show'); }
    function closeMenu() { sidebar.classList.remove('open'); overlay.classList.remove('show'); }

    toggle.addEventListener('click', openMenu);
    overlay.addEventListener('click', closeMenu);
});
