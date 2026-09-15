

function numericValue(value) {
    var parsed = parseFloat(String(value || '').replace(',', '.'));
    return isNaN(parsed) ? 0 : parsed;
}

function displayTotal(value) {
    return Math.round(value * 100) / 100;
}

function recalculateCoursePlanTotals() {
    var controls = 0;
    var seminars = 0;
    var seminarWork = 0;
    var lectures = 0;
    var exam = 0;

    $('.course_plan .section').each(function () {
        var section = $(this);
        var isExam = String(section.find('input[name="is_exam"]').first().val()) === '1';
        var sectionControls = 0;

        section.find('.control_work input[name="max_points"]').each(function () {
            sectionControls += numericValue($(this).val());
        });

        if (isExam) {
            exam += sectionControls;
            return;
        }

        controls += sectionControls;
        seminars += numericValue(section.find('input[name="max_seminar_pass_point"]').first().val());
        seminarWork += numericValue(section.find('input[name="max_seminar_work_point"]').first().val());
        lectures += numericValue(section.find('input[name="max_lecture_pass_point"]').first().val());
    });

    $('input[name="max_controls"]').val(displayTotal(controls));
    $('input[name="max_seminars"]').val(displayTotal(seminars));
    $('input[name="max_seminars_work"]').val(displayTotal(seminarWork));
    $('input[name="max_lecrures"]').val(displayTotal(lectures));
    $('input[name="max_exam"]').val(displayTotal(exam));
}

function showCoursePlanSaveState(message, isError) {
    var state = $('.course-plan-save-state');
    state.removeClass('is-success is-error')
        .addClass('is-visible ' + (isError ? 'is-error' : 'is-success'))
        .text(message);
}

$(document).ready(function () {
    recalculateCoursePlanTotals();
});

$(document).on('input change', '.section input, .section select', recalculateCoursePlanTotals);
$(document).ajaxComplete(function () {
    recalculateCoursePlanTotals();
});

//Изменяет course_plan для редактирования
$(document).on('click', '.activate_edit_course_plan', function () {
    var thisCoursePlan = $(this).closest('.course_plan');
    var thisForm = thisCoursePlan.find('.course_plan_form');
    $(this).hide();
    //выключение readonly для полей
    thisForm.find('input#course_plan_name').removeAttr("readonly");
    thisForm.find('textarea#course_plan_desc').removeAttr("readonly");
    thisForm.find('select').prop('disabled', false);
    //вставка кнопки "Обновить информ. о разделе"
    var htmlUpdateBatton = '<button type="button" class="ink-reaction btn btn-gray update_course_plan">Обновить</button>';
    thisForm.find('.update_button_course_plan').filter( ':first' ).html(htmlUpdateBatton);
});

//Обновление основной информации course_plan
$(document).on('click', '.update_course_plan', function () {
    var thisCoursePlan = $(this).closest('.course_plan');
    var thisForm = thisCoursePlan.find('.course_plan_form');
    var formData = thisForm.serialize();
    $.ajax({
        type: 'PATCH',
        beforeSend: function (xhr) {
            var token = $('meta[name="csrf-token"]').attr('content');

            if (token) {
                return xhr.setRequestHeader('X-CSRF-TOKEN', token);
            }
        },
        url:   '/course_plan/update',
        data:  formData,
        success: function(data){
            var thisCoursePlan = $('#course_plan'+data.idCoursePlan);
            var thisForm = thisCoursePlan.find('.course_plan_form');

            if($.isEmptyObject(data.error)){
                // замена header card на новое имя
                thisCoursePlan.find('header').filter( ':first' ).html('Учебный план: '+ data.courseName);
                //включение readonly для полей
                thisForm.find('input').attr('readonly', true);
                thisForm.find('textarea').attr('readonly', true);
                thisForm.find('select').prop('disabled', true);
                //удаление кнопки "Обновить доп. инфор о разделе"
                thisForm.find('.update_button_course_plan').empty();
                // удаление сообщений об ошибках
                var currentErrorDiv = thisForm.find('.print-error-msg').filter( ':first' );
                currentErrorDiv.find("ul").html('');
                currentErrorDiv.css('display','none');
                //отображение иконки редактировать
                $('.activate_edit_course_plan').show();
                showCoursePlanSaveState('Изменения сохранены.', false);
            }else{
                //добавление в html сообщений об ошибках
                var divError = thisForm.find('.print-error-msg').filter( ':first' );
                divError.find("ul").html('');
                divError.css('display','block');
                $.each( data.error, function( key, value ) {
                    divError.find("ul").append('<li>'+value+'</li>');
                });
            }
        },
        error: function(xhr) {
            var message = 'Не удалось сохранить учебный план.';
            if (xhr.status === 419 || (xhr.responseJSON && xhr.responseJSON.csrf)) {
                message = 'Сессия устарела. Обновите страницу и повторите действие.';
            }
            showCoursePlanSaveState(message, true);
        }
    });

});

//Удаление учебный план
$(document).on('click', '.delete_couse_plan', function () {
    if (confirm("Удалить учебный план ?")) {

    } else {
        return false;
    }

});

//Утверждение учебного плана
$(document).on('click', '.check_points_course_plan', function () {
    var idCoursePlan = $(this).attr('data-id-course-plan');
    var thisCoursePlan = $('#course_plan'+idCoursePlan);
    var nameCoursePlan = thisCoursePlan.find('input[name="course_plan_name"]').val();
            var token = $('meta[name="csrf-token"]').attr('content');
            $.ajax({
                type: 'POST',
                url:   '/course_plan/check_points',
                data:  {id_course_plan: idCoursePlan,
                        _token: token},
                success: function(data){
                    var divError = $('.course_plan').closest('.container-fluid')
                        .find('.print-error-msg').filter( ':first' );
                    if($.isEmptyObject(data.error)){
                        // удаление сообщений об ошибках
                        divError.find("ul").html('');
                        divError.css('display','none');
                        alert('Баллы корректны');
                    } else {
                        //добавление в html сообщений об ошибках
                        divError.find("ul").html('');
                        divError.css('display','block');
                        $.each( data.error, function( key, value ) {
                            divError.find("ul").append('<li>'+value+'</li>');
                        });
                    }
                }
            });

});
