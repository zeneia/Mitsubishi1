<?php
include_once(dirname(dirname(__DIR__)) . '/includes/init.php');

// Require authentication
if (!isLoggedIn()) {
  header('Location: ../login.php');
  exit;
}

// Normalize role (handle "SalesAgent" and "Sales Agent")
$role = $_SESSION['user_role'] ?? '';
$normalizedRole = strtolower(str_replace(' ', '', $role));
$authorized = in_array($normalizedRole, ['admin', 'salesagent'], true);

// Prepare page context
$current_page = 'sms';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SMS - Mitsubishi</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../../includes/css/common-styles.css" rel="stylesheet">

  <style>
  
  body{
      zoom: 85%;
  }
    /* Page header */
    .page-header {
      background: #fff;
      border: 1px solid var(--border-light);
      border-radius: 12px;
      box-shadow: var(--shadow-light);
      padding: 24px;
      margin-bottom: 20px;
    }
    .page-header h1 {
      font-size: 22px;
      color: var(--text-dark);
      margin-bottom: 6px;
    }
    .page-header p {
      color: var(--text-light);
      font-size: 14px;
    }

    /* Tabs */
    .tabs {
      background: #fff;
      border: 1px solid var(--border-light);
      border-radius: 12px;
      box-shadow: var(--shadow-light);
      overflow: hidden;
    }
    .tablist {
      display: flex;
      gap: 6px;
      overflow-x: auto;
      padding: 10px;
      background: var(--primary-light);
      border-bottom: 1px solid var(--border-light);
      scroll-snap-type: x mandatory;
    }
    .tablist::-webkit-scrollbar {
      height: 6px;
    }
    .tablist::-webkit-scrollbar-thumb {
      background: var(--border-light);
      border-radius: 4px;
    }
    .tab {
      appearance: none;
      background: #fff;
      border: 1px solid var(--border-light);
      color: var(--text-dark);
      padding: 10px 14px;
      border-radius: 999px;
      font-size: 14px;
      cursor: pointer;
      white-space: nowrap;
      scroll-snap-align: start;
      transition: var(--transition);
    }
    .tab[aria-selected="true"] {
      background: linear-gradient(135deg, var(--primary-red), #b91c3c);
      color: #fff;
      border-color: transparent;
    }
    .tab:focus {
      outline: 3px solid var(--accent-blue);
      outline-offset: 2px;
    }

    .tabpanel {
      padding: 20px;
    }

    /* Send form */
    .form-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 14px;
    }
    .form-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .form-field label {
      font-size: 13px;
      color: var(--text-dark);
      font-weight: 600;
    }
    .form-field input[type="tel"],
    .form-field select,
    .form-field textarea {
      border: 1px solid var(--border-light);
      border-radius: 10px;
      padding: 10px 12px;
      font-size: 14px;
      background: #fff;
      color: var(--text-dark);
      transition: var(--transition);
    }
    .form-field textarea {
      resize: vertical;
      min-height: 120px;
      font-family: inherit;
      line-height: 1.4;
    }
    .form-field input[type="tel"]:focus,
    .form-field select:focus,
    .form-field textarea:focus {
      outline: 3px solid var(--accent-blue);
      outline-offset: 2px;
    }
    .help-text {
      font-size: 12px;
      color: var(--text-light);
    }
    .error-text {
      font-size: 12px;
      color: var(--primary-red);
    }
    .counter {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 12px;
      color: var(--text-light);
    }
    .actions {
      display: flex;
      gap: 10px;
      margin-top: 6px;
    }
    .actions .btn-primary[disabled] {
      opacity: 0.65;
      cursor: not-allowed;
    }

    /* Tables / Lists */
    .toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
      margin-bottom: 12px;
    }
    .toolbar .search {
      position: relative;
      flex: 1 1 240px;
      min-width: 220px;
    }
    .toolbar .search input {
      width: 100%;
      padding: 10px 12px 10px 36px;
      border: 1px solid var(--border-light);
      border-radius: 10px;
      font-size: 14px;
    }
    .toolbar .search i {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-light);
    }
    .toolbar .filter {
      display: flex;
      gap: 8px;
      align-items: center;
    }
    .toolbar .filter select {
      border: 1px solid var(--border-light);
      border-radius: 10px;
      padding: 10px 12px;
      font-size: 14px;
      background: #fff;
      color: var(--text-dark);
    }

    .data-table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
      border: 1px solid var(--border-light);
      border-radius: 12px;
      overflow: hidden;
    }
    .data-table thead th {
      text-align: left;
      font-size: 12px;
      color: var(--text-light);
      background: var(--primary-light);
      padding: 12px;
      border-bottom: 1px solid var(--border-light);
      user-select: none;
      cursor: pointer;
    }
    .data-table tbody td {
      font-size: 14px;
      color: var(--text-dark);
      padding: 12px;
      border-bottom: 1px solid var(--border-light);
    }
    .status-badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
      background: var(--primary-light);
      color: var(--text-dark);
    }
    .status-badge.success {
      background: #d4edda;
      color: #155724;
    }
    .status-badge.error {
      background: #f8d7da;
      color: #721c24;
    }
    .status-badge.pending {
      background: #fff3cd;
      color: #856404;
    }

    /* Empty / Loading / Error states */
    .state {
      border: 1px dashed var(--border-light);
      border-radius: 12px;
      padding: 24px;
      background: #fff;
      color: var(--text-light);
      text-align: center;
    }
    .skeleton {
      display: grid;
      gap: 8px;
    }
    .skeleton .bar {
      height: 12px;
      background: var(--primary-light);
      border-radius: 6px;
      animation: pulse 1.2s ease-in-out infinite;
    }
    @keyframes pulse {
      0% { opacity: 1; }
      50% { opacity: 0.55; }
      100% { opacity: 1; }
    }

    /* Drawer (details panel) */
    .drawer-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.5);
      display: none;
      align-items: stretch;
      justify-content: flex-end;
      z-index: 10000;
    }
    .drawer {
      width: min(480px, 92vw);
      background: #fff;
      height: 100%;
      box-shadow: var(--shadow-medium);
      padding: 18px;
      overflow-y: auto;
    }
    .drawer-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid var(--border-light);
      padding-bottom: 10px;
      margin-bottom: 12px;
    }
    .drawer h3 {
      margin: 0;
      font-size: 18px;
      color: var(--text-dark);
    }
    .drawer .close-btn {
      appearance: none;
      background: none;
      border: 1px solid var(--border-light);
      border-radius: 6px;
      padding: 6px 10px;
      cursor: pointer;
    }
    .drawer .close-btn:focus {
      outline: 3px solid var(--accent-blue);
      outline-offset: 2px;
    }

    /* Responsive stacked cards for tables */
    @media (max-width: 575px) {
      .data-table {
        display: none;
      }
      .card-list {
        display: grid;
        gap: 10px;
      }
      .card {
        background: #fff;
        border: 1px solid var(--border-light);
        border-radius: 12px;
        padding: 14px;
        box-shadow: var(--shadow-light);
      }
      .card-row {
        display: flex;
        justify-content: space-between;
        font-size: 14px;
        padding: 6px 0;
        border-bottom: 1px solid var(--primary-light);
      }
      .card-row:last-child {
        border-bottom: none;
      }
      .card-label {
        color: var(--text-light);
        font-weight: 600;
        margin-right: 10px;
      }
      .card-value {
        color: var(--text-dark);
        text-align: right;
      }
    }
  </style>
