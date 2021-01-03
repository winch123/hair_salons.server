@extends('layouts.master')

@section('title', 'Page Title')

@section('sidebar')
  @parent

  <p>Этот элемент будет добавлен к главной боковой панели.</p>
@stop

@section('content')
  <div>
    <select id="sel_service">
        @foreach ($services as $service)
            <option value="{{ $service->id }}">{{ $service->name }}</option>
        @endforeach
    </select>
    <br/>
    <textarea style="width:555px; height:77px;"></textarea>
    <br/>
    <button id="send_data">send data</button>
    <div id="tik"></div>
    <div id="result" style="display:none;">получили ответ: <span/> </div>
  </div>
  <script>
  $(function() {
    let salon_id = 2;
    $("#send_data").on('click', function() {
        $.ajax('/send-request-to-salon', {
	    data: {salon_id: salon_id, service_id: $('#sel_service').val(), desired_time:'2020-12-31 23:10' },
            success: function(res) {
                if (res.message) {
                    $('#result').show().children('span').text(res.message);
                }
            },
        });
        $('#tik').text('отправляем запрос...');
        let tiks = 11;
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
  });
  </script>
@stop
