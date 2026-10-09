/*
 * Resend Inbox: optional enhancements. Without JavaScript every screen
 * still works (bulk archiving then needs ticking boxes one by one).
 */
(() => {
    const ready = (callback) => (document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', callback) : callback());

    ready(() => {
        // Paint each email with its iframe's colors and grow it to its content (allow-same-origin, no scripts inside).
        document.querySelectorAll('iframe[data-inbox-autosize]').forEach((frame) => {
            const resize = () => {
                const body = frame.contentDocument?.body;

                if (body) {
                    body.style.color = getComputedStyle(frame).color;
                    body.style.background = getComputedStyle(frame).backgroundColor;
                }

                // The document's scrollHeight is never below the iframe's own height, so measure the content.
                const height = body ? Math.max(body.scrollHeight, frame.contentDocument.documentElement.offsetHeight) : 0;

                if (height) {
                    frame.style.height = `${height}px`;
                }
            };

            frame.addEventListener('load', resize);
            resize();
        });

        // Status filter applies on change.
        document.querySelectorAll('[data-inbox-autosubmit]').forEach((field) => {
            field.addEventListener('change', () => field.form?.requestSubmit());
        });

        // Select every conversation on the page; show the bulk actions while any is ticked.
        document.querySelectorAll('form[data-inbox-selection]').forEach((form) => {
            const page = form.querySelector('[data-inbox-select-page]');
            const boxes = [...form.querySelectorAll('[data-inbox-select]')];
            const bulk = form.querySelector('[data-inbox-bulk]');

            const sync = () => {
                const ticked = boxes.filter((box) => box.checked).length;

                if (page) {
                    page.checked = boxes.length > 0 && ticked === boxes.length;
                    page.indeterminate = ticked > 0 && ticked < boxes.length;
                }

                if (bulk) {
                    bulk.hidden = ticked === 0;
                }
            };

            page?.addEventListener('change', () => {
                boxes.forEach((box) => {
                    box.checked = page.checked;
                });
                sync();
            });

            boxes.forEach((box) => box.addEventListener('change', sync));
            sync();
        });
    });
})();
