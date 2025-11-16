document.addEventListener('DOMContentLoaded', function () {
    const fontSelect = document.querySelector('[data-font-choice]');
    const customWrapper = document.querySelector('[data-font-custom-fields]');
    const uploadBtn = document.querySelector('[data-font-upload]');
    const urlField = document.getElementById('admin_font_custom_url');
    const nameField = document.getElementById('admin_font_custom_name');
    const preview = document.querySelector('[data-font-preview]');

    function updatePreviewFont() {
        if (!preview || !fontSelect) {
            return;
        }

        let family = '';
        if (fontSelect.value === 'custom') {
            const customName = nameField ? nameField.value.trim() : '';
            if (customName) {
                family = `"${customName}", sans-serif`;
            }
        } else {
            const activeOption = fontSelect.selectedOptions && fontSelect.selectedOptions.length ? fontSelect.selectedOptions[0] : null;
            if (activeOption) {
                const attr = activeOption.getAttribute('data-font-family');
                if (attr) {
                    family = attr;
                }
            }
        }

        preview.style.fontFamily = family || '';
    }

    function toggleCustomFields() {
        if (!customWrapper || !fontSelect) {
            return;
        }
        const shouldShow = fontSelect.value === 'custom';
        customWrapper.style.display = shouldShow ? '' : 'none';
        updatePreviewFont();
    }

    if (fontSelect) {
        fontSelect.addEventListener('change', function () {
            toggleCustomFields();
        });
        toggleCustomFields();
    }

    if (nameField) {
        nameField.addEventListener('input', updatePreviewFont);
    }

    if (preview) {
        updatePreviewFont();
    }

    if (uploadBtn && typeof wp !== 'undefined' && wp.media) {
        let frame;
        uploadBtn.addEventListener('click', function (event) {
            event.preventDefault();

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: uploadBtn.dataset.title || 'Select font file',
                button: {
                    text: uploadBtn.dataset.button || 'Use this font'
                },
                multiple: false,
                library: {
                    type: ['application', 'font', 'application/font-woff', 'application/font-woff2', 'font/ttf', 'font/otf']
                }
            });

            frame.on('select', function () {
                const attachment = frame.state().get('selection').first();
                if (!attachment || !urlField) {
                    return;
                }

                const data = attachment.toJSON();
                urlField.value = data.url || '';
                urlField.dispatchEvent(new Event('change'));

                if (preview) {
                    preview.innerHTML = urlField.dataset.previewText || preview.innerHTML;
                }
            });
        });
    }
});

