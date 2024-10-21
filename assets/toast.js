
import { Toast } from 'bootstrap';

export function toast(text, type = 'primary') {

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

    toast.getElementsByClassName('toast-body')[0].textContent = text;

    const toastBootstrap = Toast.getOrCreateInstance(toast);
    toastBootstrap.show();
}