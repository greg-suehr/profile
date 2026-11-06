import './bootstrap.js';
import './styles/app.scss';

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap

import bsCustomFileInput from 'bs-custom-file-input';
bsCustomFileInput.init();

// ShowPage header actions: honor data-confirm (e.g. "Rollback") before following
// the link or submitting the POST form. Table and panel views bind their own.
document.addEventListener('click', (e) => {
  const action = e.target.closest('.show-page-action[data-confirm]');
  if (action && !window.confirm(action.dataset.confirm)) {
    e.preventDefault();
  }
});
