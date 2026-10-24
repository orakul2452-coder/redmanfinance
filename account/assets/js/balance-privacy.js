(function () {
    let visible = false;

    function render() {
        document.querySelectorAll('[data-private-balance]').forEach((element) => {
            element.textContent = visible ? '$' + element.dataset.privateBalance : '••••••';
        });
        document.querySelectorAll('[data-balance-toggle]').forEach((button) => {
            button.setAttribute('aria-pressed', visible ? 'true' : 'false');
            button.setAttribute('aria-label', visible ? 'Hide balances' : 'Show balances');
            button.title = visible ? 'Hide balances' : 'Show balances';
            button.innerHTML = visible ? '<i class="fa fa-eye-slash" aria-hidden="true"></i>' : '<i class="fa fa-eye" aria-hidden="true"></i>';
        });
    }

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-balance-toggle]');
        if (!button) return;
        visible = !visible;
        render();
    });

    render();
}());