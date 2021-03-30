@extends('layouts.master')

@section('title', 'Page Title')

@section('sidebar')
  @parent

  <p>Этот элемент будет добавлен к главной боковой панели.</p>
@stop

@section('content')
  <div>
    Услуга: <select id="sel_service">
        @foreach ($services as $service)
            <option value="{{ $service->id }}">{{ $service->name }}</option>
        @endforeach
    </select>
    <input id="sel_date" type="date">
    <button id="get_unoccupied_schedule">get_unoccupied_schedule</button>
    <div id="shedule_result"></div>
    <br/>
    <textarea id="request_comment" maxlength="333" style="width:555px; height:77px;"></textarea>
    <br/>
    <div id="tik"></div>
    <div id="result" style="display:none;">получили ответ: <span/> </div>
  </div>

  <style>
  #shedule_result {border:solid 1px; display: flex; flex-wrap: wrap; justify-content: flex-start;}
  #shedule_result > div {width:15%; color:gray;}
  </style>

  <script>
  $(function() {
    let salon_id = 4;

    $(document).on('click', '#shedule_result button', function() {
		console.log( $(this).data('time') );

		$.ajax('/send-request-to-salon', {
			type: 'GET',
			data: {
				salon_id: salon_id,
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

        $.ajax('/get-unoccupied-schedule', {
			data: {
				salonId: salon_id,
				serviceId: $("#sel_service").val(),
				date: $("#sel_date").val(),
			},
			success: function(res) {

				//console.log( res );
				//let shiftId = 1;
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
