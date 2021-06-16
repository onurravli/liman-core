@extends("layouts.app")

@section("content")
<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{route('home')}}">{{__("Ana Sayfa")}}</a></li>
                @if (!request()->category_id && !request()->search_query)
                <li class="breadcrumb-item active" aria-current="page">{{__("Eklenti Mağazası")}}</li>
                @else
                    <li class="breadcrumb-item"><a href="{{route('market')}}">{{__("Eklenti Mağazası")}}</a></li>
                    @php
                    if (request()->category_id) {
                        $category_name = "";
                        foreach ($categories as $category) {
                            if ($category->id == request()->category_id){
                                $category_name = $category->name;
                                break;
                            }
                        }
                    } else {
                        $category_name = request()->search_query;
                    }
                    @endphp
                    <li class="breadcrumb-item active" aria-current="page">{{ $category_name }} eklentileri</li>
                @endif
            </ol>
        </nav>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-body py-0 pl-2 row">
                <div class="col-6">
                <a href="{{ route('market') }}" class="extensions_category">Tüm Eklentiler</a>
                @foreach ($categories as $category)
                    <a href="{{ route('market_kategori', $category->id) }}" class="extensions_category">{{ $category->name }}</a>
                @endforeach
                <button class="btn btn-dark mt-2 ml-1" style="height: 38px;" onclick="openExtensionUploadModal()"><i class="fas fa-download mr-1"></i>Eklenti yükle</button>
                </div>
                <div class="col-6">
                    <form action="{{ route('market_search') }}" method="GET">
                        <div class="input-group mt-2 w-50 float-right">
                            <input name="search_query" class="form-control py-2" @isset(request()->search_query) value="{{request()->search_query}}" @endisset type="search" placeholder="Eklentilerde ara..." id="extension_search">
                            <span class="input-group-append">
                                <button class="btn btn-dark" type="submit">
                                    <i class="fa fa-search"></i>
                                </button>
                            </span>
                        </div>
                    </form>
                    <button onclick="window.location.href='/ayarlar#extensions'" class="btn btn-dark mt-2 mr-2 float-right" data-toggle="tooltip" title="Eklenti Ayarları" style="height: 38px;" ><i class="fas fa-cogs"></i></button>
                </div>
            </div>
        </div>
    </div>

    @foreach ($apps as $app)
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-3">
                    @if ($app->iconPath)
                        <img src="{{ env('MARKET_URL') . '/' . $app->iconPath }}" alt="{{ $app->name }}" class="img-fluid">
                    @else
                        <i class="fas fa-puzzle-piece" style="font-size: 100px;"></i>
                    @endif
                    </div>
                    <div class="col-6">
                        <h4 style="font-weight: 600;">{{ $app->name }}</h4>
                        <p class="mb-0">{{ $app->shortDescription }}</p>
                    </div>
                    <div class="col-3 text-center">
                    @if ($app->publicVersion)
                        @if (!$app->isInstalled)
                        <button id="installBtn" class="btn btn-success mb-2 w-100" onclick="installExtension('{{ $app->packageName }}')">
                            <i class="fas fa-download mr-1"></i> Yükle
                        </button>
                        @endif

                        @if ($app->publicVersion->needsToBeUpdated)
                        <button id="installBtn" class="pl-1 pr-1 btn btn-warning mb-2 w-100" onclick="installExtension('{{ $app->packageName }}')">
                            <i class="fas fa-download mr-1"></i> Güncelle
                        </button>
                        @elseif ($app->isInstalled)
                        <button id="installBtn" class="btn btn-secondary mb-2 w-100 disabled" disabled>
                            <i class="fas fa-check mr-1"></i> Kurulu
                        </button>
                        @endif
                    @else
                        <button class="btn btn-primary mb-2" onclick="window.open('https://liman.havelsan.com.tr/iletisim/')">
                            <i class="fas fa-shopping-cart mr-1"></i> Satın Al
                        </button>
                    @endif
                        <a href="{{ env('MARKET_URL') . '/Application/' . mb_strtolower($app->packageName) }}" target="_blank"><small>Daha fazla detay</small></a>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col-6">
                        @if ($app->publicVersion)
                        <small class="font-italic">
                            <b>Versiyon:</b> {{ $app->publicVersion->versionName }}
                        @else
                        <small>
                            Ücretli eklenti
                        @endif
                        </small>
                    </div>
                    <div class="col-6 text-right">
                        <small>
                            <b>Geliştirici:</b> {{ $app->publisher->userName }}
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endforeach

    @if (count($apps) == 0)
    <div class="container-fluid">
        <div class="error-page mt-5">
            <h2 class="headline text-warning"><i class="fas fa-exclamation-triangle text-warning"></i></h2>
            <div class="error-content">
                <h3>Uyarı</h3>
                <p>
                    {{ request()->search_query }} aramasına uygun bir eklenti bulamadık.
                    <br><button class="btn btn-success mt-3" onclick="history.back()">{{__("Geri Dön")}}</button>
                </p>
            </div>
        </div>
    </div>
    @endif
