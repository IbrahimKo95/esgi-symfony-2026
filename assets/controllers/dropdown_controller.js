import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['menu']

    toggle() {
        this.menuTarget.classList.toggle('hidden')
    }

    close() {
        this.menuTarget.classList.add('hidden')
    }

    outsideClick(event) {
        if (!this.element.contains(event.target)) {
            this.close()
        }
    }

    connect() {
        this._handler = this.outsideClick.bind(this)
        document.addEventListener('click', this._handler)
    }

    disconnect() {
        document.removeEventListener('click', this._handler)
    }
}