</head>
<body>
  <?php include '../../includes/components/sidebar.php'; ?>

  <div class="main">
    <?php include '../../includes/components/topbar.php'; ?>

    <div class="main-content" id="mainContent">
      <?php if (!$authorized): ?>
        <?php http_response_code(403); ?>
        <?php include '../../includes/components/not_authorized.php'; ?>
      <?php else: ?>
        <section class="page-header" aria-labelledby="sms-title">
          <h1 id="sms-title">SMS</h1>
          <p>Send and manage text messages using your organization’s standard communication tools.</p>
        </section>

        <section class="tabs">
          <div class="tablist"
               id="smsTablist"
               role="tablist"
               aria-label="SMS sections"
               tabindex="0">
            <button class="tab" id="tab-send" role="tab" aria-controls="panel-send" aria-selected="true">Send</button>
            <button class="tab" id="tab-templates" role="tab" aria-controls="panel-templates" aria-selected="false" tabindex="-1">Templates</button>
            <button class="tab" id="tab-history" role="tab" aria-controls="panel-history" aria-selected="false" tabindex="-1">History</button>
          </div>

          <!-- Send -->
          <div id="panel-send" class="tabpanel" role="tabpanel" aria-labelledby="tab-send">
            <form id="sendForm" novalidate>
              <div class="form-grid">
                <div class="form-field">
                  <label for="recipient">Recipient phone</label>
                  <input type="tel"
                         id="recipient"
                         name="recipient"
                         inputmode="tel"
                         autocomplete="tel"
                         placeholder="+639171234567"
                         aria-describedby="recipientHelp recipientError"
                         required 
                         oninput="this.value = this.value.replace(/[^0-9+]/g,'')"
                         onkeydown="if(event.key === 'e' || event.key === 'E') event.preventDefault();"/>
                  <div id="recipientHelp" class="help-text">Use E.164 format. Example: +639171234567 (max 15 digits).</div>
                  <div id="recipientError" class="error-text" aria-live="polite"></div>
                </div>

                <div class="form-field">
                  <label for="message">Message</label>
                  <textarea id="message"
                            name="message"
                            placeholder="Type your message..."
                            aria-describedby="messageCounter"
                            maxlength="2000"
                            required></textarea>
                  <div class="counter" id="messageCounter">
                    <span id="charCount">0 chars</span>
                    <span id="segmentCount">0 segments</span>
                  </div>
                </div>

                <div class="actions">
                  <button type="submit" id="sendBtn" class="btn-primary">Send</button>
                  <button type="button" id="clearBtn" class="btn-secondary">Clear</button>
                </div>
              </div>
            </form>
          </div>

          <!-- Templates -->
          <div id="panel-templates" class="tabpanel" role="tabpanel" aria-labelledby="tab-templates" hidden>
            <div class="toolbar">
              <div class="search">
                <i class="fas fa-search"></i>
                <input type="text" id="templateSearch" placeholder="Search templates..." aria-label="Search templates">
              </div>
              <div class="filter">
                <select id="templateSort" aria-label="Sort templates">
                  <option value="updated_desc">Updated: Newest</option>
                  <option value="updated_asc">Updated: Oldest</option>
                  <option value="name_asc">Name: A–Z</option>
                  <option value="name_desc">Name: Z–A</option>
                </select>
                <button class="btn-primary" id="newTemplateBtn" type="button"><i class="fas fa-plus" aria-hidden="true"></i>&nbsp;New Template</button>
              </div>
            </div>

            <div id="templatesState" class="state" aria-live="polite" style="display:none;"></div>

            <table class="data-table" id="templatesTable" aria-label="Templates table">
              <thead>
                <tr>
                  <th data-sort="name">Name</th>
                  <th>Preview</th>
                  <th data-sort="updatedAt">Updated</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="templatesTbody"></tbody>
            </table>

            <div class="card-list" id="templatesCards" style="display:none;"></div>
          </div>

          <!-- History -->
          <div id="panel-history" class="tabpanel" role="tabpanel" aria-labelledby="tab-history" hidden>
            <div class="toolbar">
              <div class="search">
                <i class="fas fa-search"></i>
                <input type="text" id="historySearch" placeholder="Search recipient or message..." aria-label="Search history">
              </div>
              <div class="filter">
                <select id="historyStatus" aria-label="Filter status">
                  <option value="">All statuses</option>
                  <option value="Sent">Sent</option>
                  <option value="Failed">Failed</option>
                </select>
                <select id="historySort" aria-label="Sort history">
                  <option value="date_desc">Newest first</option>
                  <option value="date_asc">Oldest first</option>
                </select>
              </div>
            </div>

            <div id="historyState" class="state" aria-live="polite" style="display:none;"></div>

            <table class="data-table" id="historyTable" aria-label="History table">
              <thead>
                <tr>
                  <th data-sort="recipient">Recipient</th>
                  <th>Status</th>
                  <th data-sort="date">Date/Time</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="historyTbody"></tbody>
            </table>

            <div class="card-list" id="historyCards" style="display:none;"></div>

            <div class="toolbar" style="justify-content:flex-end;margin-top:10px;">
              <div class="filter">
                <button type="button" class="btn-secondary" id="prevPageBtn" aria-label="Previous page">Prev</button>
                <span id="pageInfo" class="help-text" aria-live="polite"></span>
                <button type="button" class="btn-secondary" id="nextPageBtn" aria-label="Next page">Next</button>
              </div>
            </div>
          </div>
        </section>

        <!-- Drawer for history details -->
        <div id="drawerOverlay" class="drawer-overlay" aria-hidden="true">
          <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="drawerTitle">
            <div class="drawer-header">
              <h3 id="drawerTitle">Message details</h3>
              <button type="button" class="close-btn" id="drawerCloseBtn" aria-label="Close">Close</button>
            </div>
            <div id="drawerContent"></div>
          </aside>
        </div>

        <!-- Template Modal -->
        <div class="modal-overlay" id="templateModal">
          <div class="modal" role="dialog" aria-modal="true" aria-labelledby="templateModalTitle">
            <div class="modal-header">
              <h3 id="templateModalTitle">New Template</h3>
              <button class="modal-close" id="templateModalClose" aria-label="Close template modal">
                <i class="fas fa-times" aria-hidden="true"></i>
              </button>
            </div>
            <div class="modal-body">
              <form id="templateForm" novalidate>
                <input type="hidden" id="templateId" />
                <div class="form-field">
                  <label for="templateName">Name</label>
                  <input type="text" id="templateName" required aria-describedby="templateNameErr" />
                  <div id="templateNameErr" class="error-text" aria-live="polite"></div>
                </div>
                <div class="form-field">
                  <label for="templateContent">Content</label>
                  <textarea id="templateContent" required aria-describedby="templateContentErr" rows="6"></textarea>
                  <div id="templateContentErr" class="error-text" aria-live="polite"></div>
                </div>
              </form>
            </div>
            <div class="modal-footer">
              <button class="btn-secondary" id="templateCancelBtn" type="button">Cancel</button>
              <button class="btn-primary" id="templateSaveBtn" type="button">Save</button>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script src="../../includes/js/common-scripts.js"></script>
  <script>
    (function () {
      'use strict';

      // SweetAlert2 fallback loader (topbar attempts to include it; this ensures availability)
      if (typeof window.Swal === 'undefined') {
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
        document.head.appendChild(s);
      }

      // Feature flags
      // Tabs: roving tabindex + keyboard navigation
      const tablist = document.getElementById('smsTablist');
      const tabs = Array.from(tablist?.querySelectorAll('.tab') || []);
      const panels = [
        document.getElementById('panel-send'),
        document.getElementById('panel-templates'),
        document.getElementById('panel-history')
      ];
      let activeTabIndex = 0;

      function setActiveTab(index, setFocus = true) {
        tabs.forEach((t, i) => {
          const selected = i === index;
          t.setAttribute('aria-selected', selected ? 'true' : 'false');
          if (!selected) t.setAttribute('tabindex', '-1'); else t.removeAttribute('tabindex');
          panels[i]?.toggleAttribute('hidden', !selected);
        });
        activeTabIndex = index;
        if (setFocus) tabs[index].focus();

        // Load history when History tab is activated
        if (index === 2) { // History tab is index 2
          loadSmsHistory();
        }
      }

      tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => setActiveTab(index, false));
        tab.addEventListener('keydown', (e) => {
          const key = e.key;
          if (key === 'ArrowRight') {
            e.preventDefault();
            setActiveTab((activeTabIndex + 1) % tabs.length);
          } else if (key === 'ArrowLeft') {
            e.preventDefault();
            setActiveTab((activeTabIndex - 1 + tabs.length) % tabs.length);
          } else if (key === 'Home') {
            e.preventDefault();
            setActiveTab(0);
          } else if (key === 'End') {
            e.preventDefault();
            setActiveTab(tabs.length - 1);
          }
        });
      });

      // Initialize first tab
      if (tabs.length) setActiveTab(0, false);

      // Send form logic
      const recipientInput = document.getElementById('recipient');
      const recipientError = document.getElementById('recipientError');
      const messageInput = document.getElementById('message');
      const charCountEl = document.getElementById('charCount');
      const segmentCountEl = document.getElementById('segmentCount');
      const sendBtn = document.getElementById('sendBtn');
      const clearBtn = document.getElementById('clearBtn');

      function isE164(phone) {
        // E.164: + followed by 8..15 digits, not starting with 0
        return /^\+[1-9]\d{7,14}$/.test(phone);
      }

      // GSM-7 basic and extension sets
      const GSM7_BASIC = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\u0020!\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ`¿abcdefghijklmnopqrstuvwxyzäöñüà^{}\\[~]|".replace(/&/g,'&').replace(/</g,'<').replace(/>/g,'>');
      const GSM7_EXT = "^{}\\[~]|€";

      function isGsm7(text) {
        for (let i = 0; i < text.length; i++) {
          const ch = text[i];
          if (GSM7_BASIC.indexOf(ch) !== -1) continue;
          if (GSM7_EXT.indexOf(ch) !== -1) continue;
          return false;
        }
        return true;
      }

      function computeSegments(text) {
        if (!text) return { unicode: false, chars: 0, segments: 0, perSegment: 160, concat: 153 };
        const unicode = !isGsm7(text);
        const single = unicode ? 70 : 160;
        const concat = unicode ? 67 : 153;
        const len = text.length;
        if (len <= single) return { unicode, chars: len, segments: len === 0 ? 0 : 1, perSegment: single, concat };
        const segments = Math.ceil(len / concat);
        return { unicode, chars: len, segments, perSegment: concat, concat };
      }

      function updateMessageCounters() {
        const val = messageInput.value || '';
        const meta = computeSegments(val);
        charCountEl.textContent = meta.chars + ' chars' + (meta.unicode ? ' (Unicode)' : ' (GSM-7)');
        segmentCountEl.textContent = meta.segments + ' segments' + (meta.segments > 1 ? ` (~${meta.perSegment} per sms)` : '');
      }

      function validateRecipient() {
        const phone = (recipientInput.value || '').trim();
        if (!phone) {
          recipientError.textContent = 'Recipient is required.';
          return false;
        }
        if (!isE164(phone)) {
          recipientError.textContent = 'Invalid E.164 format. Example: +639171234567';
          return false;
        }
        recipientError.textContent = '';
        return true;
      }

      recipientInput?.addEventListener('input', validateRecipient);
      recipientInput?.addEventListener('blur', validateRecipient);
      messageInput?.addEventListener('input', updateMessageCounters);

      // Initialize counters
      updateMessageCounters();

      clearBtn?.addEventListener('click', function () {
        recipientInput.value = '';
        messageInput.value = '';
        recipientError.textContent = '';
        updateMessageCounters();
      });

      // Local storage keys
      const LS_TEMPLATES = 'sms_templates';
      const LS_HISTORY = 'sms_history';

      function readLS(key, fallback) {
        try {
          const raw = localStorage.getItem(key);
          if (!raw) return fallback;
          const val = JSON.parse(raw);
          return Array.isArray(val) || typeof val === 'object' ? val : fallback;
        } catch {
          return fallback;
        }
      }
      function writeLS(key, val) {
        try {
          localStorage.setItem(key, JSON.stringify(val));
        } catch {}
      }

      // Templates logic
      const templatesState = document.getElementById('templatesState');
      const templatesTbody = document.getElementById('templatesTbody');
      const templatesCards = document.getElementById('templatesCards');
      const templateSearch = document.getElementById('templateSearch');
      const templateSort = document.getElementById('templateSort');
      const newTemplateBtn = document.getElementById('newTemplateBtn');

      const templateModal = document.getElementById('templateModal');
      const templateModalClose = document.getElementById('templateModalClose');
      const templateCancelBtn = document.getElementById('templateCancelBtn');
      const templateSaveBtn = document.getElementById('templateSaveBtn');
      const templateForm = document.getElementById('templateForm');
      const templateId = document.getElementById('templateId');
      const templateName = document.getElementById('templateName');
      const templateNameErr = document.getElementById('templateNameErr');
      const templateContent = document.getElementById('templateContent');
      const templateContentErr = document.getElementById('templateContentErr');

      function openTemplateModal(editing) {
        document.getElementById('templateModalTitle').textContent = editing ? 'Edit Template' : 'New Template';
        templateModal.classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(() => templateName.focus(), 0);
      }
      function closeTemplateModal() {
        templateModal.classList.remove('active');
        document.body.style.overflow = 'auto';
        templateId.value = '';
        templateName.value = '';
        templateContent.value = '';
        templateNameErr.textContent = '';
        templateContentErr.textContent = '';
      }

      templateModalClose?.addEventListener('click', closeTemplateModal);
      templateCancelBtn?.addEventListener('click', closeTemplateModal);

      newTemplateBtn?.addEventListener('click', function () {
        openTemplateModal(false);
      });

      function validateTemplateForm() {
        let ok = true;
        if (!templateName.value.trim()) {
          templateNameErr.textContent = 'Name is required.';
          ok = false;
        } else templateNameErr.textContent = '';
        if (!templateContent.value.trim()) {
          templateContentErr.textContent = 'Content is required.';
          ok = false;
        } else templateContentErr.textContent = '';
        return ok;
      }

      templateSaveBtn?.addEventListener('click', function () {
        if (!validateTemplateForm()) return;
        const now = new Date().toISOString();
        const id = templateId.value || ('tmpl_' + Date.now());
        const record = {
          id,
          name: templateName.value.trim(),
          content: templateContent.value.trim(),
          updatedAt: now
        };
        const data = readLS(LS_TEMPLATES, []);
        const idx = data.findIndex(t => t.id === id);
        if (idx === -1) data.push(record);
        else data[idx] = record;
        writeLS(LS_TEMPLATES, data);
        renderTemplates();
        closeTemplateModal();
        if (window.Swal) Swal.fire({ icon: 'success', title: 'Saved', text: 'Template saved.', timer: 1200, showConfirmButton: false, heightAuto: false });
      });

      function editTemplate(id) {
        const data = readLS(LS_TEMPLATES, []);
        const item = data.find(t => t.id === id);
        if (!item) return;
        templateId.value = item.id;
        templateName.value = item.name;
        templateContent.value = item.content;
        openTemplateModal(true);
      }

      function deleteTemplate(id) {
        const data = readLS(LS_TEMPLATES, []);
        const next = data.filter(t => t.id !== id);
        writeLS(LS_TEMPLATES, next);
        renderTemplates();
        if (window.Swal) Swal.fire({ icon: 'success', title: 'Deleted', text: 'Template deleted.', timer: 1000, showConfirmButton: false, heightAuto: false });
      }

      templateSearch?.addEventListener('input', renderTemplates);
      templateSort?.addEventListener('change', renderTemplates);

      function summarize(text) {
        if (!text) return '';
        const t = text.replace(/\s+/g, ' ').trim();
        return t.length > 80 ? t.slice(0, 80) + '…' : t;
      }

      function sortTemplates(arr) {
        const mode = templateSort.value;
        const copy = arr.slice();
        switch (mode) {
          case 'updated_asc': return copy.sort((a,b) => a.updatedAt.localeCompare(b.updatedAt));
          case 'name_asc': return copy.sort((a,b) => a.name.localeCompare(b.name));
          case 'name_desc': return copy.sort((a,b) => b.name.localeCompare(a.name));
          case 'updated_desc':
          default: return copy.sort((a,b) => b.updatedAt.localeCompare(a.updatedAt));
        }
      }

      function renderTemplates() {
        const q = (templateSearch.value || '').toLowerCase();
        const all = readLS(LS_TEMPLATES, []);
        const filtered = sortTemplates(all.filter(t => (t.name || '').toLowerCase().includes(q) || (t.content || '').toLowerCase().includes(q)));

        const table = document.getElementById('templatesTable');
        const cards = document.getElementById('templatesCards');

        // States
        templatesState.style.display = filtered.length === 0 ? 'block' : 'none';
        templatesState.textContent = filtered.length === 0 ? 'No templates found. Create a new one to get started.' : '';

        // Table body
        templatesTbody.innerHTML = '';
        filtered.forEach(t => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${escapeHtml(t.name)}</td>
            <td>${escapeHtml(summarize(t.content))}</td>
            <td>${formatDate(t.updatedAt)}</td>
            <td>
              <button class="btn-secondary" data-edit="${t.id}" aria-label="Edit template">Edit</button>
              <button class="btn-secondary" data-delete="${t.id}" aria-label="Delete template">Delete</button>
              <button class="btn-primary" data-apply="${t.id}" aria-label="Use template">Use</button>
            </td>
          `;
          templatesTbody.appendChild(tr);
        });

        // Cards for small screens
        cards.innerHTML = '';
        filtered.forEach(t => {
          const card = document.createElement('div');
          card.className = 'card';
          card.innerHTML = `
            <div class="card-row"><span class="card-label">Name</span><span class="card-value">${escapeHtml(t.name)}</span></div>
            <div class="card-row"><span class="card-label">Preview</span><span class="card-value">${escapeHtml(summarize(t.content))}</span></div>
            <div class="card-row"><span class="card-label">Updated</span><span class="card-value">${formatDate(t.updatedAt)}</span></div>
            <div class="card-row">
              <span class="card-label">Actions</span>
              <span class="card-value">
                <button class="btn-secondary" data-edit="${t.id}" aria-label="Edit template">Edit</button>
                <button class="btn-secondary" data-delete="${t.id}" aria-label="Delete template">Delete</button>
                <button class="btn-primary" data-apply="${t.id}" aria-label="Use template">Use</button>
              </span>
            </div>
          `;
          cards.appendChild(card);
        });

        // Wire actions
        document.querySelectorAll('[data-edit]').forEach(btn => btn.addEventListener('click', () => editTemplate(btn.getAttribute('data-edit'))));
        document.querySelectorAll('[data-delete]').forEach(btn => btn.addEventListener('click', () => deleteTemplate(btn.getAttribute('data-delete'))));
        document.querySelectorAll('[data-apply]').forEach(btn => btn.addEventListener('click', () => applyTemplate(btn.getAttribute('data-apply'))));
      }

      function applyTemplate(id) {
        const data = readLS(LS_TEMPLATES, []);
        const item = data.find(t => t.id === id);
        if (!item) return;
        setActiveTab(0, false);
        const msg = item.content || '';
        messageInput.value = msg;
        updateMessageCounters();
        messageInput.focus();
        if (window.Swal) Swal.fire({ icon: 'success', title: 'Template applied', text: 'Message updated from template.', timer: 1000, showConfirmButton: false, heightAuto: false });
      }

      // History logic
      const historyState = document.getElementById('historyState');
      const historyTbody = document.getElementById('historyTbody');
      const historyCards = document.getElementById('historyCards');
      const historySearch = document.getElementById('historySearch');
      const historyStatus = document.getElementById('historyStatus');
      const historySort = document.getElementById('historySort');
      const prevPageBtn = document.getElementById('prevPageBtn');
      const nextPageBtn = document.getElementById('nextPageBtn');
      const pageInfo = document.getElementById('pageInfo');

      let historyPage = 1;
      const pageSize = 10;
      let historyData = []; // Store fetched history data

      historySearch?.addEventListener('input', () => { historyPage = 1; loadSmsHistory(); });
      historyStatus?.addEventListener('change', () => { historyPage = 1; loadSmsHistory(); });
      historySort?.addEventListener('change', () => { historyPage = 1; loadSmsHistory(); });
      prevPageBtn?.addEventListener('click', () => { historyPage = Math.max(1, historyPage - 1); loadSmsHistory(); });
      nextPageBtn?.addEventListener('click', () => { historyPage += 1; loadSmsHistory(); });

      // Load SMS history from API
      async function loadSmsHistory() {
        try {
          // Show loading state
          historyState.style.display = 'block';
          historyState.textContent = 'Loading...';
          historyTbody.innerHTML = '';
          historyCards.innerHTML = '';

          // Build query parameters
          const params = new URLSearchParams({
            action: 'get_history',
            page: historyPage,
            limit: pageSize
          });

          const search = historySearch?.value?.trim();
          if (search) params.append('search', search);

          const status = historyStatus?.value;
          if (status) params.append('status', status);

          // Fetch from API
          const response = await fetch(`../../api/sms_history_api.php?${params.toString()}`);
          const data = await response.json();

          if (data.success) {
            historyData = data.data;
            renderHistory(data.data, data.pagination);
          } else {
            historyState.style.display = 'block';
            historyState.textContent = 'Error loading history: ' + (data.message || 'Unknown error');
          }
        } catch (error) {
          console.error('Error loading SMS history:', error);
          historyState.style.display = 'block';
          historyState.textContent = 'Error loading history. Please try again.';
        }
      }

      function renderHistory(smsData, pagination) {
        // Update pagination info
        if (pagination) {
          const totalPages = pagination.total_pages || 1;
          pageInfo.textContent = `Page ${pagination.current_page} of ${totalPages}`;

          // Update button states
          if (prevPageBtn) prevPageBtn.disabled = pagination.current_page <= 1;
          if (nextPageBtn) nextPageBtn.disabled = pagination.current_page >= totalPages;
        }

        // State
        historyState.style.display = smsData.length === 0 ? 'block' : 'none';
        historyState.textContent = smsData.length === 0 ? 'No messages found.' : '';

        // Table
        historyTbody.innerHTML = '';
        smsData.forEach(h => {
          const tr = document.createElement('tr');
          const statusClass = h.status === 'sent' ? 'success' : (h.status === 'failed' ? 'error' : 'pending');
          tr.innerHTML = `
            <td>${escapeHtml(h.recipient)}</td>
            <td><span class="status-badge ${statusClass}">${escapeHtml(h.status.toUpperCase())}</span></td>
            <td>${h.formatted_date}</td>
            <td><button class="btn-secondary" data-view="${h.id}" aria-label="View details">View</button></td>
          `;
          historyTbody.appendChild(tr);
        });

        // Cards
        historyCards.innerHTML = '';
        smsData.forEach(h => {
          const card = document.createElement('div');
          card.className = 'card';
          const statusClass = h.status === 'sent' ? 'success' : (h.status === 'failed' ? 'error' : 'pending');
          card.innerHTML = `
            <div class="card-row"><span class="card-label">Recipient</span><span class="card-value">${escapeHtml(h.recipient)}</span></div>
            <div class="card-row"><span class="card-label">Status</span><span class="card-value"><span class="status-badge ${statusClass}">${escapeHtml(h.status.toUpperCase())}</span></span></div>
            <div class="card-row"><span class="card-label">Date/Time</span><span class="card-value">${h.formatted_date}</span></div>
            <div class="card-row"><span class="card-label">Actions</span><span class="card-value"><button class="btn-secondary" data-view="${h.id}" aria-label="View details">View</button></span></div>
          `;
          historyCards.appendChild(card);
        });

        document.querySelectorAll('[data-view]').forEach(btn => btn.addEventListener('click', () => openDrawer(btn.getAttribute('data-view'))));
      }

      // Drawer details
      const drawerOverlay = document.getElementById('drawerOverlay');
      const drawerCloseBtn = document.getElementById('drawerCloseBtn');
      const drawerContent = document.getElementById('drawerContent');

      async function openDrawer(id) {
        try {
          // Show loading in drawer
          drawerContent.innerHTML = '<div style="text-align:center;padding:20px;">Loading...</div>';
          drawerOverlay.style.display = 'flex';
          drawerOverlay.setAttribute('aria-hidden', 'false');

          // Fetch SMS details from API
          const response = await fetch(`../../api/sms_history_api.php?action=get_sms_details&sms_id=${id}`);
          const data = await response.json();

          if (data.success) {
            const item = data.data;
            const statusClass = item.status === 'sent' ? 'success' : (item.status === 'failed' ? 'error' : 'pending');
            const deliveryClass = item.delivery_status === 'delivered' ? 'success' : (item.delivery_status === 'failed' ? 'error' : 'pending');

            drawerContent.innerHTML = `
              <div style="display:grid;gap:10px">
                <div><strong>Recipient:</strong> ${escapeHtml(item.recipient)}</div>
                <div><strong>Sender:</strong> ${escapeHtml(item.sender_name)}</div>
                <div><strong>Sender ID:</strong> ${escapeHtml(item.sender_id_name)}</div>
                <div><strong>Status:</strong> <span class="status-badge ${statusClass}">${escapeHtml(item.status.toUpperCase())}</span></div>
                <div><strong>Delivery Status:</strong> <span class="status-badge ${deliveryClass}">${escapeHtml(item.delivery_status.toUpperCase())}</span></div>
                <div><strong>Sent:</strong> ${item.formatted_date}</div>
                ${item.delivered_at ? `<div><strong>Delivered:</strong> ${item.formatted_delivered_at}</div>` : ''}
                <div><strong>Message Length:</strong> ${item.message_length} characters</div>
                <div><strong>Segments:</strong> ${item.segment_count}</div>
                <div><strong>Encoding:</strong> ${item.is_unicode ? 'Unicode' : 'GSM-7'}</div>
                <div><strong>Provider:</strong> ${escapeHtml(item.provider)}</div>
                ${item.error_message ? `<div><strong>Error:</strong> <span style="color:#dc143c;">${escapeHtml(item.error_message)}</span></div>` : ''}
                <div><strong>Message:</strong><br><pre style="white-space:pre-wrap;font-size:13px;color:var(--text-dark);background:var(--primary-light);padding:10px;border-radius:8px;border:1px solid var(--border-light)">${escapeHtml(item.message)}</pre></div>
              </div>
            `;
          } else {
            drawerContent.innerHTML = `<div style="text-align:center;padding:20px;color:#dc143c;">Error: ${escapeHtml(data.message || 'Failed to load details')}</div>`;
          }
        } catch (error) {
          console.error('Error loading SMS details:', error);
          drawerContent.innerHTML = '<div style="text-align:center;padding:20px;color:#dc143c;">Error loading details. Please try again.</div>';
        }
      }

      function closeDrawer() {
        drawerOverlay.style.display = 'none';
        drawerOverlay.setAttribute('aria-hidden', 'true');
      }
      drawerCloseBtn?.addEventListener('click', closeDrawer);
      drawerOverlay?.addEventListener('click', (e) => { if (e.target === drawerOverlay) closeDrawer(); });

      // Utilities
      function escapeHtml(s) {
        return (s || '').replace(/[&<>"]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
      }
      function formatDate(iso) {
        try {
          const d = new Date(iso);
          return d.toLocaleString('en-PH', { year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
        } catch { return iso; }
      }

      // Initial load states (simulate minimal loading without mock data)
      function showLoading(container, ms = 300) {
        container.style.display = 'block';
        container.innerHTML = '';
        const sk = document.createElement('div');
        sk.className = 'skeleton';
        sk.innerHTML = '<div class="bar"></div><div class="bar"></div><div class="bar"></div>';
        container.appendChild(sk);
        return new Promise(res => setTimeout(res, ms));
      }

      (async function initTemplates() {
        await showLoading(templatesState, 250);
        templatesState.style.display = 'none';
        renderTemplates();
      })();

      // History will be loaded when tab is activated (see setActiveTab function)

      // Send handler (integrated with backend API)
      document.getElementById('sendForm')?.addEventListener('submit', async function (e) {
        // DEBUG LOG: Form submit triggered
        console.log('[SMS DEBUG] Submit event triggered', e); // DEBUG LOG

        e.preventDefault();
        if (!validateRecipient()) {
          // DEBUG LOG: Recipient validation failed
          console.log('[SMS DEBUG] Recipient validation failed', recipientInput.value); // DEBUG LOG
          recipientInput.focus();
          return;
        }
        const message = (messageInput.value || '').trim();
        if (!message) {
          // DEBUG LOG: Message is empty
          console.log('[SMS DEBUG] Message is empty'); // DEBUG LOG
          if (window.Swal) Swal.fire({ icon: 'error', title: 'Message required', text: 'Please enter a message.', heightAuto: false });
          else alert('Please enter a message.');
          return;
        }

        // UI submitting state
        sendBtn.disabled = true;
        sendBtn.textContent = 'Sending...';

        // DEBUG LOG: Preparing payload
        console.log('[SMS DEBUG] Preparing payload', {
          numbers: [recipientInput.value.trim()],
          message: message
        }); // DEBUG LOG

        // Prepare payload
        const numbers = [recipientInput.value.trim()];

        // DEBUG LOG: Type and value of numbers before fetch
        console.log('[SMS DEBUG] About to send fetch. numbers type:', Array.isArray(numbers) ? 'array' : typeof numbers, '| value:', numbers);

        try {
          // DEBUG LOG: Sending fetch request
          console.log('[SMS DEBUG] Sending fetch request to ../../api/send_sms.php', {
            numbers, message
          }); // DEBUG LOG

          const response = await fetch('../../api/send_sms.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              numbers: numbers,
              message: message
            })
          });

          // DEBUG LOG: Received response
          console.log('[SMS DEBUG] Received response', response); // DEBUG LOG

          const data = await response.json();

          // DEBUG LOG: Parsed response JSON
          console.log('[SMS DEBUG] Parsed response JSON', data); // DEBUG LOG

          sendBtn.disabled = false;
          sendBtn.textContent = 'Send';

          if (response.ok && data && data.success) {
            // DEBUG LOG: SMS sent successfully
            console.log('[SMS DEBUG] SMS sent successfully', data); // DEBUG LOG
            if (window.Swal) {
              Swal.fire({
                icon: 'success',
                title: 'Message sent',
                text: data.message || 'Your SMS has been queued for delivery.',
                timer: 1200,
                showConfirmButton: false,
                heightAuto: false
              });
            }
            // Optionally clear message only
            messageInput.value = '';
            updateMessageCounters();
            // Reload history if on history tab
            if (activeTabIndex === 2) {
              loadSmsHistory();
            }
          } else {
            // DEBUG LOG: SMS send failed
            console.log('[SMS DEBUG] SMS send failed', data); // DEBUG LOG
            let errorMsg = (data && data.error) ? data.error : 'Failed to send SMS. Please try again.';
            if (window.Swal) {
              Swal.fire({
                icon: 'error',
                title: 'Send failed',
                text: errorMsg,
                heightAuto: false
              });
            } else {
              alert(errorMsg);
            }
          }
        } catch (err) {
          // DEBUG LOG: Network or fetch error
          console.log('[SMS DEBUG] Network or fetch error', err); // DEBUG LOG
          sendBtn.disabled = false;
          sendBtn.textContent = 'Send';
          if (window.Swal) {
            Swal.fire({
              icon: 'error',
              title: 'Network error',
              text: 'Could not connect to SMS service. Please try again.',
              heightAuto: false
            });
          } else {
            alert('Could not connect to SMS service. Please try again.');
          }
        }
      });

    })();
  </script>
</body>
</html>