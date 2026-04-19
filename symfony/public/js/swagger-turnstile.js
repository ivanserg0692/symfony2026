(function () {
  function setTokenValue(token) {
    const tokenNode = document.getElementById('swagger-turnstile-token');

    if (!tokenNode) {
      return;
    }

    tokenNode.value = token || '';
  }

  function setStatus(state, message) {
    const statusNode = document.getElementById('swagger-turnstile-status');

    if (!statusNode) {
      return;
    }

    statusNode.dataset.state = state;
    statusNode.textContent = message;
  }

  function bindResetButton(widgetId) {
    const resetButton = document.getElementById('swagger-turnstile-reset');

    if (!resetButton || resetButton.dataset.bound === 'true') {
      return;
    }

    resetButton.dataset.bound = 'true';
    resetButton.addEventListener('click', function () {
      if (!window.turnstile || !widgetId) {
        return;
      }

      window.turnstile.reset(widgetId);
      setTokenValue('');
      setStatus('idle', 'Captcha reset. Complete verification again to get a new token.');
    });
  }

  function renderWidget(config, onSuccess, onExpired) {
    if (!window.turnstile) {
      window.setTimeout(function () {
        renderWidget(config, onSuccess, onExpired);
      }, 250);
      return;
    }

    const container = document.getElementById('swagger-turnstile');
    if (!container || container.dataset.rendered === 'true') {
      return;
    }

    container.dataset.rendered = 'true';

    const widgetId = window.turnstile.render(container, {
      sitekey: config.siteKey,
      callback: function (token) {
        onSuccess(token);
        setStatus('ready', 'Captcha solved. Copy the token below and paste it into turnstileToken.');
      },
      'expired-callback': function () {
        onExpired();
        setTokenValue('');
        setStatus('error', 'Turnstile token expired. Complete the captcha again.');
      },
      'error-callback': function () {
        onExpired();
        setTokenValue('');
        setStatus('error', 'Turnstile verification widget failed to load or validate.');
      }
    });

    bindResetButton(widgetId);
  }

  window.initializeSwaggerTurnstile = function initializeSwaggerTurnstile(config) {
    setTokenValue('');

    renderWidget(
      config,
      function (token) {
        setTokenValue(token);
      },
      function () {
        setTokenValue('');
      }
    );
  };
})();
