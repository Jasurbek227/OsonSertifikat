
/*
 * Admin image picker modal.
 * Requires:
 *   #openImagePicker
 *   #selectedImagePath
 *   #selectedImageList
 *
 * It opens images.php?picker=1 inside an iframe,
 * not a new browser window.
 */

(function () {
    'use strict';

    function initAdminImagePicker() {

        const trigger =
            document.getElementById('openImagePicker');

        const hidden =
            document.getElementById('selectedImagePath');

        const selectedList =
            document.getElementById('selectedImageList');

        if (!trigger || !hidden || !selectedList) {
            return;
        }

        let overlay = null;
        let frame = null;
        let pendingPath = hidden.value || '';


        function renderSelected(path) {

            selectedList.innerHTML = '';

            if (!path) {
                return;
            }

            const item =
                document.createElement('div');

            item.className =
                'admin-selected-image';

            item.innerHTML = `
                <img src="../${escapeHtml(path)}" alt="">

                <div>
                    <strong>Tanlangan rasm</strong>
                    <small>${escapeHtml(path)}</small>
                </div>

                <button
                    type="button"
                    class="admin-secondary-button admin-small-button"
                    id="removeSelectedImage"
                >
                    <i data-lucide="x"></i>
                    Olib tashlash
                </button>
            `;

            selectedList.appendChild(item);

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            const remove =
                document.getElementById(
                    'removeSelectedImage'
                );

            if (remove) {
                remove.addEventListener(
                    'click',
                    function () {
                        hidden.value = '';
                        pendingPath = '';
                        renderSelected('');
                    }
                );
            }
        }


        function escapeHtml(value) {

            const div =
                document.createElement('div');

            div.textContent =
                value == null
                    ? ''
                    : String(value);

            return div.innerHTML;
        }


        function closeModal() {

            if (overlay) {
                overlay.remove();
            }

            overlay = null;
            frame = null;

            document.body.classList.remove(
                'admin-image-picker-open'
            );
        }


        function openModal() {

            if (overlay) {
                return;
            }

            pendingPath = hidden.value || '';

            overlay =
                document.createElement('div');

            overlay.className =
                'admin-image-picker-modal';

            overlay.innerHTML = `
                <div class="admin-image-picker-dialog">

                    <div class="admin-image-picker-header">

                        <div class="admin-image-picker-title">
                            Rasm tanlash
                        </div>

                        <button
                            type="button"
                            class="admin-image-picker-close"
                            aria-label="Yopish"
                            data-picker-close
                        >
                            ×
                        </button>

                    </div>

                    <iframe
                        class="admin-image-picker-frame"
                        src="images.php?picker=1"
                        title="Rasm tanlash"
                    ></iframe>

                    <div class="admin-image-picker-footer">

                        <button
                            type="button"
                            class="admin-image-picker-cancel"
                            data-picker-close
                        >
                            Bekor qilish
                        </button>

                        <button
                            type="button"
                            class="admin-image-picker-confirm"
                            data-picker-confirm
                            disabled
                        >
                            Tanlash
                        </button>

                    </div>

                </div>
            `;

            document.body.appendChild(overlay);

            frame =
                overlay.querySelector(
                    '.admin-image-picker-frame'
                );

            overlay
                .querySelectorAll(
                    '[data-picker-close]'
                )
                .forEach(
                    function (button) {
                        button.addEventListener(
                            'click',
                            closeModal
                        );
                    }
                );

            overlay
                .querySelector(
                    '[data-picker-confirm]'
                )
                .addEventListener(
                    'click',
                    function () {

                        if (!pendingPath) {
                            return;
                        }

                        hidden.value =
                            pendingPath;

                        renderSelected(
                            pendingPath
                        );

                        closeModal();
                    }
                );

            document.addEventListener(
                'keydown',
                handleEscape
            );

            document.body.classList.add(
                'admin-image-picker-open'
            );
        }


        function handleEscape(event) {

            if (
                event.key === 'Escape' &&
                overlay
            ) {
                closeModal();
            }
        }


        window.addEventListener(
            'message',
            function (event) {

                if (
                    event.origin !==
                    window.location.origin
                ) {
                    return;
                }

                if (
                    !event.data ||
                    event.data.type !==
                    'osonsertifikat-image-preview'
                ) {
                    return;
                }

                pendingPath =
                    String(
                        event.data.path || ''
                    );

                if (!overlay) {
                    return;
                }

                const confirm =
                    overlay.querySelector(
                        '[data-picker-confirm]'
                    );

                if (confirm) {
                    confirm.disabled =
                        pendingPath === '';
                }
            }
        );


        trigger.addEventListener(
            'click',
            openModal
        );


        renderSelected(
            hidden.value || ''
        );
    }


    if (
        document.readyState ===
        'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            initAdminImagePicker
        );
    } else {
        initAdminImagePicker();
    }

})();
