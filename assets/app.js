
import './bootstrap.js';
import 'bootstrap';

/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */

import './styles/startbootstrap-shop-homepage-gh-pages/styles.css';
import './styles/startbootstrap-shop-item-gh-pages/styles.css';

import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

document.addEventListener('DOMContentLoaded', function () {
    const afterRenderEvent = new Event("turbo:after-stream-render");
    document.addEventListener("turbo:before-stream-render", (event) => {
        const originalRender = event.detail.render;

        event.detail.render = function (streamElement) {
            originalRender(streamElement);
            document.dispatchEvent(afterRenderEvent);
        }
    });
});