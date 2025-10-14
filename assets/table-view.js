/**
 * TableView - Client-side controller for table interactions
 * 
 * Features:
 * - Client-side search/filter (with server-side fallback)
 * - Column sorting (preserves across searches)
 * - Row selection (persists across pagination)
 * - Bulk action handling
 * - Progressive enhancement (works without JS)
 */

(function() {
  'use strict';
  
  const instances = new Map();
  
  class TableViewController {
    constructor(tableId, options = {}) {
      this.tableId = tableId;
      this.options = Object.assign({
        selectable: false,
        serverSide: false,
      }, options);
      
      // State
      this.selectedIds = new Set();
      this.currentSort = { key: null, direction: 'asc' };
      this.searchTerms = [];
      
      // DOM references
      this.container = document.querySelector(`[data-table-id="${tableId}"]`);
      if (!this.container) {
        console.warn(`TableView: Container not found for table "${tableId}"`);
        return;
      }
      
      this.table = this.container.querySelector(`[data-table="${tableId}"]`);
      this.searchInput = this.container.querySelector(`[data-table-search="${tableId}"]`);
      this.searchClear = this.container.querySelector('.table-search-clear');
      this.selectAllCheckbox = this.container.querySelector('.table-select-all');
      this.rows = Array.from(this.table.querySelectorAll('tbody tr[data-row-id]'));
      
      this.statVisible = this.container.querySelector('.table-stat-visible');
      this.statTotal = this.container.querySelector('.table-stat-total');
      this.statSelected = this.container.querySelector('.table-stat-selected');
      this.statSelectedWrapper = this.container.querySelector('.table-stat-selected-wrapper');
      
      this.init();
    }
    
    init() {
      this.bindSearchEvents();
      this.bindSortEvents();
      
      if (this.options.selectable) {
        this.bindSelectionEvents();
      }
      
      this.bindBulkActions();
      this.bindQuickActions();
      this.bindRowLinks();

      // accesses session storage for e.g. bulk action selections
      this.loadPersistedSelections();
      
      // Initial stats update
      this.updateStats();
    }
    
    // === SEARCH & FILTER ===
    
    bindSearchEvents() {
      if (!this.searchInput) return;
      
      let debounceTimer;
      
      this.searchInput.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => this.applySearch(e.target.value), 300);
      });
      
      if (this.searchClear) {
        this.searchClear.addEventListener('click', () => {
          this.searchInput.value = '';
          this.searchInput.focus();
          this.applySearch('');
        });
      }
    }
    
    applySearch(query) {
      if (this.options.serverSide) {
        // Server-side: redirect with query param
        const url = new URL(window.location);
        if (query.trim()) {
          url.searchParams.set('search', query);
        } else {
          url.searchParams.delete('search');
        }
        window.location.href = url.toString();
        return;
      }
      
      // Client-side filtering
      this.searchTerms = this.parseSearchTerms(query);
      
      let visibleCount = 0;
      for (const row of this.rows) {
        const haystack = (row.dataset.searchable || '').toLowerCase();
        const matches = this.matchesSearch(haystack, this.searchTerms);
        
        row.classList.toggle('d-none', !matches);
        if (matches) visibleCount++;
      }
      
      this.updateStats(visibleCount);
      this.toggleEmptyState(visibleCount === 0);
    }
    
    parseSearchTerms(query) {
      return query.toLowerCase()
        .split(/[,\n]/g)
        .map(term => term.trim())
        .filter(Boolean);
    }
    
    matchesSearch(haystack, terms) {
      if (terms.length === 0) return true;
      return terms.some(term => haystack.includes(term));
    }
    
    // === SORTING ===
    
    bindSortEvents() {
      const sortHeaders = this.table.querySelectorAll('th[data-sortable]');
      
      sortHeaders.forEach(header => {
        header.addEventListener('click', () => {
          const key = header.dataset.sortKey;
          this.toggleSort(key, header);
        });
      });
    }
    
    toggleSort(key, header) {
      // Determine new direction
      let direction = 'asc';
      if (this.currentSort.key === key) {
        direction = this.currentSort.direction === 'asc' ? 'desc' : 'asc';
      }
      
      this.currentSort = { key, direction };
      
      if (this.options.serverSide) {
        // Server-side: redirect with sort params
        const url = new URL(window.location);
        url.searchParams.set('sort', key);
        url.searchParams.set('direction', direction);
        window.location.href = url.toString();
        return;
      }
      
      // Client-side sorting
      this.sortRows(key, direction);
      this.updateSortIcons(header, direction);
    }
    
    sortRows(key, direction) {
      const tbody = this.table.querySelector('tbody');
      const fieldIndex = this.getFieldIndex(key);
      
      const sorted = this.rows.slice().sort((a, b) => {
        const aCell = a.cells[fieldIndex + (this.options.selectable ? 1 : 0)];
        const bCell = b.cells[fieldIndex + (this.options.selectable ? 1 : 0)];
        
        const aVal = this.getCellValue(aCell);
        const bVal = this.getCellValue(bCell);
        
        let comparison = 0;
        if (aVal < bVal) comparison = -1;
        if (aVal > bVal) comparison = 1;
        
        return direction === 'asc' ? comparison : -comparison;
      });
      
      // Re-append rows in sorted order
      sorted.forEach(row => tbody.appendChild(row));
      this.rows = sorted;
    }
    
    getFieldIndex(key) {
      const headers = Array.from(this.table.querySelectorAll('thead th'));
      return headers.findIndex(h => h.dataset.sortKey === key);
    }
    
    getCellValue(cell) {
      const text = cell.textContent.trim();
      
      // Try parsing as number
      const num = parseFloat(text.replace(/[^0-9.-]/g, ''));
      if (!isNaN(num)) return num;
      
      // Try parsing as date
      const date = Date.parse(text);
      if (!isNaN(date)) return date;
      
      return text.toLowerCase();
    }
    
    updateSortIcons(activeHeader, direction) {
      // Reset all icons
      this.table.querySelectorAll('.sort-icon').forEach(icon => {
        icon.className = 'bi bi-chevron-expand text-muted sort-icon';
      });
      
      // Set active icon
      const icon = activeHeader.querySelector('.sort-icon');
      if (icon) {
        icon.className = direction === 'asc' 
          ? 'bi bi-chevron-up sort-icon'
          : 'bi bi-chevron-down sort-icon';
      }
    }
    
    // === SELECTION ===
    
    bindSelectionEvents() {
      if (this.selectAllCheckbox) {
        this.selectAllCheckbox.addEventListener('change', (e) => {
          this.toggleSelectAll(e.target.checked);
        });
      }
      
      this.rows.forEach(row => {
        const checkbox = row.querySelector('.table-row-select');
        if (checkbox) {
          checkbox.addEventListener('change', (e) => {
            this.toggleRowSelection(row.dataset.rowId, e.target.checked);
          });
        }
      });
    }
    
    toggleSelectAll(checked) {
      // Only select/deselect VISIBLE rows
      const visibleRows = this.rows.filter(row => !row.classList.contains('d-none'));
      
      visibleRows.forEach(row => {
        const checkbox = row.querySelector('.table-row-select');
        const id = row.dataset.rowId;
        
        if (checkbox && id) {
          checkbox.checked = checked;
          if (checked) {
            this.selectedIds.add(id);
          } else {
            this.selectedIds.delete(id);
          }
        }
      });
      
      this.persistSelections();
      this.updateStats();
      this.updateBulkActionState();
    }
    
    toggleRowSelection(id, checked) {
      if (checked) {
        this.selectedIds.add(id);
      } else {
        this.selectedIds.delete(id);
      }
      
      this.persistSelections();
      this.updateStats();
      this.updateBulkActionState();
      this.updateSelectAllState();
    }
    
    updateSelectAllState() {
      if (!this.selectAllCheckbox) return;
      
      const visibleRows = this.rows.filter(row => !row.classList.contains('d-none'));
      const visibleChecked = visibleRows.filter(row => {
        const checkbox = row.querySelector('.table-row-select');
        return checkbox && checkbox.checked;
      });
      
      this.selectAllCheckbox.checked = visibleChecked.length === visibleRows.length && visibleRows.length > 0;
      this.selectAllCheckbox.indeterminate = visibleChecked.length > 0 && visibleChecked.length < visibleRows.length;
    }
    
    loadPersistedSelections() {
      try {
        const key = `table_selection_${this.tableId}`;
        const stored = sessionStorage.getItem(key);
        if (stored) {
          this.selectedIds = new Set(JSON.parse(stored));
          
          // Apply to checkboxes
          this.rows.forEach(row => {
            const checkbox = row.querySelector('.table-row-select');
            const id = row.dataset.rowId;
            if (checkbox && id && this.selectedIds.has(id)) {
              checkbox.checked = true;
            }
          });
          
          this.updateSelectAllState();
        }
      } catch (e) {
        console.warn('Failed to load persisted selections:', e);
      }
    }
    
    persistSelections() {
      try {
        const key = `table_selection_${this.tableId}`;
        sessionStorage.setItem(key, JSON.stringify(Array.from(this.selectedIds)));
      } catch (e) {
        console.warn('Failed to persist selections:', e);
      }
    }
    
    clearSelections() {
      this.selectedIds.clear();
      this.rows.forEach(row => {
        const checkbox = row.querySelector('.table-row-select');
        if (checkbox) checkbox.checked = false;
      });
      if (this.selectAllCheckbox) {
        this.selectAllCheckbox.checked = false;
        this.selectAllCheckbox.indeterminate = false;
      }
      this.persistSelections();
      this.updateStats();
      this.updateBulkActionState();
    }
    
    // === BULK ACTIONS ===
    
    bindBulkActions() {
      const bulkButtons = this.container.querySelectorAll('.table-bulk-action');
      
      bulkButtons.forEach(button => {
        button.addEventListener('click', (e) => {
          e.preventDefault();
          
          const action = button.dataset.action;
          const confirmMsg = button.dataset.confirm;
          
          if (confirmMsg && !confirm(confirmMsg)) {
            return;
          }
          
          this.executeBulkAction(action);
        });
      });
    }
    
    executeBulkAction(action) {
      const selectedIds = Array.from(this.selectedIds);
      
      // Dispatch custom event for handling by application code
      const event = new CustomEvent('tableview:bulkaction', {
        detail: {
          tableId: this.tableId,
          action: action,
          selectedIds: selectedIds
        },
        bubbles: true
      });
      
      this.container.dispatchEvent(event);
      
      // You can also implement direct form submission here
      console.log(`Bulk action "${action}" on`, selectedIds);
    }
    
    updateBulkActionState() {
      const buttons = this.container.querySelectorAll('.table-bulk-action');
      const hasSelection = this.selectedIds.size > 0;
      
      buttons.forEach(button => {
        button.disabled = !hasSelection;
      });
    }
    
    // === QUICK ACTIONS ===
    
    bindQuickActions() {
      const quickActions = this.table.querySelectorAll('.table-quick-action');
      
      quickActions.forEach(link => {
        link.addEventListener('click', (e) => {
          const confirmMsg = link.dataset.confirm;
          
          if (confirmMsg && !confirm(confirmMsg)) {
            e.preventDefault();
          }
        });
      });
    }
    
    // === ROW LINKS ===
    
    bindRowLinks() {
      this.rows.forEach(row => {
        const link = row.dataset.rowLink;
        if (!link) return;
        
        row.style.cursor = 'pointer';
        
        row.addEventListener('click', (e) => {
          // Don't trigger if clicking checkbox, button, or link
          if (e.target.closest('input, button, a')) return;
          
          window.location.href = link;
        });
      });
    }
    
    // === STATS & UI ===
    
    updateStats(visibleCount = null) {
      if (visibleCount === null) {
        visibleCount = this.rows.filter(row => !row.classList.contains('d-none')).length;
      }
      
      if (this.statVisible) {
        this.statVisible.textContent = visibleCount;
      }
      
      if (this.statTotal) {
        this.statTotal.textContent = this.rows.length;
      }
      
      if (this.statSelected) {
        this.statSelected.textContent = this.selectedIds.size;
      }
      
      if (this.statSelectedWrapper) {
        this.statSelectedWrapper.style.display = this.selectedIds.size > 0 ? 'inline' : 'none';
      }
    }
    
    toggleEmptyState(show) {
      const emptyRow = this.table.querySelector('.table-empty-state');
      if (emptyRow) {
        emptyRow.style.display = show ? '' : 'none';
      }
    }
  }
  
  // === PUBLIC API ===
  
  window.TableView = {
    init(tableId, options = {}) {
      if (instances.has(tableId)) {
        console.warn(`TableView: Table "${tableId}" already initialized`);
        return instances.get(tableId);
      }
      
      const instance = new TableViewController(tableId, options);
      instances.set(tableId, instance);
      return instance;
    },
    
    getInstance(tableId) {
      return instances.get(tableId);
    },
    
    destroy(tableId) {
      instances.delete(tableId);
    }
  };

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.table-view-container[data-table-id]').forEach(container => {
      const id = container.dataset.tableId;
      const opts = {
        selectable: container.dataset.selectable === 'true',
        serverSide: container.dataset.serverSide === 'true',
      };
      window.TableView.init(id, opts);
    });
  });

  document.addEventListener('tableview:bulkaction', (e) => {
    console.log('CAUGHT bulk event:', e.detail, 'target=', e.target);
  });
  
})();
