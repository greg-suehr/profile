batch_errors.html.twig
{% extends 'katzen/_dashboard_base.html.twig' %}

{% block title %}Import Errors - Batch #{{ batch.id }}{% endblock %}

{% block main_content %}
<div class="import-batch-errors">
  
  {# Breadcrumb Header #}
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="{{ path('import_dashboard') }}">Import</a>
      </li>
      <li class="breadcrumb-item">
        <a href="{{ path('import_batch_show', {id: batch.id}) }}">Batch #{{ batch.id }}</a>
      </li>
      <li class="breadcrumb-item active" aria-current="page">Errors</li>
    </ol>
  </nav>

  {# Page Header #}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-1">
        <i class="bi bi-exclamation-triangle text-warning me-2"></i>
        Import Errors
      </h2>
      <p class="text-muted mb-0">
        {{ total_errors|number_format }} errors from 
        <strong>{{ batch.name ?: 'Batch #' ~ batch.id }}</strong>
      </p>
    </div>
    <div class="btn-group">
      <a href="{{ path('import_batch_show', {id: batch.id}) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>
        Back to Batch
      </a>
      <a href="#" class="btn btn-outline-primary" id="export-errors-btn">
        <i class="bi bi-download me-1"></i>
        Export CSV
      </a>
    </div>
  </div>

  {# Error Type Filter Cards #}
  {% if summary is not empty %}
  <div class="row g-3 mb-4">
    <div class="col-auto">
      <a href="{{ path('import_batch_errors', {id: batch.id}) }}" 
         class="card text-decoration-none {{ not app.request.query.get('type') ? 'border-primary' : '' }}"
         style="min-width: 100px;">
        <div class="card-body py-2 px-3 text-center">
          <div class="h5 mb-0">{{ total_errors }}</div>
          <small class="text-muted">All</small>
        </div>
      </a>
    </div>
    {% for type, count in summary %}
    <div class="col-auto">
      <a href="{{ path('import_batch_errors', {id: batch.id, type: type}) }}" 
         class="card text-decoration-none {{ app.request.query.get('type') == type ? 'border-primary' : '' }}"
         style="min-width: 100px;">
        <div class="card-body py-2 px-3 text-center">
          <div class="h5 mb-0 text-{{ type == 'validation' ? 'warning' : (type == 'entity_creation' ? 'danger' : 'info') }}">
            {{ count }}
          </div>
          <small class="text-muted text-capitalize">{{ type|replace({'_': ' '}) }}</small>
        </div>
      </a>
    </div>
    {% endfor %}
  </div>
  {% endif %}

  {# Errors Table #}
  <div class="card">
    <div class="card-body p-0">
      {% include 'katzen/component/_table_view.html.twig' with { table: table } %}
    </div>
    
    {# Pagination #}
    {% if total_pages > 1 %}
    <div class="card-footer bg-transparent">
      <nav aria-label="Error pagination">
        <ul class="pagination pagination-sm justify-content-center mb-0">
          
          {# Previous #}
          <li class="page-item {{ current_page == 1 ? 'disabled' : '' }}">
            <a class="page-link" 
               href="{{ path('import_batch_errors', {id: batch.id, page: current_page - 1}) }}"
               {% if current_page == 1 %}tabindex="-1" aria-disabled="true"{% endif %}>
              <i class="bi bi-chevron-left"></i>
            </a>
          </li>
          
          {# Page Numbers #}
          {% set start_page = max(1, current_page - 2) %}
          {% set end_page = min(total_pages, current_page + 2) %}
          
          {% if start_page > 1 %}
          <li class="page-item">
            <a class="page-link" href="{{ path('import_batch_errors', {id: batch.id, page: 1}) }}">1</a>
          </li>
          {% if start_page > 2 %}
          <li class="page-item disabled"><span class="page-link">...</span></li>
          {% endif %}
          {% endif %}
          
          {% for page in start_page..end_page %}
          <li class="page-item {{ page == current_page ? 'active' : '' }}">
            <a class="page-link" href="{{ path('import_batch_errors', {id: batch.id, page: page}) }}">{{ page }}</a>
          </li>
          {% endfor %}
          
          {% if end_page < total_pages %}
          {% if end_page < total_pages - 1 %}
          <li class="page-item disabled"><span class="page-link">...</span></li>
          {% endif %}
          <li class="page-item">
            <a class="page-link" href="{{ path('import_batch_errors', {id: batch.id, page: total_pages}) }}">{{ total_pages }}</a>
          </li>
          {% endif %}
          
          {# Next #}
          <li class="page-item {{ current_page == total_pages ? 'disabled' : '' }}">
            <a class="page-link" 
               href="{{ path('import_batch_errors', {id: batch.id, page: current_page + 1}) }}"
               {% if current_page == total_pages %}tabindex="-1" aria-disabled="true"{% endif %}>
              <i class="bi bi-chevron-right"></i>
            </a>
          </li>
          
        </ul>
      </nav>
      <div class="text-center text-muted small mt-2">
        Showing page {{ current_page }} of {{ total_pages }} ({{ total_errors|number_format }} total errors)
      </div>
    </div>
    {% endif %}
  </div>

  {# Quick Tips #}
  <div class="card mt-4 bg-light border-0">
    <div class="card-body">
      <h6 class="card-title">
        <i class="bi bi-lightbulb me-2"></i>
        Tips for Resolving Errors
      </h6>
      <ul class="mb-0 small">
        <li><strong>Validation errors</strong> usually mean the data doesn't match expected formats (dates, numbers, required fields)</li>
        <li><strong>Transformation errors</strong> occur when data can't be converted (e.g., "N/A" in a numeric field)</li>
        <li><strong>Entity creation errors</strong> indicate issues creating records (duplicates, missing references)</li>
        <li>Export the error list as CSV and fix issues in your source file before re-importing</li>
      </ul>
    </div>
  </div>

</div>
{% endblock %}
batch_show.html.twig
{% extends 'katzen/_dashboard_base.html.twig' %}

{% block title %}Import Batch #{{ batch.id }}{% endblock %}

{% block main_content %}
<div class="import-batch-detail">
  
  {# ShowPage Component for Header & Summary #}
  {% include 'katzen/component/_show_page.html.twig' with { page: page } %}

  {# Progress Bar (for processing batches) #}
  {% if batch.status == 'processing' %}
  <div class="card mb-4" id="progress-card" data-batch-id="{{ batch.id }}">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="fw-semibold">
          <span class="spinner-border spinner-border-sm me-2" role="status"></span>
          Processing...
        </span>
        <span id="progress-text">{{ batch.progressPercent }}%</span>
      </div>
      <div class="progress" style="height: 20px;">
        <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" 
             role="progressbar" 
             id="progress-bar"
             style="width: {{ batch.progressPercent }}%" 
             aria-valuenow="{{ batch.progressPercent }}" 
             aria-valuemin="0" 
             aria-valuemax="100">
        </div>
      </div>
      <div class="d-flex justify-content-between mt-2 text-muted small">
        <span id="progress-rows">{{ batch.processedRows|number_format }} / {{ batch.totalRows|number_format }} rows</span>
        <span id="progress-errors">{{ batch.failedRows|number_format }} errors</span>
      </div>
    </div>
  </div>
  {% endif %}

  {# Entity Counts (if completed) #}
  {% if batch.entityCounts is not empty %}
  <div class="card mb-4">
    <div class="card-header bg-transparent">
      <h5 class="mb-0">
        <i class="bi bi-box me-2"></i>
        Created Entities
      </h5>
    </div>
    <div class="card-body">
      <div class="row g-3">
        {% for entity_type, count in batch.entityCounts %}
        <div class="col-6 col-md-3">
          <div class="border rounded p-3 text-center">
            <div class="h4 mb-1">{{ count|number_format }}</div>
            <div class="text-muted small text-capitalize">{{ entity_type }}</div>
          </div>
        </div>
        {% endfor %}
      </div>
    </div>
  </div>
  {% endif %}

  {# Error Analysis (if there are errors) #}
  {% if batch.failedRows > 0 %}
  <div class="row g-4 mb-4">
    
    {# Error Summary by Type #}
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-transparent">
          <h5 class="mb-0">
            <i class="bi bi-exclamation-triangle text-warning me-2"></i>
            Errors by Type
          </h5>
        </div>
        <div class="card-body">
          {% if error_summary.by_type is not empty %}
          <div class="d-flex flex-column gap-2">
            {% for type, count in error_summary.by_type %}
            <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
              <span class="text-capitalize">
                {% if type == 'validation' %}
                <i class="bi bi-shield-exclamation text-warning me-2"></i>
                {% elseif type == 'transformation' %}
                <i class="bi bi-arrow-left-right text-info me-2"></i>
                {% elseif type == 'entity_creation' %}
                <i class="bi bi-database-exclamation text-danger me-2"></i>
                {% else %}
                <i class="bi bi-bug text-secondary me-2"></i>
                {% endif %}
                {{ type|replace({'_': ' '}) }}
              </span>
              <span class="badge bg-danger">{{ count }}</span>
            </div>
            {% endfor %}
          </div>
          {% else %}
          <p class="text-muted mb-0">No error data available</p>
          {% endif %}
        </div>
      </div>
    </div>

    {# Error Summary by Field #}
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-transparent">
          <h5 class="mb-0">
            <i class="bi bi-input-cursor-text text-danger me-2"></i>
            Errors by Field
          </h5>
        </div>
        <div class="card-body">
          {% if error_summary.by_field is not empty %}
          <div class="d-flex flex-column gap-2">
            {% for field, count in error_summary.by_field|slice(0, 8) %}
            <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
              <code class="text-body">{{ field }}</code>
              <span class="badge bg-warning text-dark">{{ count }}</span>
            </div>
            {% endfor %}
            {% if error_summary.by_field|length > 8 %}
            <a href="{{ path('import_batch_errors', {id: batch.id}) }}" class="text-muted small">
              + {{ error_summary.by_field|length - 8 }} more fields
            </a>
            {% endif %}
          </div>
          {% else %}
          <p class="text-muted mb-0">No field-specific errors</p>
          {% endif %}
        </div>
      </div>
    </div>
    
  </div>

  {# Common Error Messages #}
  <div class="card mb-4">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
      <h5 class="mb-0">
        <i class="bi bi-chat-quote text-secondary me-2"></i>
        Most Common Errors
      </h5>
      <a href="{{ path('import_batch_errors', {id: batch.id}) }}" class="btn btn-sm btn-outline-warning">
        View All {{ error_summary.total }} Errors
      </a>
    </div>
    <div class="card-body">
      {% if unique_errors is not empty %}
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th>Error Message</th>
              <th class="text-center" style="width: 100px;">Count</th>
              <th class="text-end" style="width: 100px;">First Row</th>
            </tr>
          </thead>
          <tbody>
            {% for error in unique_errors %}
            <tr>
              <td>
                <span class="text-danger">{{ error.message|length > 100 ? error.message|slice(0, 100) ~ '...' : error.message }}</span>
              </td>
              <td class="text-center">
                <span class="badge bg-danger">{{ error.count }}</span>
              </td>
              <td class="text-end text-muted">Row {{ error.first_row }}</td>
            </tr>
            {% endfor %}
          </tbody>
        </table>
      </div>
      {% else %}
      <p class="text-muted mb-0">Error details not available</p>
      {% endif %}
    </div>
  </div>

  {# Problematic Rows #}
  {% if problematic_rows is not empty %}
  <div class="card mb-4">
    <div class="card-header bg-transparent">
      <h5 class="mb-0">
        <i class="bi bi-exclamation-octagon text-danger me-2"></i>
        Most Problematic Rows
      </h5>
    </div>
    <div class="card-body">
      <div class="d-flex flex-wrap gap-2">
        {% for row in problematic_rows %}
        <span class="badge bg-light text-dark border px-3 py-2">
          Row {{ row.row_number }}
          <span class="badge bg-danger ms-1">{{ row.error_count }} errors</span>
        </span>
        {% endfor %}
      </div>
    </div>
  </div>
  {% endif %}

  {% endif %}

  {# Mapping Details #}
  {% if batch.mapping %}
  <div class="card mb-4">
    <div class="card-header bg-transparent">
      <h5 class="mb-0">
        <i class="bi bi-diagram-3 me-2"></i>
        Mapping Used
      </h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <dl class="row mb-0">
            <dt class="col-sm-4">Mapping Name</dt>
            <dd class="col-sm-8">
              <a href="{{ path('import_mapping_show', {id: batch.mapping.id}) }}">
                {{ batch.mapping.name }}
              </a>
            </dd>
            <dt class="col-sm-4">Entity Type</dt>
            <dd class="col-sm-8">
              <span class="badge bg-primary">{{ batch.mapping.entityType }}</span>
            </dd>
            <dt class="col-sm-4">Fields Mapped</dt>
            <dd class="col-sm-8">{{ batch.mapping.fieldMappings|length }}</dd>
          </dl>
        </div>
        <div class="col-md-6">
          <h6 class="text-muted mb-2">Field Mappings</h6>
          <div class="bg-light rounded p-2" style="max-height: 150px; overflow-y: auto;">
            <table class="table table-sm table-borderless mb-0" style="font-size: 0.85rem;">
              {% for source, target in batch.mapping.fieldMappings|slice(0, 10) %}
              <tr>
                <td class="text-muted">{{ source }}</td>
                <td><i class="bi bi-arrow-right text-muted"></i></td>
                <td><code>{{ target }}</code></td>
              </tr>
              {% endfor %}
              {% if batch.mapping.fieldMappings|length > 10 %}
              <tr>
                <td colspan="3" class="text-muted">
                  + {{ batch.mapping.fieldMappings|length - 10 }} more...
                </td>
              </tr>
              {% endif %}
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
  {% endif %}

  {# Source File Info #}
  {% if batch.sourceFile %}
  <div class="card">
    <div class="card-header bg-transparent">
      <h5 class="mb-0">
        <i class="bi bi-file-earmark me-2"></i>
        Source File
      </h5>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Filename</dt>
        <dd class="col-sm-9"><code>{{ batch.sourceFile }}</code></dd>
        {% if batch.sourceFilePath %}
        <dt class="col-sm-3">Path</dt>
        <dd class="col-sm-9"><code class="text-muted small">{{ batch.sourceFilePath }}</code></dd>
        {% endif %}
      </dl>
    </div>
  </div>
  {% endif %}

</div>

{% if batch.status == 'processing' %}
<script>
document.addEventListener('DOMContentLoaded', function() {
  const batchId = {{ batch.id }};
  const progressBar = document.getElementById('progress-bar');
  const progressText = document.getElementById('progress-text');
  const progressRows = document.getElementById('progress-rows');
  const progressErrors = document.getElementById('progress-errors');
  const progressCard = document.getElementById('progress-card');
  
  function updateProgress() {
    fetch('{{ path('import_batch_progress', {id: batch.id}) }}')
      .then(response => response.json())
      .then(data => {
        progressBar.style.width = data.progress_percent + '%';
        progressBar.setAttribute('aria-valuenow', data.progress_percent);
        progressText.textContent = data.progress_percent + '%';
        progressRows.textContent = data.processed_rows.toLocaleString() + ' / ' + data.total_rows.toLocaleString() + ' rows';
        progressErrors.textContent = data.failed_rows.toLocaleString() + ' errors';
        
        if (data.is_complete) {
          // Reload page to show final state
          window.location.reload();
        } else {
          // Continue polling
          setTimeout(updateProgress, 2000);
        }
      })
      .catch(err => {
        console.error('Failed to fetch progress:', err);
        setTimeout(updateProgress, 5000);
      });
  }
  
  // Start polling
  setTimeout(updateProgress, 2000);
});
</script>
{% endif %}
{% endblock %}
configure_mapping.html.twig
{% extends 'katzen/_dashboard_base.html.twig' %}

{% block title %}Configure Field Mapping{% endblock %}

{% block main_content %}
<div class="import-configure-mapping">
  
  {# Header Section #}
  <div class="mb-4">
    <div class="d-flex align-items-center mb-2">
      <a href="{{ path('import_upload') }}" class="btn btn-sm btn-outline-secondary me-3">
        <i class="bi bi-arrow-left"></i>
      </a>
      <div>
        <h2 class="mb-1">
          <i class="bi bi-diagram-3 me-2"></i>
          Configure Field Mapping
        </h2>
        <p class="text-muted mb-0">Map your CSV columns to {{ entity_type|title }} fields</p>
      </div>
    </div>
  </div>

  {# Wizard Steps Progress #}
  <div class="card mb-4">
    <div class="card-body">
      <div class="row text-center">
        <div class="col-3">
          <div class="wizard-step completed">
            <div class="wizard-step-icon bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
              <i class="bi bi-check-lg"></i>
            </div>
            <div class="fw-semibold text-success">Upload</div>
            <div class="small text-muted">Complete</div>
          </div>
        </div>
        <div class="col-3">
          <div class="wizard-step active">
            <div class="wizard-step-icon bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
              <i class="bi bi-diagram-3"></i>
            </div>
            <div class="fw-semibold">Map Fields</div>
            <div class="small text-muted">Configure mapping</div>
          </div>
        </div>
        <div class="col-3">
          <div class="wizard-step">
            <div class="wizard-step-icon bg-light text-muted rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
              <i class="bi bi-shield-check"></i>
            </div>
            <div class="text-muted">Validate</div>
            <div class="small text-muted">Preview & check</div>
          </div>
        </div>
        <div class="col-3">
          <div class="wizard-step">
            <div class="wizard-step-icon bg-light text-muted rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
              <i class="bi bi-play-fill"></i>
            </div>
            <div class="text-muted">Import</div>
            <div class="small text-muted">Execute</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {# AI Suggestions Banner #}
  {% if suggested is not empty %}
  <div class="alert alert-success border-success d-flex align-items-start mb-4">
    <i class="bi bi-lightbulb-fill me-3 mt-1" style="font-size: 1.25rem;"></i>
    <div>
      <h6 class="alert-heading mb-1">Smart Mapping Applied</h6>
      <p class="mb-0 small">
        Katzen has automatically suggested field mappings based on your column headers and past import patterns. 
        Review the suggestions below and adjust as needed.
      </p>
    </div>
  </div>
  {% endif %}

  {{ form_start(form, {'attr': {'class': 'import-mapping-form'}}) }}
  {{ form_errors(form) }}

  {# Main Mapping Configuration #}
  <div class="row">
    <div class="col-lg-10 mx-auto">
      
      {# Field Mappings Card #}
      <div class="card mb-4">
        <div class="card-header bg-transparent">
          <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
              <i class="bi bi-arrow-left-right me-2"></i>
              Field Mappings
            </h5>
            <div>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-all-btn">
                <i class="bi bi-x-circle me-1"></i>
                Clear All
              </button>
              <button type="button" class="btn btn-sm btn-outline-primary" id="auto-map-btn">
                <i class="bi bi-magic me-1"></i>
                Auto-Map
              </button>
            </div>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0 mapping-table">
              <thead class="table-light sticky-top">
                <tr>
                  <th style="width: 5%;" class="text-center">#</th>
                  <th style="width: 35%;">CSV Column</th>
                  <th style="width: 10%;" class="text-center"></th>
                  <th style="width: 50%;">Target Field</th>
                </tr>
              </thead>
              <tbody>
                {% for i, header in headers %}
                {% set mapping_field = 'mapping_' ~ i %}
                {% set is_suggested = suggested[header] is defined %}
                <tr class="mapping-row {% if is_suggested %}table-success{% endif %}">
                  <td class="text-center text-muted">{{ i + 1 }}</td>
                  <td>
                    <div class="d-flex align-items-center">
                      <code class="bg-light px-2 py-1 rounded">{{ header }}</code>
                      {% if is_suggested %}
                      <span class="badge bg-success-subtle text-success ms-2" title="AI suggested mapping">
                        <i class="bi bi-lightbulb-fill"></i>
                      </span>
                      {% endif %}
                    </div>
                  </td>
                  <td class="text-center text-muted">
                    <i class="bi bi-arrow-right"></i>
                  </td>
                  <td>
                    {{ form_widget(form[mapping_field], {
                      'attr': {
                        'class': 'form-select mapping-select',
                        'data-row': i
                      }
                    }) }}
                    {{ form_errors(form[mapping_field]) }}
                  </td>
                </tr>
                {% endfor %}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {# Import Options #}
      <div class="card mb-4">
        <div class="card-header bg-transparent">
          <h5 class="mb-0">
            <i class="bi bi-sliders me-2"></i>
            Import Options
          </h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-check form-switch">
                {{ form_widget(form.skip_duplicates, {
                  'attr': {'class': 'form-check-input'}
                }) }}
                {{ form_label(form.skip_duplicates, null, {
                  'label_attr': {'class': 'form-check-label'}
                }) }}
              </div>
              <small class="text-muted d-block mt-1">
                {{ form.skip_duplicates.vars.help }}
              </small>
            </div>
            <div class="col-md-6">
              <div class="form-check form-switch">
                {{ form_widget(form.update_existing, {
                  'attr': {'class': 'form-check-input'}
                }) }}
                {{ form_label(form.update_existing, null, {
                  'label_attr': {'class': 'form-check-label'}
                }) }}
              </div>
              <small class="text-muted d-block mt-1">
                {{ form.update_existing.vars.help }}
              </small>
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                {{ form_widget(form.create_missing_references, {
                  'attr': {'class': 'form-check-input'}
                }) }}
                {{ form_label(form.create_missing_references, null, {
                  'label_attr': {'class': 'form-check-label'}
                }) }}
              </div>
              <small class="text-muted d-block mt-1">
                {{ form.create_missing_references.vars.help }}
              </small>
            </div>
          </div>
        </div>
      </div>

      {# Default Values Section #}
      <div class="card mb-4">
        <div class="card-header bg-transparent">
          <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
              <i class="bi bi-card-text me-2"></i>
              Default Values
            </h5>
            <button type="button" class="btn btn-sm btn-outline-primary" id="add-default-value">
              <i class="bi bi-plus-lg me-1"></i>
              Add Default
            </button>
          </div>
        </div>
        <div class="card-body">
          <p class="text-muted small mb-3">
            Set default values for fields that aren't included in your CSV file.
          </p>
          <div id="default-values-container">
            {{ form_row(form.default_values) }}
          </div>
        </div>
      </div>

      {# Save Template Options #}
      <div class="card mb-4">
        <div class="card-header bg-transparent">
          <h5 class="mb-0">
            <i class="bi bi-bookmark me-2"></i>
            Save as Template
          </h5>
        </div>
        <div class="card-body">
          <div class="form-check form-switch mb-3">
            {{ form_widget(form.save_as_template, {
              'attr': {'class': 'form-check-input', 'id': 'save-template-toggle'}
            }) }}
            {{ form_label(form.save_as_template, null, {
              'label_attr': {'class': 'form-check-label'}
            }) }}
          </div>
          
          <div id="template-options" class="d-none">
            <div class="mb-3">
              {{ form_row(form.mapping_name, {
                'label_attr': {'class': 'form-label fw-semibold'}
              }) }}
            </div>
            
            <div class="form-check">
              {{ form_widget(form.is_system_template, {
                'attr': {'class': 'form-check-input'}
              }) }}
              {{ form_label(form.is_system_template, null, {
                'label_attr': {'class': 'form-check-label'}
              }) }}
              <small class="text-muted d-block mt-1">
                {{ form.is_system_template.vars.help }}
              </small>
            </div>
          </div>
        </div>
      </div>

      {# Action Buttons #}
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <a href="{{ path('import_upload') }}" class="btn btn-outline-secondary">
              <i class="bi bi-arrow-left me-1"></i>
              Back
            </a>
            <div>
              <span class="text-muted me-3 small" id="mapping-count">
                <span class="fw-semibold" id="mapped-count">0</span> of 
                <span id="total-count">{{ headers|length }}</span> fields mapped
              </span>
              {{ form_widget(form.submit) }}
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  {{ form_widget(form._token) }}
  {{ form_end(form, {'render_rest': false}) }}

</div>

<style>
  .wizard-step {
    position: relative;
  }
  
  .wizard-step.active .wizard-step-icon {
    box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.25);
  }
  
  .wizard-step.completed .wizard-step-icon {
    box-shadow: 0 0 0 4px rgba(25, 135, 84, 0.25);
  }

  .mapping-table thead {
    background: white;
    z-index: 10;
  }

  .mapping-row.table-success {
    background-color: rgba(25, 135, 84, 0.05) !important;
  }

  .mapping-select {
    font-size: 0.9rem;
  }

  .mapping-select option[value=""] {
    font-style: italic;
    color: #6c757d;
  }

  .import-configure-mapping .card {
    border-radius: 0.5rem;
    border-color: rgba(0,0,0,0.1);
  }
  
  .import-configure-mapping .card-header {
    border-bottom-color: rgba(0,0,0,0.05);
  }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  
  function updateMappingCount() {
    const selects = document.querySelectorAll('.mapping-select');
    const mappedCount = Array.from(selects).filter(s => s.value !== '').length;
    const totalCount = selects.length;
    
    document.getElementById('mapped-count').textContent = mappedCount;
    document.getElementById('total-count').textContent = totalCount;
  }
  
  updateMappingCount();
  
  document.querySelectorAll('.mapping-select').forEach(select => {
    select.addEventListener('change', updateMappingCount);
  });
  
  document.getElementById('clear-all-btn')?.addEventListener('click', function() {
    if (confirm('Are you sure you want to clear all field mappings?')) {
      document.querySelectorAll('.mapping-select').forEach(select => {
        select.value = '';
      });
      updateMappingCount();
    }
  });
  
  document.getElementById('auto-map-btn')?.addEventListener('click', function() {
    document.querySelectorAll('.mapping-select').forEach(select => {
      const suggested = select.getAttribute('data-suggested');
      if (suggested && suggested !== 'false') {
        const options = Array.from(select.options);
        const suggestedOption = options.find(opt => opt.selected);
        if (suggestedOption) {
          select.value = suggestedOption.value;
        }
      }
    });
    updateMappingCount();
  });
  
  const saveTemplateToggle = document.getElementById('save-template-toggle');
  const templateOptions = document.getElementById('template-options');
  
  if (saveTemplateToggle && templateOptions) {
    saveTemplateToggle.addEventListener('change', function() {
      if (this.checked) {
        templateOptions.classList.remove('d-none');
      } else {
        templateOptions.classList.add('d-none');
      }
    });
    
    if (saveTemplateToggle.checked) {
      templateOptions.classList.remove('d-none');
    }
  }
  
  document.querySelectorAll('.mapping-select').forEach(select => {
    const originalValue = select.value;
    select.addEventListener('change', function() {
      const row = this.closest('tr');
      if (this.value !== originalValue) {
        row.classList.add('table-warning');
      } else {
        row.classList.remove('table-warning');
      }
    });
  });
  
  const form = document.querySelector('.import-mapping-form');
  if (form) {
    form.addEventListener('submit', function(e) {
      const unmappedCount = Array.from(document.querySelectorAll('.mapping-select'))
        .filter(s => s.value === '').length;
      
      if (unmappedCount > 0) {
        const confirmed = confirm(
          `Warning: ${unmappedCount} field(s) are not mapped and will be skipped during import. Continue anyway?`
        );
        if (!confirmed) {
          e.preventDefault();
        }
      }
    });
  }
});
</script>
{% endblock %}
dashboard.html.twig
{% extends 'katzen/_dashboard_base.html.twig' %}

{% block title %}Import Dashboard{% endblock %}

{% block main_content %}
<div class="import-dashboard">
  
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-1">
        <i class="bi bi-cloud-upload me-2"></i>
        Data Import
      </h2>
      <p class="text-muted mb-0">Manage imports, mappings, and view import history</p>
    </div>
    <div class="btn-group">
      <a href="{{ path('import_upload') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>
        New Import
      </a>
      <a href="{{ path('import_mappings') }}" class="btn btn-outline-secondary">
        <i class="bi bi-gear me-1"></i>
        Mappings
      </a>
    </div>
  </div>

  {% if stats.active_count > 0 %}
  <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
    <div class="spinner-border spinner-border-sm me-3" role="status">
      <span class="visually-hidden">Processing...</span>
    </div>
    <div class="flex-grow-1">
      <strong>{{ stats.active_count }} import{{ stats.active_count > 1 ? 's' : '' }} in progress</strong>
      {% for batch in active_batches %}
        <span class="badge bg-info ms-2">{{ batch.name ?: 'Batch #' ~ batch.id }}</span>
      {% endfor %}
    </div>
    <a href="{{ path('import_batches', {status: 'processing'}) }}" class="btn btn-sm btn-outline-info">
      View Active
    </a>
  </div>
  {% endif %}

  <div class="row g-3 mb-4">
    
    <div class="col-6 col-lg-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="text-muted text-uppercase mb-2" style="font-size: 0.75rem;">Total Imports</h6>
              <h3 class="mb-0">{{ stats.total_batches }}</h3>
            </div>
            <div class="bg-primary bg-opacity-10 rounded-3 p-2">
              <i class="bi bi-archive text-primary" style="font-size: 1.5rem;"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="text-muted text-uppercase mb-2" style="font-size: 0.75rem;">Records Imported</h6>
              <h3 class="mb-0">{{ stats.total_rows }}</h3>
            </div>
            <div class="bg-success bg-opacity-10 rounded-3 p-2">
              <i class="bi bi-database text-success" style="font-size: 1.5rem;"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="text-muted text-uppercase mb-2" style="font-size: 0.75rem;">Success Rate</h6>
              <h3 class="mb-0 {{ stats.success_rate >= 95 ? 'text-success' : (stats.success_rate >= 80 ? 'text-warning' : 'text-danger') }}">
                {{ stats.success_rate }}%
              </h3>
            </div>
            <div class="bg-{{ stats.success_rate >= 95 ? 'success' : (stats.success_rate >= 80 ? 'warning' : 'danger') }} bg-opacity-10 rounded-3 p-2">
              <i class="bi bi-{{ stats.success_rate >= 95 ? 'check-circle' : (stats.success_rate >= 80 ? 'exclamation-triangle' : 'x-circle') }} text-{{ stats.success_rate >= 95 ? 'success' : (stats.success_rate >= 80 ? 'warning' : 'danger') }}" style="font-size: 1.5rem;"></i>
            </div>
          </div>
          <div class="progress mt-2" style="height: 4px;">
            <div class="progress-bar bg-{{ stats.success_rate >= 95 ? 'success' : (stats.success_rate >= 80 ? 'warning' : 'danger') }}" 
                 role="progressbar" 
                 style="width: {{ stats.success_rate }}%" 
                 aria-valuenow="{{ stats.success_rate }}" 
                 aria-valuemin="0" 
                 aria-valuemax="100"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="card h-100 {{ stats.failed_rows|replace({',': ''})|number_format > 0 ? 'border-danger' : '' }}">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="text-muted text-uppercase mb-2" style="font-size: 0.75rem;">Failed Records</h6>
              <h3 class="mb-0 {{ stats.failed_rows != '0' ? 'text-danger' : '' }}">{{ stats.failed_rows }}</h3>
            </div>
            <div class="bg-danger bg-opacity-10 rounded-3 p-2">
              <i class="bi bi-exclamation-triangle text-danger" style="font-size: 1.5rem;"></i>
            </div>
          </div>
        </div>
      </div>
    </div>
    
  </div>

  <div class="row g-4 mb-4">
    
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-transparent">
          <h5 class="mb-0">
            <i class="bi bi-pie-chart me-2"></i>
            Import Status Breakdown
          </h5>
        </div>
        <div class="card-body">
          <div class="d-flex flex-column gap-2">
            
            {% set status_config = {
              'completed': { 'label': 'Completed', 'color': 'success', 'icon': 'check-circle' },
              'processing': { 'label': 'Processing', 'color': 'info', 'icon': 'arrow-repeat' },
              'pending': { 'label': 'Pending', 'color': 'secondary', 'icon': 'clock' },
              'failed': { 'label': 'Failed', 'color': 'danger', 'icon': 'x-circle' },
              'rolled_back': { 'label': 'Rolled Back', 'color': 'warning', 'icon': 'arrow-counterclockwise' }
            } %}
            
            {% for status, count in status_counts %}
            {% set config = status_config[status] ?? { 'label': status|capitalize, 'color': 'secondary', 'icon': 'circle' } %}
            <a href="{{ path('import_batches', {status: status}) }}" 
               class="d-flex justify-content-between align-items-center p-2 rounded text-decoration-none text-body hover-bg-light">
              <span>
                <i class="bi bi-{{ config.icon }} text-{{ config.color }} me-2"></i>
                {{ config.label }}
              </span>
              <span class="badge bg-{{ config.color }}">{{ count }}</span>
            </a>
            {% else %}
            <p class="text-muted mb-0">No imports yet</p>
            {% endfor %}
            
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
          <h5 class="mb-0">
            <i class="bi bi-diagram-3 me-2"></i>
            Configured Mappings
          </h5>
          <a href="{{ path('import_mappings') }}" class="btn btn-sm btn-outline-primary">
            Manage
          </a>
        </div>
        <div class="card-body">
          {% if mapping_counts|length > 0 %}
          <div class="d-flex flex-column gap-2">
            
            {% set entity_config = {
              'order': { 'label': 'Orders', 'color': 'primary', 'icon': 'receipt' },
              'item': { 'label': 'Items', 'color': 'success', 'icon': 'box' },
              'sellable': { 'label': 'Sellables', 'color': 'info', 'icon': 'tag' },
              'customer': { 'label': 'Customers', 'color': 'warning', 'icon': 'people' },
              'vendor': { 'label': 'Vendors', 'color': 'secondary', 'icon': 'truck' }
            } %}
            
            {% for entity_type, count in mapping_counts %}
            {% set config = entity_config[entity_type] ?? { 'label': entity_type|capitalize, 'color': 'secondary', 'icon': 'file-earmark' } %}
            <a href="{{ path('import_mappings', {type: entity_type}) }}" 
               class="d-flex justify-content-between align-items-center p-2 rounded text-decoration-none text-body hover-bg-light">
              <span>
                <i class="bi bi-{{ config.icon }} text-{{ config.color }} me-2"></i>
                {{ config.label }}
              </span>
              <span class="badge bg-{{ config.color }}">{{ count }} mapping{{ count != 1 ? 's' : '' }}</span>
            </a>
            {% endfor %}
            
          </div>
          {% else %}
          <div class="text-center py-4">
            <i class="bi bi-gear text-muted" style="font-size: 2rem;"></i>
            <p class="text-muted mt-2 mb-3">No mappings configured yet</p>
            <a href="{{ path('import_upload') }}" class="btn btn-sm btn-primary">
              Create Your First Import
            </a>
          </div>
          {% endif %}
        </div>
      </div>
    </div>
    
  </div>

  <div class="card">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
      <h5 class="mb-0">
        <i class="bi bi-clock-history me-2"></i>
        Recent Imports
      </h5>
      <a href="{{ path('import_batches') }}" class="btn btn-sm btn-outline-secondary">
        View All
      </a>
    </div>
    <div class="card-body p-0">
      {% include 'katzen/component/_table_view.html.twig' with { table: table } %}
    </div>
  </div>

</div>

<style>
  .hover-bg-light:hover {
    background-color: rgba(0,0,0,0.03);
  }
  
  .import-dashboard .card {
    border-radius: 0.5rem;
    border-color: rgba(0,0,0,0.1);
  }
  
  .import-dashboard .card-header {
    border-bottom-color: rgba(0,0,0,0.05);
  }
</style>
{% endblock %}
learning.html.twig
{% extends 'katzen/_dashboard_base.html.twig' %}

{% block title %}Import Learning Stats{% endblock %}

{% block main_content %}
<div class="import-learning">
  
  {# Page Header #}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-1">
        <i class="bi bi-graph-up me-2"></i>
        Mapping Intelligence
      </h2>
      <p class="text-muted mb-0">
        The import system learns from your corrections to improve future suggestions
      </p>
    </div>
    <a href="{{ path('import_mappings') }}" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>
      Back to Mappings
    </a>
  </div>

  {# Stats by Entity Type #}
  <div class="row g-4 mb-4">
    {% for entity_type, entity_stats in stats %}
    {% set colors = {
      'order': 'primary',
      'item': 'success', 
      'sellable': 'info',
      'customer': 'warning',
      'vendor': 'secondary'
    } %}
    {% set color = colors[entity_type] ?? 'secondary' %}
    
    <div class="col-md-4 col-lg-3">
      <div class="card h-100 border-{{ color }}">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <h5 class="card-title text-capitalize mb-3">{{ entity_type }}</h5>
            <span class="badge bg-{{ color }}">{{ entity_stats.total }}</span>
          </div>
          <dl class="row mb-0 small">
            <dt class="col-7">Unique Columns</dt>
            <dd class="col-5 text-end">{{ entity_stats.unique_columns }}</dd>
            <dt class="col-7">Avg Confidence</dt>
            <dd class="col-5 text-end">{{ entity_stats.avg_success }}x</dd>
          </dl>
        </div>
      </div>
    </div>
    {% else %}
    <div class="col-12">
      <div class="card bg-light border-0">
        <div class="card-body text-center py-5">
          <i class="bi bi-lightbulb text-muted" style="font-size: 3rem;"></i>
          <h5 class="mt-3">No Learning Data Yet</h5>
          <p class="text-muted mb-0">
            As you import data and confirm or correct column mappings, 
            the system will learn and improve its suggestions.
          </p>
        </div>
      </div>
    </div>
    {% endfor %}
  </div>

  {# Top Learned Mappings #}
  {% if top_mappings is not empty %}
  <div class="card mb-4">
    <div class="card-header bg-transparent">
      <h5 class="mb-0">
        <i class="bi bi-trophy me-2 text-warning"></i>
        Top Learned Mappings
      </h5>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Column Name</th>
              <th>Maps To</th>
              <th>Entity Type</th>
              <th class="text-end">Times Confirmed</th>
            </tr>
          </thead>
          <tbody>
            {% for mapping in top_mappings %}
            <tr>
              <td>
                <code class="bg-light px-2 py-1 rounded">{{ mapping.column_name }}</code>
              </td>
              <td>
                <code class="text-primary">{{ mapping.target_field }}</code>
              </td>
              <td>
                <span class="badge bg-secondary">{{ mapping.entity_type }}</span>
              </td>
              <td class="text-end">
                <span class="badge bg-success">{{ mapping.success_count }}×</span>
              </td>
            </tr>
            {% endfor %}
          </tbody>
        </table>
      </div>
    </div>
  </div>
  {% endif %}

  {# Conflicting Mappings #}
  {% if conflicts is not empty %}
  <div class="card mb-4 border-warning">
    <div class="card-header bg-warning bg-opacity-10">
      <h5 class="mb-0">
        <i class="bi bi-exclamation-triangle text-warning me-2"></i>
        Ambiguous Column Names
      </h5>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-3">
        These column names have been mapped to different fields. 
        This might indicate inconsistent usage or genuinely ambiguous names.
      </p>
      
      {% for conflict in conflicts %}
      <div class="border rounded p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <code class="bg-light px-2 py-1 rounded">{{ conflict.column_name }}</code>
          <span class="badge bg-secondary">{{ conflict.entity_type }}</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
          {% for mapping in conflict.mappings %}
          <span class="badge bg-{{ loop.first ? 'primary' : 'outline-secondary border' }} px-3 py-2">
            {{ mapping.target_field }}
            <small class="ms-1 opacity-75">({{ mapping.success_count }}×)</small>
          </span>
          {% endfor %}
        </div>
      </div>
      {% endfor %}
    </div>
  </div>
  {% endif %}

  {# How It Works #}
  <div class="card bg-light border-0">
    <div class="card-body">
      <h6 class="card-title">
        <i class="bi bi-cpu me-2"></i>
        How Mapping Intelligence Works
      </h6>
      <div class="row">
        <div class="col-md-4">
          <div class="d-flex align-items-start mb-3 mb-md-0">
            <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
              <i class="bi bi-1-circle text-primary"></i>
            </div>
            <div>
              <strong>Detection</strong>
              <p class="small text-muted mb-0">
                Column headers are analyzed using fuzzy matching, data type inference, and pattern recognition.
              </p>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="d-flex align-items-start mb-3 mb-md-0">
            <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
              <i class="bi bi-2-circle text-primary"></i>
            </div>
            <div>
              <strong>Learning</strong>
              <p class="small text-muted mb-0">
                When you confirm or correct mappings, the system remembers the association.
              </p>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="d-flex align-items-start">
            <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
              <i class="bi bi-3-circle text-primary"></i>
            </div>
            <div>
              <strong>Improvement</strong>
              <p class="small text-muted mb-0">
                Future imports with similar columns will use learned mappings first.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
{% endblock %}
mapping_show.html.twig
{% extends 'katzen/_dashboard_base.html.twig' %}

{% block title %}{{ mapping.name }}{% endblock %}

{% block main_content %}
<div class="import-mapping-detail">
  
  {# ShowPage Component for Header #}
  {% include 'katzen/component/_show_page.html.twig' with { page: page } %}

  {# Field Mappings Table #}
  <div class="card mb-4">
    <div class="card-header bg-transparent">
      <h5 class="mb-0">
        <i class="bi bi-arrow-left-right me-2"></i>
        Field Mappings
      </h5>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 40%;">CSV Column</th>
              <th style="width: 10%;" class="text-center"></th>
              <th style="width: 40%;">Target Field</th>
              <th style="width: 10%;">Transform</th>
            </tr>
          </thead>
          <tbody>
            {% for source, target in field_mappings %}
            {% set has_transform = transformation_rules[source] is defined %}
            <tr>
              <td>
                <code class="bg-light px-2 py-1 rounded">{{ source }}</code>
              </td>
              <td class="text-center text-muted">
                <i class="bi bi-arrow-right"></i>
              </td>
              <td>
                <code class="text-primary">{{ target }}</code>
              </td>
              <td>
                {% if has_transform %}
                <span class="badge bg-info" 
                      data-bs-toggle="tooltip" 
                      title="{{ transformation_rules[source]|json_encode }}">
                  <i class="bi bi-gear"></i>
                  {{ transformation_rules[source].type ?? 'transform' }}
                </span>
                {% else %}
                <span class="text-muted">—</span>
                {% endif %}
              </td>
            </tr>
            {% else %}
            <tr>
              <td colspan="4" class="text-center text-muted py-4">
                No field mappings configured
              </td>
            </tr>
            {% endfor %}
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {# Transformation Rules (if any) #}
  {% if transformation_rules is not empty %}
  <div class="card mb-4">
    <div class="card-header bg-transparent">
      <h5 class="mb-0">
        <i class="bi bi-gear me-2"></i>
        Transformation Rules
      </h5>
    </div>
    <div class="card-body">
      <div class="row g-3">
        {% for field, rule in transformation_rules %}
        <div class="col-md-6">
          <div class="border rounded p-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <code class="text-primary">{{ field }}</code>
              <span class="badge bg-secondary">{{ rule.type ?? 'unknown' }}</span>
            </div>
            <div class="small text-muted">
              <pre class="mb-0" style="font-size: 0.75rem;">{{ rule|json_encode(constant('JSON_PRETTY_PRINT')) }}</pre>
            </div>
          </div>
        </div>
        {% endfor %}
      </div>
    </div>
  </div>
  {% endif %}

  {# Validation Rules (if any) #}
  {% if validation_rules is not empty %}
  <div class="card mb-4">
    <div class="card-header bg-transparent">
      <h5 class="mb-0">
        <i class="bi bi-shield-check me-2"></i>
        Validation Rules
      </h5>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th>Field</th>
              <th>Required</th>
              <th>Type</th>
              <th>Constraints</th>
            </tr>
          </thead>
          <tbody>
            {% for field, rules in validation_rules %}
            <tr>
              <td><code>{{ field }}</code></td>
              <td>
                {% if rules.required is defined and rules.required %}
                <span class="badge bg-danger">Required</span>
                {% else %}
                <span class="text-muted">Optional</span>
                {% endif %}
              </td>
              <td>
                {% if rules.type is defined %}
                <code>{{ rules.type }}</code>
                {% else %}
                <span class="text-muted">—</span>
                {% endif %}
              </td>
              <td>
                {% if rules.min is defined %}min: {{ rules.min }}{% endif %}
                {% if rules.max is defined %}max: {{ rules.max }}{% endif %}
                {% if rules.pattern is defined %}<code>{{ rules.pattern }}</code>{% endif %}
              </td>
            </tr>
            {% endfor %}
          </tbody>
        </table>
      </div>
    </div>
  </div>
  {% endif %}

  {# Default Values (if any) #}
  {% if default_values is not empty %}
  <div class="card mb-4">
    <div class="card-header bg-transparent">
      <h5 class="mb-0">
        <i class="bi bi-card-text me-2"></i>
        Default Values
      </h5>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-3">
        These values will be used when the source data is missing or empty.
      </p>
      <div class="row g-3">
        {% for field, value in default_values %}
        <div class="col-md-4">
          <div class="bg-light rounded p-2">
            <small class="text-muted d-block">{{ field }}</small>
            <code>{{ value }}</code>
          </div>
        </div>
        {% endfor %}
      </div>
    </div>
  </div>
  {% endif %}

  {# Mapping Metadata #}
  <div class="card">
    <div class="card-header bg-transparent">
      <h5 class="mb-0">
        <i class="bi bi-info-circle me-2"></i>
        Mapping Details
      </h5>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Description</dt>
        <dd class="col-sm-9">{{ mapping.description ?: '—' }}</dd>
        
        <dt class="col-sm-3">Created</dt>
        <dd class="col-sm-9">{{ mapping.createdAt|date('M j, Y g:i A') }}</dd>
        
        <dt class="col-sm-3">Last Updated</dt>
        <dd class="col-sm-9">{{ mapping.updatedAt|date('M j, Y g:i A') }}</dd>
        
        <dt class="col-sm-3">Type</dt>
        <dd class="col-sm-9">
          {% if mapping.isSystemTemplate %}
          <span class="badge bg-dark">System Template</span>
          <small class="text-muted ms-2">Cannot be modified</small>
          {% else %}
          <span class="badge bg-light text-dark border">Custom Mapping</span>
          {% endif %}
        </dd>
        
        {% if mapping.createdBy %}
        <dt class="col-sm-3">Created By</dt>
        <dd class="col-sm-9">User #{{ mapping.createdBy }}</dd>
        {% endif %}
      </dl>
    </div>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Initialize Bootstrap tooltips
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.forEach(function(tooltipTriggerEl) {
    new bootstrap.Tooltip(tooltipTriggerEl);
  });
});
</script>
{% endblock %}
mappings.html.twig
{% extends 'katzen/_dashboard_base.html.twig' %}

{% block title %}Import Mappings{% endblock %}

{% block main_content %}
<div class="import-mappings">
  
  {# Page Header #}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-1">
        <i class="bi bi-diagram-3 me-2"></i>
        Import Mappings
      </h2>
      <p class="text-muted mb-0">Configure how CSV columns map to Katzen fields</p>
    </div>
    <a href="{{ path('import_upload') }}" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i>
      New Import
    </a>
  </div>

  {# Entity Type Filter Pills #}
  <div class="mb-4">
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ path('import_mappings') }}" 
         class="btn btn-sm {{ not current_type ? 'btn-primary' : 'btn-outline-secondary' }}">
        All Types
      </a>
      
      {% set entity_config = {
        'order': { 'label': 'Orders', 'color': 'primary' },
        'item': { 'label': 'Items', 'color': 'success' },
        'sellable': { 'label': 'Sellables', 'color': 'info' },
        'customer': { 'label': 'Customers', 'color': 'warning' },
        'vendor': { 'label': 'Vendors', 'color': 'secondary' }
      } %}
      
      {% for type in entity_types %}
      {% set config = entity_config[type] ?? { 'label': type|capitalize, 'color': 'secondary' } %}
      <a href="{{ path('import_mappings', {type: type}) }}" 
         class="btn btn-sm {{ current_type == type ? 'btn-' ~ config.color : 'btn-outline-' ~ config.color }}">
        {{ config.label }}
      </a>
      {% endfor %}
    </div>
  </div>

  {# Mappings Table #}
  <div class="card">
    <div class="card-body p-0">
      {% include 'katzen/component/_table_view.html.twig' with { table: table } %}
    </div>
  </div>

  {# Help Card #}
  <div class="card mt-4 bg-light border-0">
    <div class="card-body">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h6 class="card-title">
            <i class="bi bi-question-circle me-2"></i>
            What are Import Mappings?
          </h6>
          <p class="small mb-0">
            Import mappings tell Katzen how to translate your CSV column headers into database fields.
            <strong>System templates</strong> provide common mappings for popular formats (POS exports, spreadsheets).
            <strong>Custom mappings</strong> are created when you import with a new column structure.
            You can clone and modify any mapping to suit your needs.
          </p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
          <a href="{{ path('import_learning') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-graph-up me-1"></i>
            View Learning Stats
          </a>
        </div>
      </div>
    </div>
  </div>

</div>
{% endblock %}
upload.html.twig
{% extends 'katzen/_dashboard_base.html.twig' %}

{% block title %}Upload Import File{% endblock %}

{% block main_content %}
<div class="import-upload">
  
  {# Header Section #}
  <div class="mb-4">
    <div class="d-flex align-items-center mb-2">
      <a href="{{ path('import_dashboard') }}" class="btn btn-sm btn-outline-secondary me-3">
        <i class="bi bi-arrow-left"></i>
      </a>
      <div>
        <h2 class="mb-1">
          <i class="bi bi-cloud-upload me-2"></i>
          New Import
        </h2>
        <p class="text-muted mb-0">Upload your data file and configure import settings</p>
      </div>
    </div>
  </div>

  {# Wizard Steps Progress #}
  <div class="card mb-4">
    <div class="card-body">
      <div class="row text-center">
        <div class="col-3">
          <div class="wizard-step active">
            <div class="wizard-step-icon bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
              <i class="bi bi-cloud-upload"></i>
            </div>
            <div class="fw-semibold">Upload</div>
            <div class="small text-muted">Choose file</div>
          </div>
        </div>
        <div class="col-3">
          <div class="wizard-step">
            <div class="wizard-step-icon bg-light text-muted rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
              <i class="bi bi-diagram-3"></i>
            </div>
            <div class="text-muted">Map Fields</div>
            <div class="small text-muted">Configure mapping</div>
          </div>
        </div>
        <div class="col-3">
          <div class="wizard-step">
            <div class="wizard-step-icon bg-light text-muted rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
              <i class="bi bi-shield-check"></i>
            </div>
            <div class="text-muted">Validate</div>
            <div class="small text-muted">Preview & check</div>
          </div>
        </div>
        <div class="col-3">
          <div class="wizard-step">
            <div class="wizard-step-icon bg-light text-muted rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
              <i class="bi bi-play-fill"></i>
            </div>
            <div class="text-muted">Import</div>
            <div class="small text-muted">Execute</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {# Help Cards #}
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card border-primary h-100">
        <div class="card-body">
          <div class="d-flex align-items-start">
            <div class="bg-primary bg-opacity-10 rounded-3 p-2 me-3">
              <i class="bi bi-file-earmark-spreadsheet text-primary" style="font-size: 1.5rem;"></i>
            </div>
            <div>
              <h6 class="mb-1">Supported Formats</h6>
              <p class="text-muted small mb-0">CSV and Excel files (.csv, .xlsx, .xls) up to 10MB</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-success h-100">
        <div class="card-body">
          <div class="d-flex align-items-start">
            <div class="bg-success bg-opacity-10 rounded-3 p-2 me-3">
              <i class="bi bi-lightbulb text-success" style="font-size: 1.5rem;"></i>
            </div>
            <div>
              <h6 class="mb-1">Smart Mapping</h6>
              <p class="text-muted small mb-0">AI-powered field detection suggests the best mappings automatically</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-info h-100">
        <div class="card-body">
          <div class="d-flex align-items-start">
            <div class="bg-info bg-opacity-10 rounded-3 p-2 me-3">
              <i class="bi bi-shield-check text-info" style="font-size: 1.5rem;"></i>
            </div>
            <div>
              <h6 class="mb-1">Safe Preview</h6>
              <p class="text-muted small mb-0">Review and validate before any data is imported</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {# Main Upload Form #}
  <div class="row">
    <div class="col-lg-8 mx-auto">
      <div class="card">
        <div class="card-header bg-transparent">
          <h5 class="mb-0">
            <i class="bi bi-file-earmark-arrow-up me-2"></i>
            Upload Your File
          </h5>
        </div>
        <div class="card-body">
          
          {{ form_start(form, {'attr': {'class': 'import-upload-form'}}) }}
          {{ form_errors(form) }}
          
          {# Entity Type Selection #}
          <div class="mb-4">
            <div class="alert alert-light border d-flex align-items-start">
              <i class="bi bi-info-circle text-primary me-2 mt-1"></i>
              <div class="small">
                Select what type of data you're importing. This helps Katzen understand your file structure and suggest the right field mappings.
              </div>
            </div>
            {{ form_row(form.entity_type, {
              'label_attr': {'class': 'form-label fw-semibold'},
              'attr': {'class': 'form-select form-select-lg'}
            }) }}
          </div>

          {# File Upload #}
          <div class="mb-4">
            <div class="file-upload-wrapper">
              {{ form_label(form.file, null, {'label_attr': {'class': 'form-label fw-semibold'}}) }}
              
              <div class="file-drop-zone border-2 border-dashed rounded p-5 text-center" id="file-drop-zone">
                <i class="bi bi-cloud-upload text-primary mb-3" style="font-size: 3rem;"></i>
                <div class="mb-3">
                  <div class="fw-semibold mb-1">Drag & drop your file here</div>
                  <div class="text-muted small">or click to browse</div>
                </div>
                
                {{ form_widget(form.file, {
                  'attr': {
                    'class': 'form-control form-control-lg d-none'
                  }
                }) }}
                
                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('{{ form.file.vars.id }}').click()">
                  <i class="bi bi-folder2-open me-1"></i>
                  Choose File
                </button>
                
                <div id="file-name-display" class="mt-3 d-none">
                  <div class="alert alert-success d-inline-flex align-items-center">
                    <i class="bi bi-file-earmark-check me-2"></i>
                    <span id="file-name"></span>
                  </div>
                </div>
              </div>
              
              {{ form_errors(form.file) }}
              {{ form_help(form.file) }}
            </div>
          </div>

          {# Import Name (Optional) #}
          <div class="mb-4">
            {{ form_row(form.name, {
              'label_attr': {'class': 'form-label fw-semibold'},
              'attr': {'class': 'form-control'}
            }) }}
          </div>

          {# Existing Mapping Template #}
          {% if form.use_existing_mapping is defined %}
          <div class="mb-4">
            <div class="card bg-light">
              <div class="card-body">
                <h6 class="card-title mb-3">
                  <i class="bi bi-lightning-charge text-warning me-2"></i>
                  Quick Start
                </h6>
                {{ form_row(form.use_existing_mapping, {
                  'label_attr': {'class': 'form-label'},
                  'attr': {'class': 'form-select'}
                }) }}
                <div class="small text-muted mt-2">
                  <i class="bi bi-info-circle me-1"></i>
                  Select a saved mapping template to skip manual field configuration. You can still review and adjust before importing.
                </div>
              </div>
            </div>
          </div>
          {% endif %}

          {# Submit Button #}
          <div class="d-flex justify-content-between align-items-center">
            <a href="{{ path('import_dashboard') }}" class="btn btn-outline-secondary">
              <i class="bi bi-x-lg me-1"></i>
              Cancel
            </a>
            {{ form_row(form.submit, {
              'label': 'Continue to Mapping',
              'attr': {'class': 'btn btn-primary btn-lg'}
            }) }}
          </div>

          {{ form_widget(form._token) }}
          {{ form_end(form, {'render_rest': false}) }}
          
        </div>
      </div>

      {# Additional Tips #}
      <div class="card mt-3 border-0 bg-light">
        <div class="card-body">
          <h6 class="mb-3">
            <i class="bi bi-lightbulb me-2"></i>
            Tips for Best Results
          </h6>
          <ul class="mb-0 small text-muted">
            <li class="mb-2">Ensure your file has clear column headers in the first row</li>
            <li class="mb-2">Remove any summary rows or totals at the bottom of your data</li>
            <li class="mb-2">Check that dates are formatted consistently (e.g., MM/DD/YYYY or YYYY-MM-DD)</li>
            <li class="mb-2">For best mapping results, use descriptive header names (e.g., "Product Name" instead of just "Name")</li>
            <li>If importing prices, ensure they're numeric values without currency symbols</li>
          </ul>
        </div>
      </div>

    </div>
  </div>

</div>

<style>
  .wizard-step {
    position: relative;
  }
  
  .wizard-step.active .wizard-step-icon {
    box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.25);
  }
  
  .file-drop-zone {
    transition: all 0.3s ease;
    background: #fafafa;
  }
  
  .file-drop-zone:hover {
    border-color: var(--bs-primary) !important;
    background: rgba(13, 110, 253, 0.05);
  }
  
  .file-drop-zone.dragover {
    border-color: var(--bs-primary) !important;
    background: rgba(13, 110, 253, 0.1);
  }
  
  .import-upload .card {
    border-radius: 0.5rem;
    border-color: rgba(0,0,0,0.1);
  }
  
  .import-upload .card-header {
    border-bottom-color: rgba(0,0,0,0.05);
  }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const fileInput = document.getElementById('{{ form.file.vars.id }}');
  const dropZone = document.getElementById('file-drop-zone');
  const fileNameDisplay = document.getElementById('file-name-display');
  const fileName = document.getElementById('file-name');
  
  // File input change handler
  if (fileInput) {
    fileInput.addEventListener('change', function(e) {
      if (e.target.files.length > 0) {
        const file = e.target.files[0];
        fileName.textContent = file.name;
        fileNameDisplay.classList.remove('d-none');
      }
    });
  }
  
  // Drag and drop handlers
  if (dropZone) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
      e.preventDefault();
      e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
      dropZone.addEventListener(eventName, function() {
        dropZone.classList.add('dragover');
      }, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
      dropZone.addEventListener(eventName, function() {
        dropZone.classList.remove('dragover');
      }, false);
    });
    
    dropZone.addEventListener('drop', function(e) {
      const dt = e.dataTransfer;
      const files = dt.files;
      
      if (files.length > 0) {
        fileInput.files = files;
        // Trigger change event
        const event = new Event('change', { bubbles: true });
        fileInput.dispatchEvent(event);
      }
    }, false);
  }
  
  // Entity type change - could show/hide relevant tips or warnings
  const entityTypeSelect = document.querySelector('select[name*="entity_type"]');
  if (entityTypeSelect) {
    entityTypeSelect.addEventListener('change', function(e) {
      // Optional: Add dynamic help text based on entity type
      console.log('Selected entity type:', e.target.value);
    });
  }
});
</script>
{% endblock %}
validate.html.twig
{% extends 'katzen/_dashboard_base.html.twig' %}

{% block title %}Validate Import Data{% endblock %}

{% block main_content %}
<div class="import-validate">
  
  <div class="mb-4">
    <div class="d-flex align-items-center mb-2">
      <a href="{{ path('import_configure_mapping') }}" class="btn btn-sm btn-outline-secondary me-3">
        <i class="bi bi-arrow-left"></i>
      </a>
      <div>
        <h2 class="mb-1">
          <i class="bi bi-shield-check me-2"></i>
          Validate Import Data
        </h2>
        <p class="text-muted mb-0">Review your data before importing {{ entity_type|title }} records</p>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <div class="row text-center">
        <div class="col-3">
          <div class="wizard-step completed">
            <div class="wizard-step-icon bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
              <i class="bi bi-check-lg"></i>
            </div>
            <div class="fw-semibold text-success">Upload</div>
            <div class="small text-muted">Complete</div>
          </div>
        </div>
        <div class="col-3">
          <div class="wizard-step completed">
            <div class="wizard-step-icon bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
              <i class="bi bi-check-lg"></i>
            </div>
            <div class="fw-semibold text-success">Map Fields</div>
            <div class="small text-muted">Complete</div>
          </div>
        </div>
        <div class="col-3">
          <div class="wizard-step active">
            <div class="wizard-step-icon bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
              <i class="bi bi-shield-check"></i>
            </div>
            <div class="fw-semibold">Validate</div>
            <div class="small text-muted">Preview & check</div>
          </div>
        </div>
        <div class="col-3">
          <div class="wizard-step">
            <div class="wizard-step-icon bg-light text-muted rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">
              <i class="bi bi-play-fill"></i>
            </div>
            <div class="text-muted">Import</div>
            <div class="small text-muted">Execute</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card border-primary h-100">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="bg-primary bg-opacity-10 rounded-3 p-3 me-3">
              <i class="bi bi-file-earmark-text text-primary" style="font-size: 2rem;"></i>
            </div>
            <div>
              <div class="text-muted small">Total Rows</div>
              <div class="h4 mb-0">{{ validation_results.total_rows|number_format }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-success h-100">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="bg-success bg-opacity-10 rounded-3 p-3 me-3">
              <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
            </div>
            <div>
              <div class="text-muted small">Valid Rows</div>
              <div class="h4 mb-0 text-success">{{ validation_results.valid_rows|number_format }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-warning h-100">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="bg-warning bg-opacity-10 rounded-3 p-3 me-3">
              <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
            </div>
            <div>
              <div class="text-muted small">Warnings</div>
              <div class="h4 mb-0 text-warning">{{ validation_results.warnings|number_format }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-danger h-100">
        <div class="card-body">
          <div class="d-flex align-items-center">
            <div class="bg-danger bg-opacity-10 rounded-3 p-3 me-3">
              <i class="bi bi-x-circle text-danger" style="font-size: 2rem;"></i>
            </div>
            <div>
              <div class="text-muted small">Errors</div>
              <div class="h4 mb-0 text-danger">{{ validation_results.critical_errors|number_format }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {% if validation_results.critical_errors > 0 %}
  <div class="alert alert-danger d-flex align-items-start mb-4">
    <i class="bi bi-exclamation-triangle-fill me-3 mt-1" style="font-size: 1.5rem;"></i>
    <div>
      <h5 class="alert-heading mb-2">Import Blocked: Critical Errors Found</h5>
      <p class="mb-2">
        Your data contains {{ validation_results.critical_errors }} critical error(s) that must be fixed before importing. 
        Common issues include missing required fields, invalid data types, or constraint violations.
      </p>
      <p class="mb-0 small">
        <strong>Recommendation:</strong> Review the errors below, fix your source file, and re-upload. 
        You can also adjust field mappings if needed.
      </p>
    </div>
  </div>
  {% elseif validation_results.warnings > 0 %}
  <div class="alert alert-warning d-flex align-items-start mb-4">
    <i class="bi bi-info-circle-fill me-3 mt-1" style="font-size: 1.25rem;"></i>
    <div>
      <h6 class="alert-heading mb-1">Warnings Detected</h6>
      <p class="mb-0 small">
        Your data contains {{ validation_results.warnings }} warning(s). These won't block the import, 
        but you may want to review them to ensure data quality.
      </p>
    </div>
  </div>
  {% else %}
  <div class="alert alert-success d-flex align-items-start mb-4">
    <i class="bi bi-check-circle-fill me-3 mt-1" style="font-size: 1.25rem;"></i>
    <div>
      <h6 class="alert-heading mb-1">Validation Passed!</h6>
      <p class="mb-0 small">
        All {{ validation_results.total_rows }} rows passed validation. You're ready to import.
      </p>
    </div>
  </div>
  {% endif %}

  {% if validation_results.errors is defined and validation_results.errors is not empty %}
  <div class="card mb-4">
    <div class="card-header bg-transparent">
      <h5 class="mb-0">
        <i class="bi bi-exclamation-octagon me-2"></i>
        Validation Issues
      </h5>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 10%;">Row</th>
              <th style="width: 20%;">Field</th>
              <th style="width: 15%;">Severity</th>
              <th style="width: 55%;">Issue</th>
            </tr>
          </thead>
          <tbody>
            {% for error in validation_results.errors|slice(0, 50) %}
            <tr class="{% if error.severity == 'critical' %}table-danger{% elseif error.severity == 'warning' %}table-warning{% endif %}">
              <td class="text-center">
                <code>{{ error.row_number }}</code>
              </td>
              <td>
                <code class="text-primary">{{ error.field }}</code>
              </td>
              <td>
                {% if error.severity == 'critical' %}
                <span class="badge bg-danger">
                  <i class="bi bi-x-circle"></i>
                  Critical
                </span>
                {% elseif error.severity == 'error' %}
                <span class="badge bg-warning">
                  <i class="bi bi-exclamation-triangle"></i>
                  Error
                </span>
                {% else %}
                <span class="badge bg-info">
                  <i class="bi bi-info-circle"></i>
                  Warning
                </span>
                {% endif %}
              </td>
              <td>
                {{ error.message }}
                {% if error.value is defined %}
                <div class="small text-muted mt-1">
                  Value: <code>{{ error.value }}</code>
                </div>
                {% endif %}
              </td>
            </tr>
            {% endfor %}
            {% if validation_results.errors|length > 50 %}
            <tr>
              <td colspan="4" class="text-center text-muted py-3">
                <i class="bi bi-three-dots"></i>
                Showing first 50 of {{ validation_results.errors|length }} issues. 
                All issues will be available in the import batch report.
              </td>
            </tr>
            {% endif %}
          </tbody>
        </table>
      </div>
    </div>
  </div>
  {% endif %}

  <div class="card mb-4">
    <div class="card-header bg-transparent">
      <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
          <i class="bi bi-eye me-2"></i>
          Data Preview
        </h5>
        <span class="badge bg-light text-dark border">
          Showing first {{ validation_results.preview_rows|length }} rows
        </span>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light sticky-top">
            <tr>
              <th style="width: 5%;" class="text-center">#</th>
              {% for field in validation_results.headers %}
              <th>{{ field|title|replace({'_': ' '}) }}</th>
              {% endfor %}
            </tr>
          </thead>
          <tbody>
            {% for row in validation_results.preview_rows %}
            <tr>
              <td class="text-center text-muted">
                {{ row.row_number }}
              </td>
              
              {% for field in validation_results.headers %}
              {% set value = row.data[field] ?? null %}
              
              <td>
                {% if value is null or value == '' %}
                <span class="text-muted fst-italic">—</span>
                {% else %}
                <span class="text-truncate d-inline-block"
                      style="max-width: 200px;"
                      title="{{ value }}">
                  {{ value }}
                </span>
                {% endif %}
              </td>
              {% endfor %}
            </tr>
            {% else %}
            <tr>
              <td colspan="100" class="text-center text-muted py-4">
                No preview data available
              </td>
            </tr>
            {% endfor %}
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center">
        <a href="{{ path('import_configure_mapping') }}" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left me-1"></i>
          Back to Mapping
        </a>
        <div>
          {% if can_proceed %}
          <button type="button" class="btn btn-success btn-lg" id="execute-import-btn">
            <i class="bi bi-play-fill me-2"></i>
            Start Import
          </button>
          {% else %}
          <button type="button" class="btn btn-danger btn-lg" disabled>
            <i class="bi bi-x-circle me-2"></i>
            Cannot Import (Fix Errors)
          </button>
          {% endif %}
        </div>
      </div>
    </div>
  </div>

</div>

<div class="modal fade" id="import-modal" tabindex="-1" aria-labelledby="import-modal-label" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="import-modal-label">
          <i class="bi bi-hourglass-split me-2"></i>
          Importing Data...
        </h5>
      </div>
      <div class="modal-body text-center py-5">
        <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mb-0">Please wait while your data is being imported.</p>
        <p class="text-muted small">This may take a few moments depending on the file size.</p>
        <div class="progress mt-3" style="height: 25px;">
          <div class="progress-bar progress-bar-striped progress-bar-animated" 
               role="progressbar" 
               style="width: 0%;" 
               id="import-progress-bar">
            <span id="progress-text">0%</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .wizard-step {
    position: relative;
  }
  
  .wizard-step.active .wizard-step-icon {
    box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.25);
  }
  
  .wizard-step.completed .wizard-step-icon {
    box-shadow: 0 0 0 4px rgba(25, 135, 84, 0.25);
  }

  .import-validate .card {
    border-radius: 0.5rem;
    border-color: rgba(0,0,0,0.1);
  }
  
  .import-validate .card-header {
    border-bottom-color: rgba(0,0,0,0.05);
  }

  .table thead.sticky-top {
    position: sticky;
    top: 0;
    z-index: 10;
    background: white;
  }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const executeBtn = document.getElementById('execute-import-btn');
  const importModal = new bootstrap.Modal(document.getElementById('import-modal'));
  const progressBar = document.getElementById('import-progress-bar');
  const progressText = document.getElementById('progress-text');
  
  if (executeBtn) {
    executeBtn.addEventListener('click', async function() {
      // Confirm before starting
      const confirmed = confirm(
        'Ready to start the import? This will create {{ validation_results.valid_rows }} new {{ entity_type }} record(s).'
      );
      
      if (!confirmed) return;
      
      // Show loading modal
      importModal.show();
      
      try {
        // Start the import
        const response = await fetch('{{ path('import_execute') }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        
        const result = await response.json();
        
        if (result.success) {
          // Redirect to batch detail page
          window.location.href = result.redirect_url;
        } else {
          importModal.hide();
          alert('Import failed: ' + (result.error || 'Unknown error'));
        }
        
      } catch (error) {
        importModal.hide();
        alert('Import failed: ' + error.message);
        console.error('Import error:', error);
      }
    });
  }
  
  // Simulate progress (in reality, you'd poll the progress endpoint)
  let progress = 0;
  const progressInterval = setInterval(function() {
    if (progress < 90) {
      progress += Math.random() * 10;
      progressBar.style.width = progress + '%';
      progressText.textContent = Math.round(progress) + '%';
    }
  }, 500);
  
  // Clear interval when modal is hidden
  document.getElementById('import-modal').addEventListener('hidden.bs.modal', function() {
    clearInterval(progressInterval);
  });
});
</script>
{% endblock %}
