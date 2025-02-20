<?php

// Settings Route

Route::get('/ayarlar', 'Settings\MainController@index')
    ->name('settings')
    ->middleware('admin');

Route::get('/ayarlar/{user}', 'Settings\MainController@one')
    ->name('settings_one')
    ->middleware('admin');

Route::post('/ayarlar/liste', 'Settings\MainController@getList')
    ->name('settings_get_list')
    ->middleware('admin');

Route::post('/ayarlar/all_roles', 'Settings\MainController@allRoles')
    ->name('all_roles')
    ->middleware('admin');

Route::post('/ayar/yetki/ekle', 'Settings\MainController@addList')
    ->name('settings_add_to_list')
    ->middleware('admin');

Route::post('/ayar/yetki/veriOku', 'Settings\MainController@getPermisssionData')
    ->name('get_permission_data')
    ->middleware('admin');

Route::post(
    '/ayar/yetki/veriYaz',
    'Settings\MainController@writePermisssionData'
)
    ->name('write_permission_data')
    ->middleware('admin');

Route::post('/ayar/yetki/sil', 'Settings\MainController@removeFromList')
    ->name('settings_remove_from_list')
    ->middleware('admin');

Route::post('/ayar/log/kaydet', 'Settings\MainController@setLogForwarding')
    ->name('set_log_forwarding')
    ->middleware('admin');

Route::get('/market/yonlendir', 'Settings\MainController@redirectMarket')
    ->name('redirect_market')
    ->middleware('admin');
Route::get('/market/baglaAuth', 'Settings\MainController@connectMarket')
    ->name('connect_market')
    ->middleware('admin');

Route::post('/ayar/log/oku', 'Settings\MainController@getLogSystem')
    ->name('get_log_system')
    ->middleware('admin');

Route::view('/ayar/sunucu', 'settings.server')
    ->middleware('admin')
    ->name('settings_server');

Route::post('/yetki/veriEkle', 'Settings\MainController@addVariable')
    ->name('permission_add_variable')
    ->middleware('admin');

Route::post('/yetki/veriSil', 'Settings\MainController@removeVariable')
    ->name('permission_remove_variable')
    ->middleware('admin');

Route::post(
    '/ayar/eklenti/fonksiyonlar',
    'Settings\MainController@getExtensionFunctions'
)
    ->middleware('admin')
    ->name('extension_function_list');

Route::post(
    '/ayar/eklenti/fonksiyonlar/ekle',
    'Settings\MainController@addFunctionPermissions'
)
    ->middleware('admin')
    ->name('extension_function_add');

Route::post(
    '/ayar/eklenti/fonksiyonlar/sil',
    'Settings\MainController@removeFunctionPermissions'
)
    ->middleware('admin')
    ->name('extension_function_remove');

Route::post('/ayar/ldap', 'Settings\MainController@saveLDAPConf')
    ->middleware('admin')
    ->name('save_ldap_conf');

Route::post('/ayarlar/saglik', 'Settings\MainController@health')
    ->middleware('admin')
    ->name('health_check');

Route::post('/kullaniciGetir', 'Settings\MainController@getUserList')
    ->middleware('admin')
    ->name('get_user_list_admin');

Route::post('/kullaniciGetirBasit', 'Settings\MainController@getSimpleUserList')
    ->middleware('admin')
    ->name('get_user_list_admin_simple');

Route::view('/sifreDegistir', 'user.password')
    ->middleware('auth')
    ->name('password_change');

Route::post('/sifreDegistir', 'UserController@forcePasswordChange')
    ->middleware('auth')
    ->name('password_change_save');

Route::post('/dnsOku', 'Settings\MainController@getDNSServers')
    ->middleware('admin')
    ->name('get_liman_dns_servers');

Route::post('/inceAyarlar/oku', 'Settings\MainController@getLimanTweaks')
    ->middleware('admin')
    ->name('get_liman_tweaks');

Route::post('/inceAyarlar/yaz', 'Settings\MainController@setLimanTweaks')
    ->middleware('admin')
    ->name('set_liman_tweaks');

Route::post('/dnsYaz', 'Settings\MainController@setDNSServers')
    ->middleware('admin')
    ->name('set_liman_dns_servers');

Route::post('/uploadLoginLogo', 'Settings\MainController@uploadLoginLogo')
    ->middleware('admin')
    ->name('upload_login_logo');

Route::post('/testMailSettings', 'Settings\MainController@testMailSettings')
    ->middleware('admin')
    ->name('test_mail_settings');
