@extends('layouts.app')

@section('content')
    @php($extension = json_decode(file_get_contents(env("EXTENSIONS_PATH") .strtolower(extension()->name) . DIRECTORY_SEPARATOR . "db.json"),true))

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{route('home')}}">{{__("Ana Sayfa")}}</a></li>
            <li class="breadcrumb-item" aria-current="page"><a
                        href="{{route('extensions_settings')}}">{{__("Eklenti Yönetimi")}}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $extension["name"] }}</li>
        </ol>
    </nav>
    @include('errors')
    <div class="card">
        <div class="card-header p-2">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="pill" href="#tab_1" role="tab" aria-selected="true">{{__("Genel Ayarlar")}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="pill" href="#tab_2" role="tab" >{{__("Eklenti Veritabanı")}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="pill" href="#tab_2_2" role="tab">{{__("Sayfa Ayarları")}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="pill" href="#tab_3" role="tab">{{__("Widgetlar")}}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="pill" href="#tab_4" role="tab">{{__("Fonksiyonlar")}}</a>
                </li>
            </ul>
        </div>
        <div class="card-body">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab_1" role="tabpanel">
                        <h3>{{__("Eklenti Adı")}}</h3>
                        <input id="extensionName" type="text" class="form-control" value="{{$extension["name"]}}" disabled required>
                        <h3>{{__("Yayınlayan")}}</h3>
                        <input type="text" name="name" class="form-control" value="{{$extension["publisher"]}}" disabled required>
                        <h3>{{__("Destek Email'i")}}</h3>
                        <input id="support" type="text" name="email" class="form-control" value="{{$extension["support"]}}" required>
                        <h3>{{__("Logo (Font Awesome Ikon)")}}</h3>
                        <input id="icon" type="text" name="icon" class="form-control" value="{{$extension["icon"]}}" required>
                        <h3>{{__("Versiyon")}}</h3>
                        <input id="version" type="text" name="version" class="form-control" value="{{$extension["version"]}}" required>
                        <h3>{{__("Ayar Doğrulama Fonksiyonu/Betiği")}}</h3>
                        <input id="verification" type="text" name="verification" class="form-control" value="{{array_key_exists("verification",$extension) ? $extension["verification"] : ""}}" required>
                        <h3>{{__("Servis Adı yada Kontrol Etmek için Port")}}</h3>
                        <input id="service" type="text" name="service" class="form-control" value="{{$extension["service"]}}" required>
                        <h3>{{__("SSL Sertifikası Eklenecek Portlar")}}</h3>
                        <small>{{__("Birden fazla port yazmak için aralarında virgül bırakabilirsiniz.")}}</small>
                        <input id="sslPorts" type="text" name="service" class="form-control" value="{{array_key_exists("sslPorts",$extension) ? $extension["sslPorts"] : ""}}" required><br>
                        <button class="btn btn-success btn-sm" onclick="updateExtension('general')">{{__("Kaydet")}}</button>
                    </div>
                    <div class="tab-pane fade show" id="tab_2" role="tabpanel">
                        @include('modal-button',[
                            "class" => "btn-success btn-sm",
                            "target_id" => "add_database",
                            "text" => "Veri Ekle"
                        ])<br><br>
                        @include('table',[
                            "value" => collect($extension["database"])->map(function($item){
                                $item['required'] = isset($item['required']) && $item['required'] ? 'on' : '';
                                return $item;
                            }),
                            "title" => [
                                "Adı" , "Türü" , "Variable Adı", "*hidden*" , "*hidden*", "*hidden*", "*hidden*"
                            ],
                            "display" => [
                                "name" , "type", "variable", "variable:variable_old", "type:type_old", "name:name_old", "required:required"
                            ],
                            "menu" => [
                                "Ayarları Düzenle" => [
                                    "target" => "edit_database",
                                    "icon" => "  context-menu-icon-edit"
                                ],
                                "Sil" => [
                                    "target" => "remove_database",
                                    "icon" => " context-menu-icon-delete"
                                ]
                            ]
                        ])
            
                        @include('modal',[
                            "id"=>"add_database",
                            "title" => "Veri Ekle",
                            "url" => route('extension_settings_add'),
                            "next" => "reload",
                            "inputs" => [
                                "Adı" => "name:text:Veri adı oluşturulan formlarda gösterilmek için kullanılır.",
                                "Türü" => "type:text:Verinizin türü form elemanını belirler. Örneğin text, password vs.",
                                "Variable Adı" => "variable:text:Eklenti içinden veriye bu isim ile erişirsiniz.",
                                "Zorunlu Alan" => "required:checkbox",
                                "table:database" => "table:hidden"
                            ],
                            "submit_text" => "Veri Ekle"
                        ])
            
                        @include('modal',[
                            "id"=>"edit_database",
                            "title" => "Veri Düzenle",
                            "url" => route('extension_settings_update'),
                            "next" => "updateTable",
                            "inputs" => [
                                "Adı" => "name:text",
                                "Türü" => "type:text",
                                "Variable Adı" => "variable:text",
                                "Sayfa Adı:a" => "name_old:hidden",
                                "Türü:a" => "type_old:hidden",
                                "Variable Adı:a" => "variable_old:hidden",
                                "Zorunlu Alan" => "required:checkbox",
                                "table:database" => "table:hidden"
                            ],
                            "submit_text" => "Veri Düzenle"
                        ])
                        @include('modal',[
                            "id"=>"remove_database",
                            "title" => "Veri'yi Sil",
                            "url" => route('extension_settings_remove'),
                            "next" => "reload",
                            "text" => "Veri'yi silmek istediğinize emin misiniz? Bu işlem geri alınamayacaktır.",
                            "inputs" => [
                                "Sayfa Adı:a" => "name:hidden",
                                "Türü:a" => "type:hidden",
                                "Variable Adı:a" => "variable:hidden",
                                "table:database" => "table:hidden"
                            ],
                            "submit_text" => "Veri'yi Sil"
                        ])
                    </div>
                    <div class="tab-pane fade show" id="tab_2_2" role="tabpanel">
                        @include('modal-button',[
                            "class" => "btn-success btn-sm",
                            "target_id" => "add_view",
                            "text" => "Sayfa Ekle"
                        ])<br><br>
                        @include('table',[
                            "value" => $extension["views"],
                            "title" => [
                                "Sayfa Adı" , "*hidden*", "*hidden*"
                            ],
                            "display" => [
                                "name" , "name:name_old", "scripts:scripts_old"
                            ],
                            "menu" => [
                                "Kodu Düzenle" => [
                                    "target" => "editPage",
                                    "icon" => " context-menu-icon-edit"
                                ],
                                "Sil" => [
                                    "target" => "remove_view",
                                    "icon" => " context-menu-icon-delete"
                                ]
                            ]
                        ])
                        @include('modal',[
                            "id"=>"add_view",
                            "title" => "Sayfa Ekle",
                            "url" => route('extension_settings_add'),
                            "next" => "reload",
                            "inputs" => [
                                "Sayfa Adı" => "name:text",
                                "table:views" => "table:hidden",
                            ],
                            "submit_text" => "Sayfa Ekle"
                        ])
                        @include('modal',[
                            "id"=>"remove_view",
                            "title" => "Sayfa'yı Sil",
                            "url" => route('extension_settings_remove'),
                            "next" => "reload",
                            "text" => "Sayfa'yı silmek istediğinize emin misiniz? Bu işlem geri alınamayacaktır.",
                            "inputs" => [
                                "Sayfa Adı:a" => "name:hidden",
                                "table:views" => "table:hidden",
                            ],
                            "submit_text" => "Sayfa'yı Sil"
                        ])
                    </div>
                    <div class="tab-pane fade show" id="tab_3" role="tabpanel">
                        @include('modal-button',[
                            "class" => "btn-success btn-sm",
                            "target_id" => "add_widget",
                            "text" => "Widget Ekle"
                        ])<br><br>
                        @include('table',[
                            "value" => $extension["widgets"],
                            "title" => [
                                "Widget Adı" , "Türü" , "Çalışacak Fonksiyon" , "*hidden*", "*hidden*", "*hidden*"
                            ],
                            "display" => [
                                "name" , "type", "target", "name:name_old", "type:type_old", "target:target_old"
                            ],
                            "menu" => [
                                "Düzenle" => [
                                    "target" => "edit_widget",
                                    "icon" => " context-menu-icon-edit"
                                ],
                                "Sil" => [
                                    "target" => "remove_widget",
                                    "icon" => " context-menu-icon-delete"
                                ]
                            ]
                        ])
                        @include('modal',[
                            "id"=>"add_widget",
                            "title" => "Widget Ekle",
                            "url" => route('extension_settings_add'),
                            "next" => "reload",
                            "inputs" => [
                                "Widget Adı" => "name:text:Widgetınızın adı.",
                                "İkon (FontAwesome)" => "icon:text:Widgetınızın sol menüde görüntülenecek ikonu.",
                                "Türü:type:Widget türü widgetın görünümünü belirler." => [
                                    "Sayı" => "count_box",
                                    "Grafik" => "chart"
                                ],
                                "table:widgets" => "table:hidden",
                                "Çalışacak Fonksiyon" => "target:text:Widget verilerinin hangi fonksiyon yada betikten getirileceğini belirler."
                            ],
                            "submit_text" => "Widget Ekle"
                        ])
                        @include('modal',[
                            "id"=>"edit_widget",
                            "title" => "Widget Düzenle",
                            "url" => route('extension_settings_update'),
                            "next" => "updateTable",
                            "inputs" => [
                                "Widget Adı" => "name:text",
                                "İkon (FontAwesome)" => "icon:text",
                                "Türü:type" => [
                                    "Sayı" => "count_box",
                                    "Grafik" => "chart"
                                ],
                                "Çalışacak Fonksiyon" => "target:text",
                                "Widget Adı:a" => "name_old:hidden",
                                "table:widgets" => "table:hidden",
                                "Türü:a" => "type_old:hidden",
                                "Çalışacak Fonksiyon:a" => "target_old:hidden"
                            ],
                            "submit_text" => "Widget Düzenle"
                        ])
                        @include('modal',[
                            "id"=>"remove_widget",
                            "title" => "Widget'ı Sil",
                            "url" => route('extension_settings_remove'),
                            "next" => "reload",
                            "text" => "Widget'ı silmek istediğinize emin misiniz? Bu işlem geri alınamayacaktır.",
                            "inputs" => [
                                "Widget Adı:a" => "name:hidden",
                                "Türü:a" => "type:hidden",
                                "table:widgets" => "table:hidden",
                                "Çalışacak Fonksiyon:a" => "target:hidden"
                            ],
                            "submit_text" => "Widget'ı Sil"
                        ])
                    </div>
                    <div class="tab-pane fade show" id="tab_4" role="tabpanel">
                        <button class="btn btn-success" data-toggle="modal" data-target="#addFunctionModal"><i data-toggle="tooltip" title="Ekle" class="fa fa-plus"></i></button><br><br>
                        @include('table',[
                            "value" => array_key_exists("functions",$extension) ? $extension["functions"] : [],
                            "title" => [
                                "Fonksiyon Adı" , "Çeviri Key'i", "Yetki Sistemi" ,"*hidden*"
                            ],
                            "display" => [
                                "name" , "description", "isActive", "name:old"
                            ],
                            "menu" => [
                                "İzin Parametreleri" => [
                                    "target" => "updateFunctionParameters",
                                    "icon" => "fa-cog"
                                ],
                                "Ayarları Düzenle" => [
                                    "target" => "updateFunctionModal",
                                    "icon" => " context-menu-icon-edit"
                                ],
                                "Sil" => [
                                    "target" => "removeFunctionModal",
                                    "icon" => " context-menu-icon-delete"
                                ]
                            ]
                        ])
            
                        @include('modal',[
                            "id"=>"addFunctionModal",
                            "title" => "Fonksiyon Ekle",
                            "url" => route('extension_add_function'),
                            "next" => "reload",
                            "inputs" => [
                                "Fonksiyon Adı" => "name:text",
                                "Açıklama (Çeviri Key)" => "description:text",
                                "Yetki Sistemine Dahil Et" => "isActive:checkbox",
                            ],
                            "submit_text" => "Fonksiyon Ekle"
                        ])
            
                        @include('modal',[
                            "id"=>"updateFunctionModal",
                            "title" => "Fonksiyon Duzenle",
                            "url" => route('extension_update_function'),
                            "next" => "reload",
                            "inputs" => [
                                "Fonksiyon Adı" => "name:text",
                                "Açıklama (Çeviri Key)" => "description:text",
                                "Yetki Sistemine Dahil Et" => "isActive:checkbox",
                                "-:-" => "old:hidden"
                            ],
                            "submit_text" => "Fonksiyon Duzenle"
                        ])

                        @component('modal-component',[
                            "id" => "updateFunctionParametersModal",
                            "title" => "Fonksiyon İzin Parametreleri"
                        ])
                            <button class="btn btn-success">
                                <i data-toggle="tooltip" onclick="addFunctionParameters()" title="Ekle" class="fa fa-plus"></i>
                            </button>
                            <div id="functionParameters" class="mt-2"></div>
                        @endcomponent

                        @include('modal',[
                            "id"=>"addFunctionParametersModal",
                            "title" => "Fonksiyon İzin Parametresi Ekle",
                            "url" => route('extension_add_function_parameters'),
                            "next" => "getActiveFunctionParameters",
                            "inputs" => [
                                "Parametre Adı" => "name:text",
                                "Değişken Adı" => "variable:text",
                                "Tip:type" => [
                                    "Yazı" => "text",
                                    "Uzun Metin" => "textarea",
                                    "Sayı" => "number"
                                ],
                                "function_name:function_name" => "function_name:hidden",
                            ],
                            "submit_text" => "Kaydet"
                        ])
            
                        @include('modal',[
                            "id"=>"removeFunctionModal",
                            "title" => "Fonksiyonu Sil",
                            "url" => route('extension_remove_function'),
                            "next" => "reload",
                            "text" => "Fonksiyonu silmek istediğinize emin misiniz? Bu işlem geri alınamayacaktır.",
                            "inputs" => [
                                "-:-" => "name:hidden"
                            ],
                            "submit_text" => "Fonksiyonu Sil"
                        ])
                    </div>
                </div>
        </div>
    </div>
    <script>
        function editPage(element){
            let page = $(element).find("#name").text();
            location.href = location.protocol+'//'+location.host+location.pathname + "/" + page;
        }

        function updateExtension(type,tableId = null){
            Swal.fire({
                position: 'center',
                type: 'info',
                title: '{{__("Kaydediliyor...")}}',
                showConfirmButton: false,
            });
            let data = new FormData();
            data.append('type',type);
            data.append('name',$("#extensionName").val());
            data.append('icon',$("#icon").val());
            data.append('support',$("#support").val());
            data.append('version',$("#version").val());
            data.append('service',$("#service").val());
            data.append('sslPorts',$("#sslPorts").val());
            data.append('verification',$("#verification").val());
            request('{{route('extension_settings_update')}}',data,function(){
                Swal.fire({
                    position: 'center',
                    type: 'success',
                    title: "{{__("Başarıyla kaydedildi")}}",
                    showConfirmButton: false,
                    timer: 1500
                });
                setTimeout(function(){
                    location.reload();
                },1500);
            }, function(response){
                let error = JSON.parse(response);
                Swal.fire({
                    type: 'error',
                    title: error.message,
                    timer : 2000
                });
            });
        }

        var activeFunction = null;

        function getFunctionParameters(function_name){
            Swal.fire({
                position: 'center',
                type: 'info',
                title: '{{__("Yükleniyor...")}}',
                showConfirmButton: false,
            });

            let data = new FormData();
            data.append('function_name',function_name);
            activeFunction = function_name;

            request('{{route('extension_function_parameters')}}',data,function(response){
                Swal.close();
                $('#updateFunctionParametersModal').find('#functionParameters').html(response);
                $('#updateFunctionParametersModal')
                    .find('#functionParameters table')
                    .DataTable(dataTablePresets('normal'));
                $('#updateFunctionParametersModal').modal('show');
            }, function(response){
                let error = JSON.parse(response);
                Swal.fire({
                    type: 'error',
                    title: error.message,
                    timer : 2000
                });
            });
        }

        function getActiveFunctionParameters(){
            $('#addFunctionParametersModal').modal('hide');
            getFunctionParameters(activeFunction);
        }

        function updateFunctionParameters(row){
            let function_name = $(row).find("#name").text();
            getFunctionParameters(function_name);
        }

        function addFunctionParameters(){
            $('#addFunctionParametersModal').find('input[name=function_name]').val(activeFunction);
            $('#addFunctionParametersModal').modal('show');
        }

        function deleteFunctionParameters(row){
            Swal.fire({
                title: "{{ __('Onay') }}",
                text: "{{ __('Parametreyi silmek istediğinizden emin misiniz?') }}",
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: "{{ __('İptal') }}",
                confirmButtonText: "{{ __('Sil') }}"
            }).then((result) => {
                if (result.value) {
                    Swal.fire({
                        position: 'center',
                        type: 'info',
                        title: '{{__("Yükleniyor...")}}',
                        showConfirmButton: false,
                    });
                    let parameter_variable = $(row).find("#variable").text();
                    let data = new FormData();
                    data.append('parameter_variable',parameter_variable);
                    data.append('function_name',activeFunction);
                    
                    request('{{route('extension_remove_function_parameters')}}',data,function(response){
                        Swal.close();
                        getFunctionParameters(activeFunction);
                    }, function(response){
                        let error = JSON.parse(response);
                        Swal.fire({
                            type: 'error',
                            title: error.message,
                            timer : 2000
                        });
                    });
                }
            });
        }
    </script>
@endsection
