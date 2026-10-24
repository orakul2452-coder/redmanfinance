(function () {
    async function copyText(value) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(value);
            return;
        }

        const temporaryInput = document.createElement('textarea');
        temporaryInput.value = value;
        temporaryInput.setAttribute('readonly', '');
        temporaryInput.style.position = 'fixed';
        temporaryInput.style.opacity = '0';
        document.body.appendChild(temporaryInput);
        temporaryInput.select();
        const copied = document.execCommand('copy');
        temporaryInput.remove();
        if (!copied) throw new Error('Clipboard copy failed');
    }

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-copy-account]');
        if (!button) return;

        const numberElement = document.getElementById(button.dataset.copyAccount);
        const status = button.closest('[data-account-container]')?.querySelector('[data-copy-account-status]');
        if (!numberElement) return;

        try {
            await copyText(numberElement.textContent.trim());
            if (status) status.textContent = 'Copied';
            button.setAttribute('aria-label', 'Account number copied');
            window.setTimeout(() => {
                if (status) status.textContent = '';
                button.setAttribute('aria-label', 'Copy account number');
            }, 1800);
        } catch (error) {
            if (status) status.textContent = 'Copy failed. Select and copy the number.';
        }
    });
}());