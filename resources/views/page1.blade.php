@extends('layouts.master')

@section('title', 'Page Title')

@section('sidebar')
  @parent

  <p>Этот элемент будет добавлен к главной боковой панели.</p>
@stop

@section('content')
  <div>
    Услуга: <select id="sel_service"></select>
    <div id="salons_for_service_list">
    </div>
    <br/>
    <input id="sel_date" type="date">
    <button id="get_unoccupied_schedule">выбрать свободное время</button>
    <h4 id="shedule_title"></h4>
    <div id="shedule_result"></div>
    <br/>
    доп. инфа:
    <br/>
    <textarea id="request_comment" maxlength="333" style="width:555px; height:77px;"></textarea>
    <br/>
    <div id="tik"></div>
    <div id="result" style="display:none;">получили ответ: <span/> </div>
  </div>

  <style>
  #shedule_result {border:solid 1px; display: flex; flex-wrap: wrap; justify-content: flex-start;}
  #shedule_result > div {width:15%; color:gray;}
  #salons_for_service_list  li:hover {background:#eee;}
  </style>

  <script>
  $(function() {

    $.ajax('/get-services-list', {
        success: function(res) {
            for (let k1 in res) {
                let cat = res[k1];
                let optgroup = $(`<optgroup label="${cat.name}" />`).appendTo('#sel_service');
                for (let k2 in cat.services) {
                    let serv = cat.services[k2];
                    optgroup.append(`<option value="${serv.id}">${serv.name}</option>`);
                }
            }
        }
    });

    $("#sel_service").on("change", function(e)	{
		$.ajax('/get-salons-performing-service', {
            data: {
                service_id: e.target.value,
            },
            success: function(res) {
                let ttt = '';
                for (let k1 in res) {
                    let salon = res[k1];
                    ttt += `<label><li>
                        <input type="radio" name="salon" value="${salon.id}">
                        <b>${salon.name}</b>
                        <br>
                        ${salon.price_default} руб.
                    </li></label>`;
                }
                $('#salons_for_service_list').html(ttt);
            }
		});
    });

    $(document).on('click', '#shedule_result button', function() {
		//console.log( $(this).data('time') );

		$.ajax('/send-request-to-salon', {
			type: 'GET',
			data: {
				salon_id: $("input[name=salon]:checked").val(),
				service_id: $('#sel_service').val(),
				desired_time: $(this).data('time'),
				comment: $('#request_comment').val(),
			},
			success: function(res) {
				if (res.message) {
					$('#result').show().children('span').text(res.message);
				}
			},
		});
		$('#request_comment').val('');
		$('#tik').text('отправляем запрос...');
		let tiks = 60;
		$('#result').hide();
        let intervalId = setInterval(function() {
            $('#tik').text(tiks);

            if ($('#result').is(':visible')) {
                clearInterval(intervalId);
            }
            tiks--;
            if (tiks < 0 ) {
                $('#result').text('кончилось время ожидания ответа').show();
                clearInterval(intervalId);
            }
        }, 1000);
    })

    $("#sel_date").on("change", function(e)	{
		console.log(e.target.value);
    });

    $("#get_unoccupied_schedule").on('click', function() {
		let $shedule_result = $('#shedule_result');
		$shedule_result.html('');
		let $input_salon_selected = $("input[name=salon]:checked");

        $.ajax('/get-unoccupied-schedule', {
			data: {
				salonId: $input_salon_selected.val(),
				serviceId: $("#sel_service").val(),
				date: $("#sel_date").val(),
			},
			success: function(res) {
				//console.log( res );
				//let shiftId = 1;
				$("#shedule_title").text("Свободное время для " + $input_salon_selected.next('b').text() + ":");

				for(let i = 0; i < new Date(Object.keys(res)[0] * 60 * 1000).getMinutes() /10; i++ ) {
					$('<div/>').appendTo($shedule_result);
				}
				for (let u in res) {
					let d = moment.utc(u * 60 * 1000);
					//let dd = d.getHours() + ':' + String(d.getMinutes()).padStart(2, '0');


					$(`<div>${res[u].free
						? `<button data-time="${d.format('YYYY-MM-DD HH:mm')}">${d.format('HH:mm')}</button>`
						: d.format('HH:mm')}</div>`).appendTo($shedule_result);
				}

            },
        });
    });

  });
  </script>
@stop
