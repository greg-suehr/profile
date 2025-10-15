// assets/panel-view.js
(() => {
  const root = document.querySelector('[data-panel-id]');
  if (!root) return;

  const grid = root.querySelector('#panel-grid');
  const search = root.querySelector('#panel-search');
  const clear = root.querySelector('#panel-search-clear');
  const selectAllBtn = root.querySelector('#panel-select-all');
  const clearSelBtn = root.querySelector('#panel-clear-selection');
  const countSpan = root.querySelector('#panel-selected-count');
  const bulkButtons = root.querySelectorAll('.panel-bulk-action');
  const bulkForm = root.querySelector('#panel-bulk-form');
  const bulkIds = root.querySelector('#panel-bulk-ids');

  const cards = Array.from(root.querySelectorAll('.panel-card'));
  const checkboxSelector = '.panel-select';

  const state = {
    selected: new Set(),
  };

  const updateSelectedCount = () => {
    if (countSpan) countSpan.textContent = String(state.selected.size);
  };

  const applySearch = () => {
    if (!grid || !search) return;
    const q = search.value.trim().toLowerCase();
    cards.forEach(card => {
      const text = (card.getAttribute('data-searchable') || '').toLowerCase();
      const match = !q || text.includes(q);
      card.classList.toggle('d-none', !match);
    });
  };

  const collectCheckboxes = () =>
    Array.from(root.querySelectorAll(checkboxSelector));

  const refreshSelectedFromDOM = () => {
    state.selected.clear();
    collectCheckboxes().forEach(cb => { if (cb.checked) state.selected.add(cb.value); });
    updateSelectedCount();
  };

  // Events
  if (search) {
    search.addEventListener('input', applySearch);
  }
  if (clear) {
    clear.addEventListener('click', () => { search.value = ''; applySearch(); });
  }
  root.addEventListener('change', (e) => {
    const t = e.target;
    if (t && t.matches(checkboxSelector)) {
      if (t.checked) state.selected.add(t.value);
      else state.selected.delete(t.value);
      updateSelectedCount();
    }
  });
  if (selectAllBtn) {
    selectAllBtn.addEventListener('click', () => {
      collectCheckboxes().forEach(cb => {
        if (!cb.closest('.panel-card').classList.contains('d-none')) {
          cb.checked = true;
          state.selected.add(cb.value);
        }
      });
      updateSelectedCount();
    });
  }
  if (clearSelBtn) {
    clearSelBtn.addEventListener('click', () => {
      collectCheckboxes().forEach(cb => { cb.checked = false; });
      state.selected.clear();
      updateSelectedCount();
    });
  }
  bulkButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      if (state.selected.size === 0) return;
      const confirmMsg = btn.getAttribute('data-confirm');
      if (confirmMsg && !window.confirm(confirmMsg)) return;

      const routeName = btn.getAttribute('data-route-name');
      const rawParams = btn.getAttribute('data-route-params') || '{}';
      let params = {};
      try { params = JSON.parse(rawParams); } catch(e) {}

      // Expect server endpoints at POST routes resolved in Twig.
      // Build action URL from a data attribute you render into the page.
      // Simpler: your dropdown items can include a data-post-url attribute.
      const postUrl = btn.dataset.postUrl || (btn.dataset.routeName ? window.location.href : null);
      if (!postUrl) { console.warn('Missing bulk post url.'); return; }

      bulkForm.setAttribute('action', postUrl);
      bulkIds.value = JSON.stringify(Array.from(state.selected));
      bulkForm.submit();
    });
  });

  // initial
  refreshSelectedFromDOM();
  applySearch();
})();
