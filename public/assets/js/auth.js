/*
|--------------------------------------------------------------------------
| Form message
|--------------------------------------------------------------------------
*/

function showFormMessage(element, message, type = 'error') {

    element.textContent = message;

    element.className =
        'form-message form-message-' + type;

}


/*
|--------------------------------------------------------------------------
| Button loading state
|--------------------------------------------------------------------------
*/

function setButtonLoading(
    button,
    loading,
    loadingText,
    normalText
) {

    button.disabled = loading;

    button.textContent =
        loading
            ? loadingText
            : normalText;

}


/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

function initLoginForm() {

    const form =
        document.getElementById('loginForm');

    if (!form) {
        return;
    }


    const message =
        document.getElementById('loginMessage');

    const button =
        document.getElementById('loginButton');


    form.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();


            /*
            |--------------------------------------------------------------------------
            | Browser validation
            |--------------------------------------------------------------------------
            */

            if (!form.checkValidity()) {

                form.reportValidity();

                return;
            }


            showFormMessage(
                message,
                ''
            );


            setButtonLoading(
                button,
                true,
                'Kirish...',
                'Kirish'
            );


            try {

                const response =
                    await fetch(
                        '../api/auth/login.php',
                        {
                            method: 'POST',
                            body: new FormData(form)
                        }
                    );


                const data =
                    await response.json();


                if (!data.success) {

                    showFormMessage(
                        message,
                        data.message ||
                        'Kirish amalga oshmadi.'
                    );

                    return;
                }


                showFormMessage(
                    message,
                    data.message,
                    'success'
                );


                /*
                |--------------------------------------------------------------------------
                | Redirect after successful login
                |--------------------------------------------------------------------------
                */

                window.location.href =
                    data.redirect ||
                    'dashboard.php';

            }


            catch (error) {

                showFormMessage(
                    message,
                    'Server bilan bog‘lanishda xatolik yuz berdi.'
                );

            }


            finally {

                setButtonLoading(
                    button,
                    false,
                    'Kirish...',
                    'Kirish'
                );

            }

        }
    );

}


/*
|--------------------------------------------------------------------------
| Register
|--------------------------------------------------------------------------
*/

function initRegisterForm() {

    const form =
        document.getElementById('registerForm');

    if (!form) {
        return;
    }


    const message =
        document.getElementById('registerMessage');

    const button =
        document.getElementById('registerButton');


    form.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();


            /*
            |--------------------------------------------------------------------------
            | Browser validation
            |--------------------------------------------------------------------------
            */

            if (!form.checkValidity()) {

                form.reportValidity();

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Confirm passwords
            |--------------------------------------------------------------------------
            */

            const password =
                document.getElementById('password').value;

            const passwordConfirm =
                document.getElementById('passwordConfirm').value;


            if (password !== passwordConfirm) {

                showFormMessage(
                    message,
                    'Parollar mos kelmadi.'
                );

                return;
            }


            showFormMessage(
                message,
                ''
            );


            setButtonLoading(
                button,
                true,
                'Ro`yxatdan o`tish...',
                'Ro`yxatdan o`tish'
            );


            try {

                const response =
                    await fetch(
                        '../api/auth/register.php',
                        {
                            method: 'POST',
                            body: new FormData(form)
                        }
                    );


                const data =
                    await response.json();


                if (!data.success) {

                    if (
                        Array.isArray(data.errors)
                    ) {

                        showFormMessage(
                            message,
                            data.errors.join(' ')
                        );

                    } else {

                        showFormMessage(
                            message,
                            data.message ||
                            'Ro‘yxatdan o‘tish amalga oshmadi.'
                        );

                    }

                    return;
                }


                showFormMessage(
                    message,
                    data.message +
                    ' Endi tizimga kirishingiz mumkin.',
                    'success'
                );


                form.reset();


                /*
                |--------------------------------------------------------------------------
                | Redirect to login
                |--------------------------------------------------------------------------
                */

                setTimeout(
                    function () {

                        window.location.href =
                            'login.php';

                    },
                    1000
                );

            }


            catch (error) {

                showFormMessage(
                    message,
                    'Server bilan bog‘lanishda xatolik yuz berdi.'
                );

            }


            finally {

                setButtonLoading(
                    button,
                    false,
                    'Ro`yxatdan o`tish...',
                    'Ro`yxatdan o`tish'
                );

            }

        }
    );

}