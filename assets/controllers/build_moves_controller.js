import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        console.log('test');
        // const afterRenderEvent = new Event("turbo:after-stream-render");
        // document.dispatchEvent(afterRenderEvent);
    }
}