</div>

@include('modal',[
    "id"=>"extensionUpload",
    "title" => "Eklenti Yükle",
    "url" => route('extension_upload'),
    "next" => "reload",
    "error" => "extensionUploadError",
    "inputs" => [
        "Lütfen Eklenti Dosyasını(.lmne) Seçiniz" => "extension:file",
    ],
    "submit_text" => "Yükle"
])

<script>
    function openExtensionUploadModal() {
        $("#extensionUpload").modal('show');
    }

    $("#extensionUpload input").on('change',function(){
        if(this.files[0].size / 1024 / 1024 > 100){
            $(this).val('');
            showSwal('{{__("Maksimum eklenti boyutunu (100MB) aştınız!")}}','error');
        }
    });

    function installExtension(package_name) 
    {
        showSwal('Kuruluyor...', 'info');
        let extdata = new FormData();
        request(`/market/kur/${package_name}`, extdata, function(response) {
            Swal.close();
            showSwal("Eklenti başarıyla kuruldu!", "success", 1500);
            setTimeout(_ => {
                window.location = "/ayarlar#extensions"
            }, 1500);
        }, function(err) {
            installUnsignedExtension(err, package_name);
        });
    }

    function installUnsignedExtension(response, package_name)
    {
        var error = JSON.parse(response);
        if(error.status == 203){
            Swal.fire({
                title: "{{ __('Onay') }}",
                text: error.message,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: "{{ __('İptal') }}",
                confirmButtonText: "{{ __('Tamam') }}"
            }).then((result) => {
                if (result.value) {
                    showSwal('Kuruluyor...', 'info');
                    let extdata = new FormData();
                    extdata.append("force", "1");
                    request(`/market/kur/${package_name}`, extdata, function(response) {
                        console.log(response);
                        Swal.close();
                        showSwal("Eklenti başarıyla kuruldu!", "success", 1500);
                        setTimeout(_ => {
                            window.location = "/ayarlar#extensions"
                        }, 1500);
                    }, function(err) {
                        console.log(err);
                        var error = JSON.parse(err);
                        Swal.close();
                        showSwal(error.message, "error", 3000);
                    });
                }
            });
        } else {
            showSwal("Eklenti kurulumunda hata oluştu!", "error", 3000);
        }
    }

    function extensionUploadError(response){
        var error = JSON.parse(response);
        if(error.status == 203){
            $('#extensionUpload_alert').hide();
            Swal.fire({
                title: "{{ __('Onay') }}",
                text: error.message,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                cancelButtonText: "{{ __('İptal') }}",
                confirmButtonText: "{{ __('Tamam') }}"
            }).then((result) => {
                if (result.value) {
                    showSwal('{{__("Yükleniyor...")}}','info');
                    var data = new FormData(document.querySelector('#extensionUpload_form'))
                    data.append("force", "1");
                    request('{{route('extension_upload')}}',data,function(response){
                        Swal.close();
                        showSwal('Eklenti başarıyla kuruldu!', 'success');
                        setTimeout(() => {
                            reload();
                        }, 1500);
                    }, function(response){
                        var error = JSON.parse(response);
                        Swal.close();
                        $('#extensionUpload_alert').removeClass('alert-danger').removeAttr('hidden').removeClass('alert-success').addClass('alert-danger').html(error.message).fadeIn();
                    });
                }
            });
        }
    }

    $(".extensions_category").each(function() {  
        if (this.href == window.location.href) {
            $(this).addClass("active_tab");
        }
    });
</script>
@endsection