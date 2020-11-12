<?php

namespace App\Http\Controllers\CronMail;

use App\Http\Controllers\Controller;
use App\Jobs\CronEmailJob;
use App\Models\CronMail;
use App\Models\Extension;
use App\Models\Server;
use App\User;
use Illuminate\Contracts\Bus\Dispatcher;

class MainController extends Controller
{
    public function getMailTags()
    {
        $path = "/liman/extensions/" . strtolower(extension()->name) . "/db.json";
        if(!is_file($path)){
            return respond("Bu eklentinin bir veritabanı yok!",201);
        }

        $file = file_get_contents($path);

        $json = json_decode($file,true);

        if(json_last_error() != JSON_ERROR_NONE){
            return respond("Eklenti veritabanı okunamıyor.",201);
        }

        if(array_key_exists("mail_tags",$json)){
            return respond($json["mail_tags"]);
        }else{
            return respond([]);
        }
    }

    public function addCronMail()
    {
        $obj = new CronMail(request()->all());
        $obj->last = 0;
        if ($obj->save()){
            return respond("Mail ayarı başarıyla eklendi");
        }else{
            return respond("Mail ayarı eklenemedi!",201);
        }
    }

    public function deleteCronMail()
    {
        $obj = CronMail::find(request("cron_id"));

        if($obj == null){
            return respond("Bu mail ayarı bulunamadı!");
        }

        if ($obj->delete()){
            return respond("Mail ayarı başarıyla silindi");
        }else{
            return respond("Mail ayarı silinemedi!",201);
        }
    }

    private $tagTexts = [];

    private function getTagText($key,$extension_name){
        if(!array_key_exists($extension_name,$this->tagTexts)){
            $file = file_get_contents("/liman/extensions/" . strtolower($extension_name) . "/db.json" );
            $json = json_decode($file,true);
            if(json_last_error() != JSON_ERROR_NONE){
                return $key;
            }
            $this->tagTexts[$extension_name] = $json;
        }

        if(!array_key_exists("mail_tags",$this->tagTexts[$extension_name])){
            return $key;
        }
        foreach($this->tagTexts[$extension_name]["mail_tags"] as $obj){
            if($obj["tag"] == $key){
                return $obj["description"];
            }
        }
        return $key;
    }

    public function getCronMail()
    {
        $mails = CronMail::all()->map(function($obj){
            $ext = Extension::find($obj->extension_id);
            $obj->extension_name = $ext->display_name;
            $obj->username = User::find($obj->user_id)->name;
            $obj->server_name = Server::find($obj->server_id)->name;
            $obj->tag_string = $this->getTagText($obj->target,$ext->name);
            return $obj;
        });
        return view("settings.mail",[
            "cronMails" => $mails
        ]);
    }

    public function sendNow()
    {
        $obj = CronMail::find(request("cron_id"));

        if($obj == null){
            return respond("Bu mail ayarı bulunamadı!");
        }

        $obj->update([
            "last" => 0
        ]);

        $job = (new CronEmailJob(
            $obj
        ))->onQueue('cron_mail');
        app(Dispatcher::class)->dispatch($job);

        return respond("İşlem başlatıldı, tamamlandığında size mail ulaşacaktır.");
    }

    public function getView()
    {
        return view("settings.add_mail");
    }
}
