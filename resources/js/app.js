import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('walletValidation', (config = {}) => ({
    value: config.initialValue || '',
    status: 'idle',
    message: '',
    address: '',
    isValidating: false,
    isSubmitting: false,
    validationUrl: config.validationUrl || '',
    expectedPrefix: config.expectedPrefix || '',
    hasServerError: Boolean(config.hasServerError),
    lastValidatedValue: null,

    init() {
        if (this.hasServerError) {
            this.$nextTick(() => this.focusInput());
        }
    },

    focusInput() {
        if (this.$refs.input) {
            this.$refs.input.focus();
        }
    },

    cleanedValue() {
        return (this.value || '').replace(/\s+/g, '');
    },

    handleInput() {
        this.hasServerError = false;
        this.lastValidatedValue = null;

        if (this.status !== 'idle') {
            this.status = 'idle';
            this.message = '';
            this.address = '';
        }
    },

    handleBlur() {
        if (!this.validationUrl) {
            return;
        }

        if (this.cleanedValue()) {
            this.validate();
        }
    },

    async validate({ force = false } = {}) {
        if (!this.validationUrl) {
            return 'unknown';
        }

        const cleaned = this.cleanedValue();

        if (!cleaned) {
            this.status = 'error';
            this.message = 'Please paste your wallet account key.';
            this.address = '';
            this.focusInput();
            return 'error';
        }

        if (cleaned !== this.value) {
            this.value = cleaned;
        }

        if (!force && cleaned === this.lastValidatedValue && this.status === 'success') {
            return 'success';
        }

        this.isValidating = true;
        this.status = 'validating';
        this.message = '';
        this.address = '';

        try {
            const response = await fetch(this.validationUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ bip84_xpub: cleaned }),
            });

            let payload = {};
            try {
                payload = await response.json();
            } catch (error) {
                payload = {};
            }

            if (!response.ok) {
                const message =
                    payload?.errors?.bip84_xpub?.[0] ||
                    payload?.message ||
                    'That key does not look right. Check you copied the full account public key (no spaces or line breaks).';

                this.status = 'error';
                this.message = message;
                this.address = '';
                this.isValidating = false;
                this.focusInput();
                this.lastValidatedValue = cleaned;
                return 'error';
            }

            this.status = 'success';
            this.message = 'Address validated for this key.';
            this.address = payload.address || '';
            this.isValidating = false;
            this.lastValidatedValue = cleaned;
            return 'success';
        } catch (error) {
            this.status = 'error';
            this.message = 'We could not validate this key right now. Please try again.';
            this.address = '';
            this.isValidating = false;
            return 'unknown';
        }
    },

    async handleSubmit(event) {
        if (this.isSubmitting) {
            return;
        }

        this.isSubmitting = true;

        const result = await this.validate({ force: true });

        if (result === 'success' || result === 'unknown') {
            event.target.submit();
            return;
        }

        this.isSubmitting = false;
    },
}));

Alpine.start();
