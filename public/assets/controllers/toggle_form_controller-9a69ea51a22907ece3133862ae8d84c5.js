import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
  static targets = ["form"];
  
  toggle(event) {
    event.preventDefault();
    this.formTarget.classList.toggle("hidden");
  }
}
