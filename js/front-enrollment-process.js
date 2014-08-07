jQuery(document).ready(function($) {

    $('.apply-button.enroll-success').live('click', function(event) {
        if ($(this).data('link')) {
            window.location.href = $(this).data('link');
        }
    });

    // Create specific click-handlers to double check if they are already assigned
    function signup_click(e) {
        e.preventDefault();
        e.stopPropagation();
        validate_signup_data_and_submit();
    }
    function login_click(e) {
        e.preventDefault();
        e.stopPropagation();
        validate_login_data_and_submit();
    }
    function payment_click(e) {
        e.preventDefault();
        e.stopPropagation();
        prepare_payment_data_and_submit(this);
    }

    // Functions/handlers to apply to newly loaded content.
    function init_popup(element) {

        // Prevent duplicate handling by unbinding before binding... uses non-anonymous signatures
        $('body').off('click', '.cp_popup_content .apply-button.login', login_click)
        $('body').on('click', '.cp_popup_content .apply-button.login', login_click)

        $('body').off('click', '.cp_popup_content .apply-button.signup-data', signup_click)
        $('body').on('click', '.cp_popup_content .apply-button.signup-data', signup_click)

        $('body').off('click', '.cp_popup_content .apply-button.signup-data', signup_click)
        $('body').on('click', '.cp_popup_content .apply-button.signup-data', signup_click)

        $('body').off('click', '.cp_popup_content .popup-payment-button', payment_click)
        $('body').on('click', '.cp_popup_content .popup-payment-button', payment_click)

    }

    /* Signup */
    $('button.apply-button.signup, .cp_signup_step').live('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        open_popup('signup', $(this).attr('data-course-id'));
    });

    /* Enroll (logged in users) */
    $('button.apply-button.enroll').click(function(e) {
        e.preventDefault();
        e.stopPropagation();
        open_popup('enrollment', $(this).attr('data-course-id'));
    });

    /* Login Step */

    $('.cp_login_step').live('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        open_popup('login', $(this).attr('data-course-id'));
    });


    $('.cp_popup_close_button').click(function(e) {//.cp_popup_overall, 
        close_popup();
    });



    function validate_login_data_and_submit() {
        var errors = 0;
        var required_errors = 0;

        $(".required").each(function(index) {
            if ($(this).val() == '') {
                required_errors++;
                errors++;
                validate_mark_error_field($(this).attr('id'));
            } else {
                validate_mark_blank_error_field($(this).attr('id'));
            }
        });

        if (required_errors > 0) {
            $('.validation_errors').html(cp_vars.message_all_fields_are_required);
        } else {
            var username = $('#cp_popup_username').val();
            var password = $('#cp_popup_password').val();
            $.post(
                    cp_vars.admin_ajax_url, {
                        action: 'cp_popup_login_user',
                        username: username,
                        password: password
                    }
            ).done(function(data, status) {
                if (status == 'success') {
                    if (data == 'success') {//user logged in successfully
                        var step = 'process_login';
                        open_popup(step, $('.apply-button.login').attr('data-course-id'));
                    } else {//show some error
                        $('.validation_errors').html(cp_vars.message_login_error);
                        validate_mark_error_field('cp_popup_username');
                        validate_mark_error_field('cp_popup_password');

                    }
                }
            });
        }
    }

    function validate_signup_data_and_submit() {
        var errors = 0;
        var required_errors = 0;

        $(".required").each(function(index) {
            if ($(this).val() == '') {
                required_errors++;
                errors++;
                //validate_mark_error_field($(this).attr('id'));
            } else {
                //validate_mark_blank_error_field($(this).attr('id'));
            }
        });

        if (required_errors > 0) {
            $(".required").each(function(index) {
                if ($(this).val() == '') {
                    validate_mark_error_field($(this).attr('id'));
                } else {
                    validate_mark_blank_error_field($(this).attr('id'));
                }
            });
            $('.validation_errors').html(cp_vars.message_all_fields_are_required);
        } else {//continue with checking

            validate_mark_no_error_field('cp_popup_student_first_name');
            validate_mark_no_error_field('cp_popup_student_last_name');

            var username = $('#cp_popup_username').val();
            if (username.length < 4) {
                errors++;
                $('.validation_errors').html(cp_vars.message_username_minimum_length);
            } else {//check if user already exists
                $.post(
                        cp_vars.admin_ajax_url, {
                            action: 'cp_popup_user_exists',
                            username: username,
                        }
                ).done(function(data, status) {
                    if (status == 'success') {
                        if (Number(data) > 0) {//user exists
                            errors++;
                            $('.validation_errors').html(cp_vars.message_username_exists);
                            validate_mark_error_field('cp_popup_username');
                        } else {//check email address
                            validate_mark_no_error_field('cp_popup_username');
                            var email = $('#cp_popup_email').val();
                            var email_confirmation = $('#cp_popup_email_confirmation').val();

                            if (email != email_confirmation) {
                                errors++;
                                $('.validation_errors').html(cp_vars.message_emails_dont_match);
                                validate_mark_error_field('cp_popup_email');
                                validate_mark_error_field('cp_popup_email_confirmation');
                            } else {
                                
                                $.post(
                                        cp_vars.admin_ajax_url, {
                                            action: 'cp_popup_email_exists',
                                            email: email,
                                        }
                                ).done(function(data, status) {
                                    if (status == 'success') {
                                        if (Number(data) > 0) {//email exists
                                            errors++;
                                            $('.validation_errors').html(cp_vars.message_email_exists);
                                            validate_mark_error_field('cp_popup_email');
                                            validate_mark_error_field('cp_popup_email_confirmation');
                                        } else {//check passwords
                                            validate_mark_no_error_field('cp_popup_email');
                                            validate_mark_no_error_field('cp_popup_email_confirmation');
                                            var password = $('#cp_popup_password').val();
                                            var password_confirmation = $('#cp_popup_password_confirmation').val();

                                            if (password != password_confirmation) {
                                                errors++;
                                                $('.validation_errors').html(cp_vars.message_passwords_dont_match);
                                                validate_mark_error_field('cp_popup_password');
                                                validate_mark_error_field('cp_popup_password_confirmation');
                                            } else {//check password for minimum lenght
                                                if (password.length < cp_vars.minimum_password_lenght) {
                                                    errors++;
                                                    $('.validation_errors').html(cp_vars.message_password_minimum_length);
                                                    validate_mark_error_field('cp_popup_password');
                                                } else {//valid data, continue with submit
                                                    validate_mark_no_error_field('cp_popup_password');
                                                    validate_mark_no_error_field('cp_popup_password_confirmation');
                                                    var step = $('.cp_popup_content [name="signup-next-step"]').val();
                                                    open_popup(step, $('#data-course-id').attr('data-course-id'), $('#popup_signup_form').serialize());
                                                }
                                            }
                                        }
                                    }
                                });
                            }
                        }
                    }
                });
            }
        }
        if (errors == 0) {
            $('.validation_errors').html('');
        }
    }

    function prepare_payment_data_and_submit(element) {
        var course_id = $(element).attr('data-course-id');
        var course_data = {
            product_id: $(element).attr('data-product-id'),
            gateway: $(element).attr('data-gateway'),
        }

        cp_popup_load_content('process_payment', course_id, course_data);

    }


    function validate_mark_error_field(field) {
        $('#' + field).removeClass('cp_no_error_field');
        $('#' + field).addClass('cp_error_field');
    }

    function validate_mark_no_error_field(field) {
        $('#' + field).removeClass('cp_error_field');
        $('#' + field).addClass('cp_no_error_field');
    }

    function validate_mark_blank_error_field(field) {
        $('#' + field).removeClass('cp_error_field');
        $('#' + field).removeClass('cp_no_error_field');
    }

    function open_popup(step, course_id, data) {
        if (typeof data === 'undefined') {//data not set
            content_loaded = cp_popup_load_content(step, course_id);
        } else {
            content_loaded = cp_popup_load_content(step, course_id, data);
        }

        $("body > div").not($(".cp_popup_window")).addClass('cp_blur');
        $('.cp_popup_overall').show();
        $('.cp_popup_window').center();
        if (step != 'enrollment') {
            $('.cp_popup_window').show();
        }

    }

    function close_popup() {
        $("body > div").not($(".cp_popup_window")).removeClass('cp_blur');
        $('.cp_popup_overall').hide();
        $('.cp_popup_window').hide();
    }

    function cp_popup_load_content(step, course_id, data) {
        $('.cp_popup_loading').show();
        $('.cp_popup_content').html('');

        var post_args;

        if (typeof data === 'undefined') {//data not set
            data = '';
        }

        $.post(cp_vars.admin_ajax_url, {
            action: 'cp_popup_signup',
            course_id: course_id,
            step: step,
            data: data,
        }).done(function(data, status) {
            if (status == 'success') {
                var response = $.parseJSON($(data).find('response_data').text());
                if (response) {
                    console.log(response);
                    if (response.redirect_url && response.redirect_url != '') {
                        window.location.href = response.redirect_url;
                        return;
                    }
                    $('.cp_popup_content').html(response.html);
                    $('.cp_popup_content [name="signup-next-step"]').val(response.next_step);
                    init_popup($('.cp_popup_content'));
                    $('.cp_popup_window').show();
                    $('.cp_popup_window').autoHeight();
                    $('.cp_popup_window').center();
                    $('.cp_popup_loading').hide();
                }
            } else {
            }
        }).fail(function(data) {
        });
    }

    // Extend jQuery with $.center() function to center elements in the middle of the screen
    jQuery.fn.center = function() {
        this.css('position', 'fixed');
        this.css('top', ($(window).height() / 2) - (this.outerHeight() / 2));
        this.css('left', ($(window).width() / 2) - (this.outerWidth() / 2));
        return this;
    }

    // Extend jQuery with $.autoHeight() function to adjust the height of an element to its contents.
    jQuery.fn.autoHeight = function() {
        var new_height = $($(this).find('*').last()).position().top + $($(this).find('*').last()).outerHeight();
        this.css('height', new_height);
        return this;
    }

    // When the window scrolls, make sure we keep the popup in the center.
    $(window).resize(function() {
        $('.cp_popup_window').center();
        $(".cp_popup_overall").height($(document).height());
    });

    $(".cp_popup_overall").height($(document).height());

});
