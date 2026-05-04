import DOMPurify from 'dompurify';

const triggerSelector = [
    '[data-news-export-modal-trigger]',
    '.news-export-modal-trigger',
    '.action-startExport',
    '.action-selectNews',
    'a[href*="crudAction=selectNews"]',
    'a[href*="action=selectNews"]',
    'a[href*="selectNews"]',
].join(',');

let initialized = false;

const createElement = (tagName, attributes = {}, children = []) => {
    const element = document.createElement(tagName);

    Object.entries(attributes).forEach(([name, value]) => {
        if (null === value || undefined === value) {
            return;
        }

        if ('className' === name) {
            element.className = value;

            return;
        }

        if ('text' === name) {
            element.textContent = value;

            return;
        }

        element.setAttribute(name, value);
    });

    children.forEach((child) => {
        element.append(child);
    });

    return element;
};

const showModal = (modalElement, modal) => {
    if (modal) {
        modal.show();

        return;
    }

    modalElement.classList.add('show');
    modalElement.style.display = 'block';
    modalElement.removeAttribute('aria-hidden');
    modalElement.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');
};

const renderLoading = (content) => {
    content.replaceChildren(createElement('div', { className: 'modal-body' }, [
        createElement('div', { className: 'text-muted', text: 'Loading...' }),
    ]));
};

const initSelectionControls = (content, loadSelection) => {
    const selectAll = content.querySelector('[data-news-export-select-all]');

    selectAll?.addEventListener('change', () => {
        content.querySelectorAll('input[name="news_ids[]"]').forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });
    });

    content.querySelectorAll('[data-news-export-page-link]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            loadSelection(link.href);
        });
    });
};

const init = () => {
    if (initialized) {
        return;
    }

    const modalElement = document.getElementById('news-export-modal');
    const content = modalElement?.querySelector('[data-news-export-modal-content]');

    if (!modalElement || !content) {
        return;
    }

    initialized = true;

    const modal = window.bootstrap?.Modal
        ? window.bootstrap.Modal.getOrCreateInstance(modalElement)
        : null;

    const loadSelection = async (url) => {
        renderLoading(content);

        const response = await fetch(url, {
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const html = await response.text();

        content.innerHTML = DOMPurify.sanitize(html, {
            USE_PROFILES: { html: true },
        });

        initSelectionControls(content, loadSelection);
        showModal(modalElement, modal);
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest(triggerSelector);

        if (!trigger) {
            return;
        }

        event.preventDefault();
        loadSelection(trigger.href);
    });
};

if ('loading' === document.readyState) {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

window.NewsExportModal = { init };
