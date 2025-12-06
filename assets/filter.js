var els = document.querySelectorAll('#filter > *');
var container = els[0].parentElement;
var filters = document.querySelectorAll('[data-filter], [data-sortby]');
function applyFilter(filter) {
    els.forEach(function(el) {
        if (el.matches(filter)) {
            el.style.display = 'block';
        } else {
            el.style.display = 'none';
        }
    });
}
function applySort(sortby) {
    if (sortby === 'order') {
      els = Array.from(els).sort(function(a, b) {
          return parseInt(a.dataset.order) - parseInt(b.dataset.order);
      });
    } else if (sortby === 'number') {
      els = Array.from(els).sort(function(a, b) {
          return parseInt(a.querySelector('.number').textContent) - parseInt(b.querySelector('.number').textContent);
      });
    } else if (sortby === 'weight') {
      els = Array.from(els).sort(function(a, b) {
          return parseFloat(a.querySelector('.weight').textContent) - parseFloat(b.querySelector('.weight').textContent);
      });
    } else {     
      els = Array.from(els).sort(function(a, b) {
          return a.querySelector('.'+sortby).textContent.localeCompare(b.querySelector('.'+sortby).textContent);
      });
    }
    container.append(...els);
}
function updateActiveButton(el) {
    filters.forEach(function(f) {
      if((el.dataset.filter && f.dataset.filter) || (el.dataset.sortby && f.dataset.sortby)) {
        if (f === el) {
            f.classList.add('is-checked');
        } else {
            f.classList.remove('is-checked');
        }
      }
    });
}
let isotopeNum = 0;
els.forEach(function(el) {
  el.style.viewTransitionName = 'isotopeNum-' + isotopeNum++;
  el.dataset.order = isotopeNum;
});
filters.forEach(function(f) {
  f.addEventListener('click', function() {
    if(!document.startViewTransition) {
      updateActiveButton(f);
      if(f.dataset.filter) applyFilter(f.dataset.filter);
      else applySort(f.dataset.sortby);
      if(typeof relayout === 'function') relayout();
    } else {
      document.startViewTransition(() => {
        updateActiveButton(f);
        if(f.dataset.filter) applyFilter(f.dataset.filter);
        else applySort(f.dataset.sortby);
        if(typeof relayout === 'function') relayout();
      });
    }
  });
});