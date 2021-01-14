<html>
  <head>
    <title>App Name - @yield('title')</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.14.1/moment.min.js"></script>
  </head>
  <body>
    @section('sidebar')
      Это - главная боковая панель.
    @show

    <div class="container">
      @yield('content')
    </div>
  </body>
</html>
