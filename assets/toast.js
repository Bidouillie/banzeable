
import { Toast } from 'bootstrap';

export class ToastMaker {

    static #toast;

    static toast(text, type = 'primary', delay = 1000) {

        // TODO bug when toast is disappearing 
        if (this.#toast) {
            this.#toast.dispose();
        }

        const toast = document.getElementById('toast');

        for (let i = toast.classList.length - 1; i >= 0; i--) {
            const className = toast.classList[i];
            if (className.startsWith('text-bg-')) {
                toast.classList.remove(className);
            }
        }

        switch (type) {
            case 'primary':
            case 'secondary':
            case 'info':
            case 'success':
            case 'warning':
            case 'danger':
            case 'light':
            case 'dark':
                toast.classList.add('text-bg-' + type);
                break;
            default:
                break;
        }

        toast.setAttribute('data-bs-delay', delay.toString());

        toast.getElementsByClassName('toast-body')[0].textContent = text;

        this.#toast = Toast.getOrCreateInstance(toast);
        this.#toast.show();
    }
}