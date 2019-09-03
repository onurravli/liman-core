@extends('layouts.app')

@section('content')
    @if(!$widgets->count())
      <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
              <li class="breadcrumb-item active" aria-current="page">{{__("Ana Sayfa")}}</li>
          </ol>
      </nav>
    @endif
    @include('l.errors')
    <section class="content sortable-widget">
        @if($widgets->count())
            @foreach($widgets as $widget)
              @if($widget->type==="count_box" || $widget->type==="")
                <div class="col-md-3 col-sm-4 col-xs-12" id="{{$widget->id}}">
                    <div class="info-box overlay-wrapper" title="{{$widget->server_name . " " . __("Sunucusu")}} -> {{$widget->title}}">
                        <span class="info-box-icon bg-aqua" style="padding:20px; display: none;"><i class="fa fa-{{$widget->text}}"></i></span>
                        <div class="info-box-content" style="display: none;">
                            <span class="info-box-text" id="{{$widget->id}}" title="{{__($widget->title)}}">{{__($widget->title)}}</span>
                            <span class="float-right limanWidget" id="{{$widget->id}}" style="font-size: 20px">{{__("Yükleniyor...")}}</span>
                            <span class="progress-description" title="{{$widget->server_name . " " . __("Sunucusu")}}">{{$widget->server_name . " " . __("Sunucusu")}}</span>
                        </div>
                        <div class="overlay" style="padding: 5px 10px;text-align: center;position: initial;">
                          <i class="fa fa-refresh fa-spin"></i>
                          <span style="font-size: 1.2rem;">{{__("Yükleniyor")}}</span>
                        </div>
                    </div>
                </div>
              @elseif ($widget->type==="chart")
                <div class="col-md-6 limanCharts" id="{{$widget->id}}">
                  <div class="box box-primary" id="{{$widget->id}}Chart">
                    <div class="box-header with-border">
                      <h3 class="box-title">{{$widget->server_name . " " . __("Sunucusu")}} {{__($widget->title)}}</h3>
                      <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                        </button>
                        <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
                      </div>
                    </div>
                    <div class="box-body">
                      <canvas></canvas>
                    </div>
                    <div class="overlay">
                      <i class="fa fa-refresh fa-spin"></i>
                    </div>
                  </div>
                </div>
              @endif
            @endforeach
        @else
            {{__("Liman Sistem Yönetimi'ne Hoşgeldiniz!")}}
        @endif
    </section>
    <style>
    .sortable-widget{
      cursor: default;
    }
    </style>
    <script>
        $(".sortable-widget").sortable({
            stop: function(event, ui) {
                let data = [];
                $(".sortable-widget > div").each(function(i, el){
                    $(el).attr('data-order', $(el).index());
                    data.push({
                      id: $(el).attr('id'),
                      order:  $(el).index()
                    });
                });
                let form = new FormData();
                form.append('widgets', JSON.stringify(data));
                request('{{route('update_orders')}}', form, function(response){});
            }
        });
        $(".sortable-widget").disableSelection();
        let intervals = [];
        let widgets = [];
        let currentWidget = 0;

        $(".limanWidget").each(function(){
            let element = $(this);
            widgets.push({
              'element': element,
              'type': 'countBox'
            });
        });
        $('.limanCharts').each(function(){
            let element = $(this);
            widgets.push({
              'element': element,
              'type': 'chart'
            });
        });
        startQueue()
        setInterval(function(){
            startQueue()
        },{{env("WIDGET_REFRESH_TIME")}});

        function startQueue(){
          currentWidget = 0;
          if(currentWidget >= widgets.length || widgets.length === 0){
            return;
          }
          if(widgets[currentWidget].type === 'countBox'){
            retrieveWidgets(widgets[currentWidget].element, nextWidget)
          }else if(widgets[currentWidget].type === 'chart'){
            retrieveCharts(widgets[currentWidget].element, nextWidget)
          }
        }

        function nextWidget(){
          currentWidget++;
          if(currentWidget >= widgets.length || widgets.length === 0){
            return;
          }
          if(widgets[currentWidget].type === 'countBox'){
            retrieveWidgets(widgets[currentWidget].element, nextWidget)
          }else if(widgets[currentWidget].type === 'chart'){
            retrieveCharts(widgets[currentWidget].element, nextWidget)
          }
        }

        function retrieveWidgets(element, next){
            let info_box = element.closest('.info-box');
            let form = new FormData();
            form.append('widget_id',element.attr('id'));
            request('{{route('widget_one')}}', form, function(response){
                let json = JSON.parse(response);
                element.html(json["message"]);
                info_box.find('.info-box-icon').show();
                info_box.find('.info-box-content').show();
                info_box.find('.overlay').remove();
                if(next){
                  next();
                }
            }, function(error) {
                let json = JSON.parse(error);
                widgets.splice(currentWidget, 1);
                info_box.find('.overlay i').remove();
                info_box.find('.overlay span').remove();
                info_box.find('.overlay').prepend('<i class="fa fa-exclamation-circle" title="'+strip(json.message)+'" style="color: red;"></i><span style="font-size: 1.2rem;">'+json.message+'</span>');
                if(next){
                  next();
                }
              });
        }

        function retrieveCharts(element, next){
            let id = element.attr('id');
            let form = new FormData();
            let info_box = element.closest('.info-box');
            form.append('widget_id', id);
            request('{{route('widget_one')}}', form, function(res){
                let response =  JSON.parse(res);
                let data =  response.message;
                createChart(id+'Chart',data.labels, data.data);
                if(next){
                  next();
                }
            }, function(error) {
                let json = JSON.parse(error);
                widgets.splice(currentWidget, 1);
                info_box.find('.overlay i').remove();
                info_box.find('.overlay span').remove();
                info_box.find('.overlay').prepend('<i class="fa fa-exclamation-circle" title="'+strip(json.message)+'" style="color: red;"></i><span style="font-size: 1.2rem;">'+json.message+'</span>');
                if(next){
                  next();
                }
              });
        }

        function strip(html)
        {
           var tmp = document.createElement("DIV");
           tmp.innerHTML = html;
           return tmp.textContent || tmp.innerText || "";
        }

        function createChart(element, labels, data) {
          $("#" + element + ' .overlay').remove();
          window[element + "Chart"] = new Chart($("#" + element+' .box-body canvas'), {
            type: 'line',
            data: {
              datasets: [{
                data: data,
              }],
              labels: labels
            },
            options: {
              animation: false,
              responsive: true,
              legend: false,
              scales: {
                yAxes: [{
                  ticks: {
                    beginAtZero: true,
                  }
                }]
              },
            }
          })
        }
    </script>
@stop
