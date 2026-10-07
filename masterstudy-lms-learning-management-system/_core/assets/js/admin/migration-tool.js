"use strict";

(function () {
  'use strict';

  var config = window.masterstudyMigration || {};
  var strings = config.strings || {};
  var stepLabels = config.steps || {};
  var reportGroups = config.reportGroups || {};
  var groupLabels = config.groups || {};
  var contentLabels = config.contentLabels || {};
  var REQUIRED_GROUP = 'content';
  var TERMINAL = ['completed', 'failed', 'cancelled'];
  var ICONS = {
    check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"></path></svg>',
    checkCircle: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M8 12.5l2.5 2.5L16 9.5"></path></svg>',
    spinner: '<svg class="masterstudy-migration__spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 3a9 9 0 1 1-9 9"></path></svg>',
    clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="8"></circle><path d="M12 8v4l3 2"></path></svg>',
    dash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M7 12h10"></path></svg>',
    cross: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5M12 8v.01"></path></svg>',
    warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l10 18H2zM12 10v5M12 18v.01"></path></svg>',
    database: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><ellipse cx="12" cy="6" rx="7" ry="3"></ellipse><path d="M5 6v12c0 1.7 3.1 3 7 3s7-1.3 7-3V6M5 12c0 1.7 3.1 3 7 3s7-1.3 7-3"></path></svg>',
    arrow: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"></path></svg>',
    external: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"></path></svg>',
    plug: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 7V3M15 7V3M7 7h10v4a5 5 0 0 1-10 0zM12 16v5"></path></svg>',
    validCircle: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor"></circle><path d="M7.5 12.5l3 3 6-6.5" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path></svg>'
  };
  function sprintf(format) {
    var args = Array.prototype.slice.call(arguments, 1);
    var index = 0;
    return String(format || '').replace(/%(\d+)\$[sd]/g, function (match, position) {
      return args[position - 1] !== undefined ? args[position - 1] : '';
    }).replace(/%[sd]/g, function () {
      var value = args[index];
      index++;
      return value !== undefined ? value : '';
    });
  }
  function esc(value) {
    var div = document.createElement('div');
    div.textContent = value === undefined || value === null ? '' : String(value);
    return div.innerHTML;
  }
  function request(method, path, body) {
    return fetch(config.restUrl + path, {
      method: method,
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': config.nonce,
        'Content-Type': 'application/json'
      },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (response) {
      return response.json()["catch"](function () {
        return {};
      }).then(function (data) {
        if (!response.ok) {
          var error = new Error(data && data.message || response.statusText);
          error.data = data;
          throw error;
        }
        return data;
      });
    });
  }

  /**
   * REST path with query args (the REST base may already contain "?rest_route=").
   */
  function withQuery(path, params) {
    var query = Object.keys(params).map(function (key) {
      return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
    }).join('&');
    return path + ((config.restUrl + path).indexOf('?') === -1 ? '?' : '&') + query;
  }
  function number(value) {
    return (parseInt(value, 10) || 0).toLocaleString();
  }
  function pad(number) {
    return number < 10 ? '0' + number : String(number);
  }
  function formatElapsed(seconds) {
    seconds = Math.max(0, parseInt(seconds, 10) || 0);
    var hours = Math.floor(seconds / 3600);
    var minutes = Math.floor(seconds % 3600 / 60);
    return (hours ? hours + ':' + pad(minutes) : pad(minutes)) + ':' + pad(seconds % 60);
  }
  function uniqueLabels(steps) {
    var seen = {};
    return (steps || []).map(function (step) {
      return stepLabels[step] || step;
    }).filter(function (label) {
      if (seen[label]) {
        return false;
      }
      seen[label] = true;
      return true;
    });
  }

  /**
   * The migration app. Renders into `root` and keeps its own state; all data comes from
   * the masterstudy-lms/v2/migrations REST routes.
   */
  function App(root) {
    this.root = root;
    this.state = {
      view: 'loading',
      // loading | select | run
      sources: [],
      selected: '',
      confirmOpen: false,
      typed: '',
      starting: false,
      sessionId: '',
      status: null,
      cancelling: false,
      showLog: false,
      banner: null,
      completed: {},
      notice: null,
      pollError: false,
      reportGroup: '',
      reportItems: {},
      reportLoading: false,
      reportError: false,
      preview: {},
      previewLoading: '',
      previewError: '',
      groups: {},
      undoOpen: false,
      undoSlug: '',
      undoSummary: null,
      undoLoading: false,
      undoTyped: '',
      undoRunning: false,
      undoDeleted: 0,
      undoError: ''
    };
    this.pollTimer = null;
    root.addEventListener('click', this.onClick.bind(this));
    root.addEventListener('input', this.onInput.bind(this));
    root.addEventListener('keydown', this.onKeydown.bind(this));
    this.load();
  }
  App.prototype.set = function (changes) {
    for (var key in changes) {
      if (Object.prototype.hasOwnProperty.call(changes, key)) {
        this.state[key] = changes[key];
      }
    }
    this.render();
  };
  App.prototype.sourceLabel = function (slug) {
    var source = this.state.sources.filter(function (item) {
      return item.name === slug;
    })[0];
    return source ? source.label : slug;
  };
  App.prototype.load = function () {
    var self = this;
    Promise.all([request('GET', '/migrations/lms'), request('GET', '/migrations/active')]).then(function (responses) {
      var sources = responses[0] && responses[0].data || [];
      var active = responses[1] && responses[1].data;
      var last = responses[1] && responses[1].last_completed;
      var completed = responses[1] && responses[1].completed || {};
      sources.sort(function (a, b) {
        return (b.active ? 1 : 0) - (a.active ? 1 : 0);
      });
      self.state.sources = sources;
      self.state.completed = completed;
      if (active && active.session_id) {
        self.set({
          view: 'run',
          sessionId: active.session_id,
          status: active
        });
        self.poll();
        return;
      }
      var lastActive = !!last && sources.some(function (source) {
        return source.name === last.lms_slug && source.active;
      });

      // A deactivated LMS keeps no banner: the note under the LMS cards offers its report and undo.
      self.set({
        view: 'select',
        banner: lastActive && last.lms_label ? {
          label: last.lms_label,
          sessionId: last.session_id
        } : null
      });
    })["catch"](function () {
      self.set({
        view: 'select',
        notice: strings.loadError
      });
    });
  };
  App.prototype.poll = function () {
    var self = this;
    window.clearTimeout(this.pollTimer);
    if (!this.state.sessionId) {
      return;
    }
    request('GET', '/migrations/' + this.state.sessionId).then(function (status) {
      self.set({
        status: status,
        pollError: false
      });
      if (TERMINAL.indexOf(status.status) === -1) {
        self.pollTimer = window.setTimeout(self.poll.bind(self), config.pollInterval || 2000);
      } else {
        self.set({
          cancelling: false
        });
      }
    })["catch"](function () {
      self.set({
        pollError: true
      });
      self.pollTimer = window.setTimeout(self.poll.bind(self), (config.pollInterval || 2000) * 2);
    });
  };
  App.prototype.isConfirmValid = function () {
    return this.state.typed.trim().toLowerCase() === String(config.confirmKeyword || 'confirm');
  };
  App.prototype.start = function () {
    var self = this;
    if (!this.isConfirmValid() || this.state.starting) {
      return;
    }
    this.set({
      starting: true
    });
    request('POST', '/migrations/start', {
      lms_name: this.state.selected,
      groups: this.selectedGroups()
    }).then(function (data) {
      self.set({
        starting: false,
        confirmOpen: false,
        view: 'run',
        sessionId: data.session_id,
        status: null,
        showLog: false,
        banner: null,
        notice: null,
        reportGroup: '',
        reportItems: {}
      });
      self.poll();
    })["catch"](function (error) {
      self.set({
        starting: false,
        confirmOpen: false,
        notice: error.message || strings.startError
      });
    });
  };
  App.prototype.cancel = function () {
    var self = this;
    if (!this.state.sessionId || this.state.cancelling || !window.confirm(strings.cancelConfirm)) {
      return;
    }
    this.set({
      cancelling: true
    });
    request('DELETE', '/migrations/' + this.state.sessionId).then(function () {
      self.poll();
    })["catch"](function (error) {
      self.set({
        cancelling: false,
        notice: error.message || strings.cancelError
      });
      self.poll();
    });
  };
  App.prototype.onClick = function (event) {
    var target = event.target.closest('[data-action]');
    if (!target || !this.root.contains(target)) {
      return;
    }
    var action = target.getAttribute('data-action');
    switch (action) {
      case 'pick':
        if (!target.disabled) {
          this.set({
            selected: target.getAttribute('data-source')
          });
          this.loadPreview(target.getAttribute('data-source'));
        }
        break;
      case 'toggle-group':
        this.state.groups[target.getAttribute('data-group')] = target.checked;
        this.render();
        break;
      case 'open-undo':
        this.openUndo(target.getAttribute('data-source'));
        break;
      case 'close-undo':
        if (!this.state.undoRunning) {
          this.set({
            undoOpen: false
          });
        }
        break;
      case 'confirm-undo':
        this.runUndo();
        break;
      case 'open-confirm':
        if (this.state.selected) {
          this.set({
            confirmOpen: true,
            typed: ''
          });
          var input = this.root.querySelector('.masterstudy-migration__confirm-input');
          if (input) {
            input.focus();
          }
        }
        break;
      case 'close-confirm':
        this.set({
          confirmOpen: false
        });
        break;
      case 'confirm-start':
        this.start();
        break;
      case 'cancel-migration':
        this.cancel();
        break;
      case 'toggle-log':
        event.preventDefault();
        this.set({
          showLog: !this.state.showLog
        });
        break;
      case 'migrate-another':
        var label = this.state.status && 'completed' === this.state.status.status ? this.sourceLabel(this.state.status.lms_slug) : '';
        this.set({
          view: 'select',
          sessionId: '',
          status: null,
          selected: '',
          showLog: false,
          reportGroup: '',
          reportItems: {},
          banner: label ? {
            label: label
          } : null
        });
        this.load();
        break;
      case 'run-again':
        var slug = this.state.status ? this.state.status.lms_slug : '';
        this.set({
          view: 'select',
          sessionId: '',
          status: null,
          selected: slug,
          showLog: false,
          reportGroup: '',
          reportItems: {},
          banner: null,
          notice: null
        });
        this.loadPreview(slug, true);
        break;
      case 'open-session':
        event.preventDefault();
        this.openSession(target.getAttribute('data-session'));
        break;
      case 'open-report':
        this.openReport(target.getAttribute('data-group'));
        break;
      case 'close-report':
        this.set({
          reportGroup: ''
        });
        break;
      case 'dismiss-banner':
        this.set({
          banner: null
        });
        break;
      case 'dismiss-notice':
        this.set({
          notice: null
        });
        break;
    }
  };

  /**
   * Reopen the result + report of a finished migration (the latest one of each LMS is kept).
   */
  App.prototype.openSession = function (sessionId) {
    if (!sessionId) {
      return;
    }
    this.set({
      view: 'run',
      sessionId: sessionId,
      status: null,
      showLog: false,
      banner: null,
      notice: null,
      reportGroup: '',
      reportItems: {}
    });
    this.poll();
  };
  App.prototype.openReport = function (group) {
    var self = this;
    var status = this.state.status;
    if (!group || !this.state.sessionId) {
      return;
    }
    if (this.state.reportGroup === group) {
      this.set({
        reportGroup: ''
      });
      return;
    }

    // Cached items are reused only when the group total has not changed since.
    var cached = this.state.reportItems[group];
    var total = status && status.report && status.report[group] ? status.report[group].total : 0;
    if (cached && cached.total === total) {
      this.set({
        reportGroup: group,
        reportError: false
      });
      return;
    }
    this.set({
      reportGroup: group,
      reportLoading: true,
      reportError: false
    });
    request('GET', '/migrations/' + this.state.sessionId + '/report/' + encodeURIComponent(group)).then(function (data) {
      self.state.reportItems[group] = {
        total: total,
        items: data.items || []
      };
      self.set({
        reportLoading: false
      });
    })["catch"](function () {
      self.set({
        reportLoading: false,
        reportError: true
      });
    });
  };
  App.prototype.onInput = function (event) {
    if (event.target.classList.contains('masterstudy-migration__confirm-input')) {
      this.state.typed = event.target.value;
      this.renderConfirmState();
    }
    if (event.target.classList.contains('masterstudy-migration__undo-input')) {
      this.state.undoTyped = event.target.value;
      this.renderUndoState();
    }
  };
  App.prototype.onKeydown = function (event) {
    if (this.state.undoOpen) {
      if ('Escape' === event.key && !this.state.undoRunning) {
        this.set({
          undoOpen: false
        });
      }
      if ('Enter' === event.key && event.target.classList.contains('masterstudy-migration__undo-input')) {
        event.preventDefault();
        this.runUndo();
      }
      return;
    }
    if (!this.state.confirmOpen) {
      return;
    }
    if ('Escape' === event.key) {
      this.set({
        confirmOpen: false
      });
    }
    if ('Enter' === event.key && event.target.classList.contains('masterstudy-migration__confirm-input')) {
      event.preventDefault();
      this.start();
    }
  };

  /* ---------------------------------------------------------------------
   * Rendering
   * ------------------------------------------------------------------- */

  App.prototype.render = function () {
    var s = this.state;
    var html = this.renderHeader();
    if (s.notice) {
      html += '<div class="masterstudy-migration__alert masterstudy-migration__alert_error" role="alert">' + '<span class="masterstudy-migration__alert-text">' + esc(s.notice) + '</span>' + '<button type="button" class="masterstudy-migration__alert-close" data-action="dismiss-notice" aria-label="' + esc(strings.dismiss) + '">' + ICONS.cross + '</button>' + '</div>';
    }
    if ('loading' === s.view) {
      html += '<div class="masterstudy-migration__card masterstudy-migration__card_loading">' + ICONS.spinner + '</div>';
    } else if ('run' === s.view) {
      html += this.renderRun();
    } else {
      if (s.banner) {
        html += '<div class="masterstudy-migration__banner" role="status">' + ICONS.checkCircle + '<span class="masterstudy-migration__banner-text">' + esc(sprintf(s.banner.undone ? strings.undoDone : strings.doneBanner, s.banner.label)).replace(esc(s.banner.label), '<strong>' + esc(s.banner.label) + '</strong>') + '</span>' + (s.banner.sessionId && !s.banner.undone ? '<a href="#" data-action="open-session" data-session="' + esc(s.banner.sessionId) + '">' + esc(strings.viewReport) + '</a>' : '') + '<a href="' + esc(config.coursesUrl) + '">' + esc(strings.viewCourses) + '</a>' + '<button type="button" class="masterstudy-migration__alert-close" data-action="dismiss-banner" aria-label="' + esc(strings.dismiss) + '">' + ICONS.cross + '</button>' + '</div>';
      }
      html += this.renderSelect();
    }
    html += this.renderConfirm();
    html += this.renderUndo();
    var focused = document.activeElement && this.root.contains(document.activeElement) && document.activeElement.classList.contains('masterstudy-migration__confirm-input');
    var undoFocused = document.activeElement && this.root.contains(document.activeElement) && document.activeElement.classList.contains('masterstudy-migration__undo-input');
    this.root.innerHTML = html;
    this.renderConfirmState();
    this.renderUndoState();
    if ((undoFocused || this.state.undoOpen) && !this.state.undoRunning) {
      var undoInput = this.root.querySelector('.masterstudy-migration__undo-input');
      if (undoInput) {
        undoInput.focus();
        undoInput.setSelectionRange(undoInput.value.length, undoInput.value.length);
      }
      return;
    }
    if (focused || this.state.confirmOpen) {
      var input = this.root.querySelector('.masterstudy-migration__confirm-input');
      if (input) {
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
      }
    }
  };
  App.prototype.renderHeader = function () {
    return '<div class="masterstudy-migration__header">' + '<div class="masterstudy-migration__heading">' + '<h1 class="masterstudy-migration__title">' + esc(strings.title) + '</h1>' + '<p class="masterstudy-migration__subtitle">' + esc(strings.subtitle) + '</p>' + '</div>' + (config.guideUrl ? '<a class="masterstudy-migration__guide" href="' + esc(config.guideUrl) + '" target="_blank" rel="noopener">' + esc(strings.guide) + '</a>' : '') + '</div>';
  };
  App.prototype.renderSelect = function () {
    var self = this;
    var s = this.state;
    var cards = s.sources.map(function (source) {
      var selected = source.name === s.selected;
      var classes = ['masterstudy-migration__source'];
      if (selected) {
        classes.push('masterstudy-migration__source_selected');
      }
      if (!source.active) {
        classes.push('masterstudy-migration__source_disabled');
      }
      var chips = uniqueLabels(source.steps).map(function (label) {
        return '<span class="masterstudy-migration__chip">' + esc(label) + '</span>';
      }).join('');
      return '<button type="button" role="radio" class="' + classes.join(' ') + '" data-action="pick" data-source="' + esc(source.name) + '"' + ' aria-checked="' + (selected ? 'true' : 'false') + '"' + (source.active ? '' : ' disabled') + '>' + '<span class="masterstudy-migration__radio" aria-hidden="true"></span>' + '<span class="masterstudy-migration__source-info">' + '<span class="masterstudy-migration__source-name">' + esc(source.label) + '</span>' + '<span class="masterstudy-migration__badge masterstudy-migration__badge_' + (source.active ? 'active' : 'inactive') + '">' + esc(source.active ? strings.active : strings.notActive) + '</span>' + '</span>' + '<span class="masterstudy-migration__chips">' + chips + '</span>' + '</button>';
    }).join('');
    if (!s.sources.length) {
      cards = '<p class="masterstudy-migration__empty">' + esc(strings.noSources) + '</p>';
    }

    // Its card cannot be selected, but the copies of a deactivated LMS can still be reviewed and deleted.
    var inactiveCopies = s.sources.filter(function (source) {
      return !source.active && source.migrated > 0;
    }).map(function (source) {
      return '<div class="masterstudy-migration__already">' + ICONS.info + '<div class="masterstudy-migration__already-body"><span>' + esc(sprintf(strings.inactiveCopies, source.label, number(source.migrated))) + '</span></div>' + (s.completed[source.name] ? '<button type="button" class="masterstudy-migration__button" data-action="open-session" data-session="' + esc(s.completed[source.name]) + '">' + esc(strings.viewReport) + '</button>' : '') + '<button type="button" class="masterstudy-migration__button masterstudy-migration__button_danger" data-action="open-undo" data-source="' + esc(source.name) + '">' + esc(strings.undoButton) + '</button>' + '</div>';
    }).join('');
    var noneActive = s.sources.length && !s.sources.some(function (source) {
      return source.active;
    });
    var noneActiveWarning = noneActive ? '<div class="masterstudy-migration__alert masterstudy-migration__alert_warning" role="status">' + ICONS.warning + '<span class="masterstudy-migration__alert-text"><strong>' + esc(strings.noActiveTitle) + '</strong>' + esc(sprintf(strings.noActiveText, s.sources.map(function (source) {
      return source.label;
    }).join(', '))) + '</span>' + (config.pluginsUrl ? '<a class="masterstudy-migration__button" href="' + esc(config.pluginsUrl) + '">' + esc(strings.goToPlugins) + '</a>' : '') + '</div>' : '';
    return noneActiveWarning + '<section class="masterstudy-migration__card">' + '<div class="masterstudy-migration__card-header">' + '<h2 class="masterstudy-migration__card-title">' + esc(strings.beforeTitle) + '</h2>' + '</div>' + '<div class="masterstudy-migration__tips">' + self.renderTip('success', ICONS.database, strings.backupTitle, strings.backupText) + self.renderTip('info', ICONS.plug, strings.keepTitle, strings.keepText) + '</div>' + '</section>' + '<section class="masterstudy-migration__card">' + '<div class="masterstudy-migration__card-header">' + '<h2 class="masterstudy-migration__card-title">' + esc(strings.selectTitle) + '</h2>' + '<p class="masterstudy-migration__card-text">' + esc(strings.selectText) + '</p>' + '</div>' + '<div class="masterstudy-migration__sources" role="radiogroup" aria-label="' + esc(strings.selectTitle) + '">' + cards + '</div>' + inactiveCopies + self.renderUnsupported() + '</section>' + self.renderPreview() + '<div class="masterstudy-migration__start-row">' + '<button type="button" class="masterstudy-migration__button masterstudy-migration__button_primary masterstudy-migration__button_large" data-action="open-confirm"' + (s.selected ? '' : ' disabled') + '>' + esc(strings.start) + '</button>' + (s.selected ? '' : '<span class="masterstudy-migration__hint">' + esc(strings.selectHint) + '</span>') + '</div>';
  };

  /* ---------------------------------------------------------------------
   * Preview + "Choose what to migrate"
   * ------------------------------------------------------------------- */

  App.prototype.loadPreview = function (slug, force) {
    var self = this;
    if (!slug || !force && this.state.preview[slug] || this.state.previewLoading === slug) {
      return;
    }
    this.set({
      previewLoading: slug,
      previewError: ''
    });
    request('GET', withQuery('/migrations/preview', {
      lms: slug
    })).then(function (data) {
      self.state.preview[slug] = data;
      self.set({
        previewLoading: ''
      });
    })["catch"](function (error) {
      self.set({
        previewLoading: '',
        previewError: error.message || strings.previewError
      });
    });
  };

  /**
   * Group totals of the selected source (sum of its steps' item counts).
   */
  App.prototype.groupTotals = function (slug) {
    var totals = {};
    var data = this.state.preview[slug];
    (data && data.steps || []).forEach(function (step) {
      totals[step.group] = (totals[step.group] || 0) + (parseInt(step.total, 10) || 0);
    });
    return totals;
  };

  /**
   * Optional groups the admin kept checked (courses & content are always migrated).
   */
  App.prototype.selectedGroups = function () {
    var self = this;
    return Object.keys(this.groupTotals(this.state.selected)).filter(function (group) {
      return REQUIRED_GROUP !== group && false !== self.state.groups[group];
    });
  };
  App.prototype.renderPreview = function () {
    var s = this.state;
    var slug = s.selected;
    if (!slug) {
      return '';
    }
    var data = s.preview[slug];
    var head = '<div class="masterstudy-migration__card-header">' + '<h2 class="masterstudy-migration__card-title">' + esc(strings.previewTitle) + '</h2>' + '<p class="masterstudy-migration__card-text">' + esc(strings.previewText) + '</p>' + '</div>';
    if (!data) {
      return '<section class="masterstudy-migration__card">' + head + '<p class="masterstudy-migration__preview-status">' + (s.previewError ? esc(s.previewError) : ICONS.spinner + '<span>' + esc(strings.previewLoading) + '</span>') + '</p>' + '</section>';
    }
    var counts = Object.keys(contentLabels).filter(function (key) {
      return data.content && undefined !== data.content[key];
    }).map(function (key) {
      return '<div class="masterstudy-migration__count">' + '<strong class="masterstudy-migration__count-value">' + number(data.content[key]) + '</strong>' + '<span class="masterstudy-migration__count-label">' + esc(contentLabels[key]) + '</span>' + '</div>';
    }).join('');
    var totals = this.groupTotals(slug);
    var groups = Object.keys(groupLabels).filter(function (group) {
      return undefined !== totals[group];
    }).map(function (group) {
      var required = REQUIRED_GROUP === group;
      var checked = required || false !== s.groups[group];
      var id = 'masterstudy-migration-group-' + group;
      return '<li class="masterstudy-migration__group' + (checked ? '' : ' masterstudy-migration__group_off') + '">' + '<input type="checkbox" id="' + esc(id) + '" class="masterstudy-migration__group-input" data-action="toggle-group" data-group="' + esc(group) + '"' + (checked ? ' checked' : '') + (required ? ' disabled' : '') + '>' + '<label for="' + esc(id) + '" class="masterstudy-migration__group-label">' + esc(groupLabels[group]) + '</label>' + (required ? '<span class="masterstudy-migration__group-tag">' + esc(strings.required) + '</span>' : '') + '<span class="masterstudy-migration__group-count">' + esc(totals[group] ? sprintf(strings.itemsCount, number(totals[group])) : strings.noItems) + '</span>' + '</li>';
    }).join('');
    var progressOff = undefined !== totals.progress && false === s.groups.progress;
    var migrated = data.migrated || {};
    var already = '';
    if (migrated.posts > 0) {
      var courses = migrated.types && migrated.types['stm-courses'] || 0;
      already = '<div class="masterstudy-migration__already">' + ICONS.info + '<div class="masterstudy-migration__already-body">' + '<strong>' + esc(strings.alreadyTitle) + '</strong>' + '<span>' + esc(sprintf(strings.alreadyText, number(courses), number(migrated.posts - courses), this.sourceLabel(slug))) + '</span>' + '</div>' + (s.completed[slug] ? '<button type="button" class="masterstudy-migration__button" data-action="open-session" data-session="' + esc(s.completed[slug]) + '">' + esc(strings.viewReport) + '</button>' : '') + '<button type="button" class="masterstudy-migration__button masterstudy-migration__button_danger" data-action="open-undo" data-source="' + esc(slug) + '">' + esc(strings.undoButton) + '</button>' + '</div>';
    }
    return '<section class="masterstudy-migration__card masterstudy-migration__preview">' + head + (counts ? '<div class="masterstudy-migration__counts">' + counts + '</div>' : '') + '<ul class="masterstudy-migration__groups">' + groups + '</ul>' + (progressOff ? '<p class="masterstudy-migration__group-warning">' + ICONS.warning + '<span>' + esc(strings.noGroupsWarning) + '</span></p>' : '') + already + '</section>';
  };

  /* ---------------------------------------------------------------------
   * "Delete migrated data"
   * ------------------------------------------------------------------- */

  App.prototype.openUndo = function (slug) {
    var self = this;
    if (!slug) {
      return;
    }
    this.set({
      undoOpen: true,
      undoSlug: slug,
      undoSummary: null,
      undoLoading: true,
      undoTyped: '',
      undoError: '',
      undoDeleted: 0
    });
    request('GET', withQuery('/migrations/undo', {
      lms: slug
    })).then(function (summary) {
      self.set({
        undoSummary: summary,
        undoLoading: false
      });
    })["catch"](function (error) {
      self.set({
        undoLoading: false,
        undoError: error.message || strings.undoError
      });
    });
  };
  App.prototype.isUndoValid = function () {
    var summary = this.state.undoSummary;
    return !!summary && (summary.posts > 0 || summary.coupons > 0 || summary.plans > 0 || summary.roles > 0) && this.state.undoTyped.trim().toLowerCase() === String(config.undoKeyword || 'delete');
  };
  App.prototype.runUndo = function () {
    var self = this;
    var slug = this.state.undoSlug;
    if (!this.isUndoValid() || this.state.undoRunning) {
      return;
    }
    this.set({
      undoRunning: true,
      undoError: ''
    });
    var next = function next() {
      request('POST', '/migrations/undo', {
        lms: slug
      }).then(function (result) {
        self.state.undoDeleted += parseInt(result.deleted, 10) || 0;
        if (!result.done) {
          self.render();
          next();
          return;
        }
        var active = self.state.sources.some(function (source) {
          return source.name === slug && source.active;
        });
        delete self.state.preview[slug];
        self.set({
          undoRunning: false,
          undoOpen: false,
          view: 'select',
          sessionId: '',
          status: null,
          selected: active ? slug : '',
          sources: self.state.sources.map(function (source) {
            return source.name === slug ? Object.assign({}, source, {
              migrated: 0
            }) : source;
          }),
          banner: {
            label: self.sourceLabel(slug),
            undone: true
          },
          completed: Object.keys(self.state.completed).reduce(function (kept, key) {
            if (key !== slug) {
              kept[key] = self.state.completed[key];
            }
            return kept;
          }, {})
        });
        if (active) {
          self.loadPreview(slug, true);
        }
      })["catch"](function (error) {
        self.set({
          undoRunning: false,
          undoError: error.message || strings.undoError
        });
      });
    };
    next();
  };
  App.prototype.renderUndo = function () {
    var s = this.state;
    if (!s.undoOpen) {
      return '';
    }
    var name = this.sourceLabel(s.undoSlug);
    var summary = s.undoSummary;
    var body;
    if (s.undoLoading) {
      body = '<p class="masterstudy-migration__preview-status">' + ICONS.spinner + '<span>' + esc(strings.undoLoading) + '</span></p>';
    } else if (!summary || !(summary.posts > 0 || summary.coupons > 0 || summary.plans > 0 || summary.roles > 0)) {
      body = '<p class="masterstudy-migration__undo-summary">' + esc(s.undoError || strings.undoNothing) + '</p>';
    } else if (s.undoRunning) {
      var pct = summary.posts ? Math.min(100, Math.round(s.undoDeleted / summary.posts * 100)) : 100;
      body = '<p class="masterstudy-migration__undo-summary">' + esc(sprintf(strings.undoProgress, number(s.undoDeleted), number(summary.posts))) + '</p>' + '<div class="masterstudy-migration__bar masterstudy-migration__bar_running" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + pct + '"><span style="width:' + pct + '%"></span></div>';
    } else {
      body = '<p class="masterstudy-migration__undo-summary">' + esc(sprintf(strings.undoSummary, number(summary.types && summary.types['stm-courses'] || 0), number(summary.posts), number(summary.enrollments))) + '</p>' + (s.undoError ? '<p class="masterstudy-migration__poll-error">' + esc(s.undoError) + '</p>' : '') + '<div class="masterstudy-migration__confirm-field">' + '<label for="masterstudy-migration-undo-input">' + esc(strings.undoType).replace('%s', '<code>' + esc(config.undoKeyword || 'delete') + '</code>') + '</label>' + '<div class="masterstudy-migration__confirm-wrap">' + '<input id="masterstudy-migration-undo-input" type="text" class="masterstudy-migration__confirm-input masterstudy-migration__undo-input" autocomplete="off" spellcheck="false" autocapitalize="off" value="' + esc(s.undoTyped) + '">' + '<span class="masterstudy-migration__confirm-valid">' + ICONS.validCircle + '</span>' + '</div>' + '</div>';
    }
    return '<div class="masterstudy-migration__modal">' + '<div class="masterstudy-migration__modal-backdrop" data-action="close-undo"></div>' + '<div class="masterstudy-migration__modal-dialog" role="dialog" aria-modal="true" aria-labelledby="masterstudy-migration-undo-title">' + '<h2 class="masterstudy-migration__modal-title" id="masterstudy-migration-undo-title">' + esc(strings.undoTitle) + '</h2>' + '<div class="masterstudy-migration__warning masterstudy-migration__warning_danger">' + ICONS.warning + '<p>' + esc(sprintf(strings.undoText, name)) + '</p>' + '</div>' + body + '<div class="masterstudy-migration__modal-actions">' + '<button type="button" class="masterstudy-migration__button" data-action="close-undo"' + (s.undoRunning ? ' disabled' : '') + '>' + esc(strings.cancel) + '</button>' + '<button type="button" class="masterstudy-migration__button masterstudy-migration__button_danger masterstudy-migration__undo-start" data-action="confirm-undo">' + esc(strings.undoStart) + '</button>' + '</div>' + '</div>' + '</div>';
  };

  /**
   * Update the undo button without re-rendering (keeps the caret in place).
   */
  App.prototype.renderUndoState = function () {
    var valid = this.isUndoValid();
    var input = this.root.querySelector('.masterstudy-migration__undo-input');
    var button = this.root.querySelector('.masterstudy-migration__undo-start');
    if (input) {
      input.classList.toggle('masterstudy-migration__confirm-input_valid', valid);
    }
    if (button) {
      button.disabled = !valid || this.state.undoRunning;
    }
  };
  App.prototype.renderUnsupported = function () {
    var s = this.state;
    var source = s.sources.filter(function (item) {
      return item.name === s.selected;
    })[0];
    if (!source || !source.unsupported || !source.unsupported.length) {
      return '';
    }
    return '<div class="masterstudy-migration__unsupported">' + '<div class="masterstudy-migration__unsupported-head">' + ICONS.warning + '<span>' + esc(sprintf(strings.unsupportedTitle, source.label)) + '</span>' + '</div>' + '<p class="masterstudy-migration__unsupported-text">' + esc(strings.unsupportedText) + '</p>' + '<ul class="masterstudy-migration__unsupported-list">' + source.unsupported.map(function (feature) {
      return '<li>' + esc(feature) + '</li>';
    }).join('') + '</ul>' + '</div>';
  };
  App.prototype.renderTip = function (modifier, icon, title, text) {
    return '<div class="masterstudy-migration__tip">' + '<div class="masterstudy-migration__tip-icon masterstudy-migration__tip-icon_' + modifier + '">' + icon + '</div>' + '<div class="masterstudy-migration__tip-body">' + '<span class="masterstudy-migration__tip-title">' + esc(title) + '</span>' + '<span class="masterstudy-migration__tip-text">' + esc(text) + '</span>' + '</div>' + '</div>';
  };
  App.prototype.renderConfirm = function () {
    var s = this.state;
    if (!s.confirmOpen) {
      return '';
    }
    var name = this.sourceLabel(s.selected);
    var text = esc(sprintf(strings.confirmText, name)).replace(esc(name), '<strong>' + esc(name) + '</strong>');
    return '<div class="masterstudy-migration__modal">' + '<div class="masterstudy-migration__modal-backdrop" data-action="close-confirm"></div>' + '<div class="masterstudy-migration__modal-dialog" role="dialog" aria-modal="true" aria-labelledby="masterstudy-migration-confirm-title">' + '<h2 class="masterstudy-migration__modal-title" id="masterstudy-migration-confirm-title">' + esc(strings.confirmTitle) + '</h2>' + '<div class="masterstudy-migration__warning">' + ICONS.warning + '<p><strong>' + esc(strings.important) + '</strong> ' + text + '</p>' + '</div>' + '<div class="masterstudy-migration__confirm-field">' + '<label for="masterstudy-migration-confirm-input">' + esc(strings.typeConfirm).replace('%s', '<code>' + esc(config.confirmKeyword || 'confirm') + '</code>') + '</label>' + '<div class="masterstudy-migration__confirm-wrap">' + '<input id="masterstudy-migration-confirm-input" type="text" class="masterstudy-migration__confirm-input" autocomplete="off" spellcheck="false" autocapitalize="off" value="' + esc(s.typed) + '">' + '<span class="masterstudy-migration__confirm-valid">' + ICONS.validCircle + '</span>' + '</div>' + '</div>' + '<div class="masterstudy-migration__modal-actions">' + '<button type="button" class="masterstudy-migration__button" data-action="close-confirm">' + esc(strings.cancel) + '</button>' + '<button type="button" class="masterstudy-migration__button masterstudy-migration__button_primary masterstudy-migration__confirm-start" data-action="confirm-start">' + esc(s.starting ? strings.starting : strings.confirmStart) + '</button>' + '</div>' + '</div>' + '</div>';
  };

  /**
   * Update the confirm modal validity without re-rendering (keeps the caret in place).
   */
  App.prototype.renderConfirmState = function () {
    var valid = this.isConfirmValid();
    var input = this.root.querySelector('.masterstudy-migration__confirm-input');
    var button = this.root.querySelector('.masterstudy-migration__confirm-start');
    if (input) {
      input.classList.toggle('masterstudy-migration__confirm-input_valid', valid);
    }
    if (button) {
      button.disabled = !valid || this.state.starting;
    }
  };
  App.prototype.renderRun = function () {
    var s = this.state;
    var status = s.status;
    if (!status) {
      return '<section class="masterstudy-migration__card masterstudy-migration__card_loading">' + ICONS.spinner + '</section>';
    }
    var label = this.sourceLabel(status.lms_slug);
    var finished = TERMINAL.indexOf(status.status) !== -1;
    var sourceActive = s.sources.some(function (source) {
      return source.name === status.lms_slug && source.active;
    });
    var steps = Object.keys(status.steps || {});
    var total = 0;
    var done = 0;
    steps.forEach(function (step) {
      total += status.steps[step].total;
      done += Math.min(status.steps[step].completed + status.steps[step].failed, status.steps[step].total);
    });
    var title;
    var subtitle;
    var headIcon;
    if ('completed' === status.status) {
      title = sprintf(strings.completeTitle, label);
      subtitle = !total ? strings.emptySub : status.failed_total ? sprintf(strings.completeFailed, status.failed_total) : strings.completeSub;
      headIcon = '<div class="masterstudy-migration__head-icon masterstudy-migration__head-icon_done">' + ICONS.check + '</div>';
    } else if ('cancelled' === status.status) {
      title = strings.cancelledTitle;
      subtitle = strings.cancelledSub;
      headIcon = '<div class="masterstudy-migration__head-icon masterstudy-migration__head-icon_muted">' + ICONS.cross + '</div>';
    } else if ('failed' === status.status) {
      title = strings.failedTitle;
      subtitle = strings.failedSub;
      headIcon = '<div class="masterstudy-migration__head-icon masterstudy-migration__head-icon_error">' + ICONS.cross + '</div>';
    } else {
      title = sprintf(strings.migratingFrom, label);
      subtitle = strings.migratingSub;
      headIcon = '<div class="masterstudy-migration__head-icon masterstudy-migration__head-icon_running">' + ICONS.spinner + '</div>';
    }
    var pct = 'completed' === status.status ? 100 : status.overall_pct;
    var rows = steps.map(function (step, index) {
      return this.renderRow(step, index, status);
    }, this).join('');
    var footer;
    if (finished) {
      footer = '<a href="#" class="masterstudy-migration__link" data-action="toggle-log">' + esc(s.showLog ? strings.hideLog : strings.viewLog) + '</a>' + '<span class="masterstudy-migration__spacer"></span>' + ('completed' === status.status || 'cancelled' === status.status ? '<button type="button" class="masterstudy-migration__button masterstudy-migration__button_danger" data-action="open-undo" data-source="' + esc(status.lms_slug) + '">' + esc(strings.undoButton) + '</button>' : '') + (sourceActive ? '<button type="button" class="masterstudy-migration__button" data-action="run-again">' + esc(strings.runAgain) + '</button>' : '') + '<button type="button" class="masterstudy-migration__button" data-action="migrate-another">' + esc(strings.migrateAnother) + '</button>' + '<a class="masterstudy-migration__button masterstudy-migration__button_primary" href="' + esc(config.coursesUrl) + '">' + esc(strings.viewCourses) + '</a>';
    } else {
      footer = '<div class="masterstudy-migration__background">' + ICONS.info + '<span>' + esc(strings.backgroundHint) + '</span></div>' + '<button type="button" class="masterstudy-migration__button masterstudy-migration__button_danger" data-action="cancel-migration"' + (s.cancelling ? ' disabled' : '') + '>' + esc(s.cancelling ? strings.cancelling : strings.cancelMigration) + '</button>';
    }
    return '<section class="masterstudy-migration__card masterstudy-migration__run" aria-labelledby="masterstudy-migration-progress-title">' + '<div class="masterstudy-migration__run-head">' + '<div class="masterstudy-migration__run-title-row">' + headIcon + '<div class="masterstudy-migration__run-heading">' + '<h2 class="masterstudy-migration__run-title" id="masterstudy-migration-progress-title">' + esc(title) + '</h2>' + '<p class="masterstudy-migration__run-subtitle">' + esc(subtitle) + '</p>' + '</div>' + (null === status.elapsed_seconds ? '' : '<span class="masterstudy-migration__elapsed">' + esc(finished ? strings.duration : strings.elapsed) + ' <strong>' + formatElapsed(status.elapsed_seconds) + '</strong></span>') + '</div>' + (s.pollError ? '<p class="masterstudy-migration__poll-error">' + esc(strings.statusError) + '</p>' : '') + '<div class="masterstudy-migration__overall">' + '<div class="masterstudy-migration__overall-label"><span>' + esc(sprintf(strings.itemsOf, done, total)) + '</span><strong>' + pct + '%</strong></div>' + '<div class="masterstudy-migration__bar masterstudy-migration__bar_' + esc(status.status) + '" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + pct + '" aria-label="' + esc(strings.overall) + '">' + '<span style="width:' + pct + '%"></span>' + '</div>' + '</div>' + '</div>' + '<div class="masterstudy-migration__rows">' + rows + '</div>' + this.renderIssues(status) + (finished && s.showLog ? this.renderLog(status.log || []) : '') + '<div class="masterstudy-migration__run-footer">' + footer + '</div>' + '</section>';
  };
  App.prototype.renderRow = function (step, index, status) {
    var data = status.steps[step];
    var steps = Object.keys(status.steps);
    var currentIndex = steps.indexOf(status.current_step);
    var running = TERMINAL.indexOf(status.status) === -1;
    var empty = 0 === data.total;
    var processed = Math.min(data.completed + data.failed, data.total);
    var isDone = !empty && ('completed' === status.status || index < currentIndex || processed >= data.total);
    var isActive = !empty && !isDone && running && index === currentIndex;
    var modifier = empty ? 'empty' : isDone ? 'done' : isActive ? 'active' : 'pending';
    var icon = {
      empty: ICONS.dash,
      done: ICONS.check,
      active: ICONS.spinner,
      pending: ICONS.clock
    }[modifier];
    var count = empty ? '—' : processed + ' / ' + data.total;
    if (data.failed > 0) {
      count += ' <span class="masterstudy-migration__row-failed">· ' + data.failed + ' ' + esc(strings.failed) + '</span>';
    }
    return '<div class="masterstudy-migration__row masterstudy-migration__row_' + modifier + '">' + '<div class="masterstudy-migration__row-main">' + '<span class="masterstudy-migration__row-icon">' + icon + '</span>' + '<span class="masterstudy-migration__row-label">' + esc(stepLabels[step] || step) + '</span>' + '<span class="masterstudy-migration__row-count">' + count + '</span>' + '</div>' + (isActive ? '<div class="masterstudy-migration__row-bar"><span style="width:' + (data.pct || 0) + '%"></span></div>' : '') + '</div>';
  };
  App.prototype.renderIssues = function (status) {
    var s = this.state;
    var report = status.report || {};
    var groups = Object.keys(reportGroups).filter(function (group) {
      return report[group] && report[group].total > 0;
    });
    Object.keys(report).forEach(function (group) {
      if (groups.indexOf(group) === -1 && report[group].total > 0) {
        groups.push(group);
      }
    });
    if (!groups.length) {
      return '';
    }
    var list = groups.map(function (group) {
      var data = report[group];
      var parts = [];
      if (data.unsupported) {
        parts.push(sprintf(strings.countUnsupported, data.unsupported));
      }
      if (data.failed) {
        parts.push(sprintf(strings.countFailed, data.failed));
      }
      if (data.partial) {
        parts.push(sprintf(strings.countPartial, data.partial));
      }
      var open = s.reportGroup === group;
      return '<li>' + '<button type="button" class="masterstudy-migration__issue' + (open ? ' masterstudy-migration__issue_open' : '') + '" data-action="open-report" data-group="' + esc(group) + '" aria-expanded="' + (open ? 'true' : 'false') + '">' + '<span class="masterstudy-migration__issue-text">' + '<span class="masterstudy-migration__issue-label">' + esc(reportGroups[group] || group) + '</span>' + '<span class="masterstudy-migration__issue-meta">' + esc(parts.join(' · ')) + '</span>' + '</span>' + '<span class="masterstudy-migration__issue-count' + (data.failed ? ' masterstudy-migration__issue-count_error' : '') + '">' + data.total + '</span>' + '<span class="masterstudy-migration__issue-arrow">' + ICONS.arrow + '</span>' + '</button>' + '</li>';
    }).join('');
    return '<div class="masterstudy-migration__issues">' + '<div class="masterstudy-migration__issues-head">' + '<h3 class="masterstudy-migration__issues-title">' + ICONS.warning + esc(strings.issuesTitle) + '</h3>' + '<p class="masterstudy-migration__issues-text">' + esc(strings.issuesText) + '</p>' + '</div>' + '<div class="masterstudy-migration__issues-body' + (s.reportGroup ? ' masterstudy-migration__issues-body_open' : '') + '">' + '<ul class="masterstudy-migration__issue-list">' + list + '</ul>' + '<div class="masterstudy-migration__issue-panel" aria-live="polite">' + this.renderIssuePanel(report) + '</div>' + '</div>' + '</div>';
  };
  App.prototype.renderIssuePanel = function (report) {
    var s = this.state;
    var group = s.reportGroup;
    if (!group) {
      return '<p class="masterstudy-migration__issue-placeholder">' + esc(strings.issuesSelect) + '</p>';
    }
    var head = '<div class="masterstudy-migration__panel-head">' + '<h4 class="masterstudy-migration__panel-title">' + esc(reportGroups[group] || group) + '</h4>' + '<button type="button" class="masterstudy-migration__alert-close" data-action="close-report" aria-label="' + esc(strings.close) + '">' + ICONS.cross + '</button>' + '</div>';
    if (s.reportLoading) {
      return head + '<p class="masterstudy-migration__issue-placeholder">' + esc(strings.issuesLoading) + '</p>';
    }
    if (s.reportError) {
      return head + '<p class="masterstudy-migration__issue-placeholder">' + esc(strings.issuesError) + '</p>';
    }
    var items = s.reportItems[group] && s.reportItems[group].items || [];
    if (!items.length) {
      return head + '<p class="masterstudy-migration__issue-placeholder">' + esc(strings.issuesEmpty) + '</p>';
    }
    var statusLabels = {
      failed: strings.statusFailed,
      unsupported: strings.statusUnsupported,
      partial: strings.statusPartial
    };
    var rows = items.map(function (item) {
      var where = [];
      if (item.parent) {
        where.push(esc(item.parent));
      }
      if (item.course) {
        where.push(esc(strings.course) + ': ' + esc(item.course));
      }
      if (item.source_id) {
        where.push(esc(strings.sourceId) + ' #' + esc(item.source_id));
      }
      return '<li class="masterstudy-migration__item">' + '<div class="masterstudy-migration__item-head">' + '<span class="masterstudy-migration__item-title">' + esc(item.title || '#' + item.source_id) + '</span>' + (item.edit_url ? '<a class="masterstudy-migration__item-link" href="' + esc(item.edit_url) + '" target="_blank" rel="noopener">' + esc(strings.open) + ICONS.external + '</a>' : '') + '</div>' + '<div class="masterstudy-migration__item-tags">' + '<span class="masterstudy-migration__tag masterstudy-migration__tag_' + esc(item.status) + '">' + esc(statusLabels[item.status] || item.status) + '</span>' + (item.type ? '<span class="masterstudy-migration__tag">' + esc(item.type) + '</span>' : '') + '</div>' + (item.reason ? '<p class="masterstudy-migration__item-reason">' + esc(item.reason) + '</p>' : '') + (where.length ? '<p class="masterstudy-migration__item-where">' + where.join(' · ') + '</p>' : '') + '</li>';
    }).join('');
    var hidden = report[group] ? report[group].total - items.length : 0;
    return head + '<ul class="masterstudy-migration__items">' + rows + '</ul>' + (hidden > 0 ? '<p class="masterstudy-migration__issue-placeholder">' + esc(sprintf(strings.issuesMore, hidden)) + '</p>' : '');
  };
  App.prototype.renderLog = function (log) {
    if (!log.length) {
      return '<div class="masterstudy-migration__log"><p class="masterstudy-migration__log-empty">' + esc(strings.emptyLog) + '</p></div>';
    }
    return '<div class="masterstudy-migration__log"><ul>' + log.map(function (entry) {
      var time = entry.time ? new Date(entry.time * 1000).toLocaleTimeString() : '';
      return '<li class="masterstudy-migration__log-item masterstudy-migration__log-item_' + esc(entry.level) + '">' + '<span class="masterstudy-migration__log-time">' + esc(time) + '</span>' + '<span class="masterstudy-migration__log-level">' + esc(entry.level) + '</span>' + '<span class="masterstudy-migration__log-message">' + esc(entry.message) + '</span>' + '</li>';
    }).join('') + '</ul></div>';
  };

  /* ---------------------------------------------------------------------
   * NUXY integration: the settings tab renders <masterstudy_lms_migration>, and this
   * component boots the app once NUXY has mounted the tab. NUXY switches tabs by
   * toggling classes, so the app lives for the whole page.
   * ------------------------------------------------------------------- */

  function boot(node) {
    if (node && !node.__masterstudyMigration) {
      node.__masterstudyMigration = new App(node);
      node.__masterstudyMigration.render();
    }
  }
  if (window.Vue) {
    window.Vue.component('masterstudy_lms_migration', {
      template: '<div class="masterstudy-migration" ref="mount"></div>',
      mounted: function mounted() {
        boot(this.$refs.mount);
      }
    });
  }
  window.MasterstudyLmsMigration = {
    boot: boot
  };
})();