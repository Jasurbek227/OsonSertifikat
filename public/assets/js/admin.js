
/*
 * Oson Sertifikat admin helper.
 * Save as:
 * public/assets/js/admin.js
 */

(function () {
    'use strict';

    function initQuestionActions() {

        document
            .querySelectorAll('.admin-question-actions')
            .forEach(function (actions) {

                const edit =
                    actions.querySelector(
                        'a[href*="question_edit.php"]'
                    );

                const forms =
                    Array.from(
                        actions.querySelectorAll('form')
                    );

                const deleteForm =
                    forms.find(function (form) {
                        return (
                            form.action &&
                            form.action.indexOf(
                                'question_delete.php'
                            ) !== -1
                        );
                    });

                const toggleForm =
                    forms.find(function (form) {
                        const action =
                            form.querySelector(
                                'input[name="action"]'
                            );

                        return (
                            action &&
                            action.value === 'toggle'
                        );
                    });


                if (edit) {

                    edit.classList.add(
                        'admin-question-icon-button',
                        'admin-question-edit-button'
                    );

                    edit.innerHTML =
                        '<i data-lucide="pencil"></i>';

                    edit.setAttribute(
                        'aria-label',
                        'Tahrirlash'
                    );

                    edit.setAttribute(
                        'title',
                        'Tahrirlash'
                    );
                }


                if (deleteForm) {

                    const button =
                        deleteForm.querySelector(
                            'button[type="submit"], input[type="submit"]'
                        );

                    if (button) {

                        button.classList.add(
                            'admin-question-icon-button',
                            'admin-question-delete-button'
                        );

                        button.innerHTML =
                            '<i data-lucide="trash-2"></i>';

                        button.setAttribute(
                            'aria-label',
                            'Butunlay o‘chirish'
                        );

                        button.setAttribute(
                            'title',
                            'Butunlay o‘chirish'
                        );
                    }
                }


                if (toggleForm) {

                    const button =
                        toggleForm.querySelector(
                            'button[type="submit"], input[type="submit"]'
                        );

                    if (button) {

                        /*
                         * Existing PHP uses the visible text to distinguish
                         * the active/inactive action. Convert that state
                         * into the initial switch state before replacing it.
                         */
                        const oldText = (
                            button.textContent ||
                            button.value ||
                            ''
                        ).toLowerCase();

                        const isActive =
                            oldText.indexOf('o‘chirish') !== -1 ||
                            oldText.indexOf("o'chirish") !== -1 ||
                            oldText.indexOf('ochirish') !== -1;

                        const wrapper =
                            document.createElement('label');

                        wrapper.className =
                            'admin-question-switch';

                        wrapper.title =
                            isActive
                                ? 'Faol'
                                : 'Nofaol';

                        wrapper.innerHTML = `
                            <input
                                type="checkbox"
                                ${isActive ? 'checked' : ''}
                                aria-label="${
                                    isActive
                                        ? 'Savol faol'
                                        : 'Savol nofaol'
                                }"
                            >

                            <span
                                class="admin-question-switch-track"
                            ></span>
                        `;

                        button.remove();

                        toggleForm.classList.add(
                            'admin-question-switch-form'
                        );

                        toggleForm.appendChild(
                            wrapper
                        );

                        const checkbox =
                            wrapper.querySelector(
                                'input'
                            );

                        checkbox.addEventListener(
                            'change',
                            function () {
                                toggleForm.submit();
                            }
                        );
                    }
                }
            });


        if (
            typeof window.lucide !==
            'undefined'
        ) {
            window.lucide.createIcons();
        }
    }


    function initBlocksBackButton() {

        const path =
            window.location.pathname
                .toLowerCase();

        if (
            !/\/admin\/blocks\.php$/.test(path)
        ) {
            return;
        }

        if (
            document.querySelector(
                '.admin-page-back'
            )
        ) {
            return;
        }

        const header =
            document.querySelector(
                '.admin-page-header'
            );

        if (!header) {
            return;
        }

        const link =
            document.createElement('a');

        link.href = 'index.php';
        link.className = 'admin-page-back';

        link.innerHTML = `
            <i data-lucide="arrow-left"></i>
            Dashboard
        `;

        header.parentNode.insertBefore(
            link,
            header
        );

        if (
            typeof window.lucide !==
            'undefined'
        ) {
            window.lucide.createIcons();
        }
    }


    function init() {
        initQuestionActions();
        initBlocksBackButton();
    }


    if (
        document.readyState === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            init
        );
    } else {
        init();
    }

})();
