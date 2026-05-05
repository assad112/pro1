document.addEventListener('submit', function (event) {
  const form = event.target;
  if (!(form instanceof HTMLFormElement)) {
    return;
  }
  const button = form.querySelector('button[type="submit"]');
  if (button) {
    button.disabled = true;
    setTimeout(function () {
      button.disabled = false;
    }, 1500);
  }
});

document.addEventListener('DOMContentLoaded', function () {
  const tabs = Array.from(document.querySelectorAll('[data-provider-tab]'));
  const panels = Array.from(document.querySelectorAll('.provider-tab-panel'));
  const stage = document.querySelector('#provider-panel-stage');

  if (tabs.length === 0 || panels.length === 0) {
    return;
  }

  function showProviderPanel(panelId, shouldScroll) {
    tabs.forEach(function (tab) {
      tab.classList.toggle('is-active', tab.dataset.providerTab === panelId);
    });

    panels.forEach(function (panel) {
      panel.classList.toggle('is-active', panel.id === panelId);
    });

    if (shouldScroll && stage) {
      stage.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function (event) {
      event.preventDefault();
      const panelId = tab.dataset.providerTab;
      if (!panelId) {
        return;
      }
      showProviderPanel(panelId, true);
      history.replaceState(null, '', '#' + panelId);
    });
  });

  const initialPanel = window.location.hash.replace('#', '');
  if (initialPanel && document.getElementById(initialPanel)) {
    showProviderPanel(initialPanel, false);
  }
});
