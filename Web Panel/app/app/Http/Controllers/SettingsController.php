<?php

namespace App\Http\Controllers;

use App\Models\Users;
use App\Models\Singbox;
use App\Models\Admins;
use App\Models\Api;
use Illuminate\Http\Request;
use Auth;
use App\Models\Settings;
use App\Models\Traffic;
use App\Models\Trafficsb;
use App\Models\Ipadapter;
use App\Models\Adapterlist;
use App\Models\CloudflareRotator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Illuminate\Support\Process\ProcessResult;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\ProController;
use Verta;


class SettingsController extends Controller
{
    public function __construct() {
        $this->middleware('auth:admins');

    }
    public function check()
    {
        $user = Auth::user();
        $check_admin = Admins::where('id', $user->id)->get();
        if($check_admin[0]->permission=='reseller')
        {
            exit(view('access'));
        }
    }
    public function defualt()
    {
        $this->check();
        return redirect()->intended(route('settings', ['name' => 'general']));
    }
    public function mod(Request $request,$name)
    {
        $this->check();
        if (!is_string($name)) {
            abort(400, 'Not Valid Username');
        }
        if($name=='night' OR $name=='light')
        {
            Process::run("sed -i \"s/APP_MODE=.*/APP_MODE=$name/g\" /var/www/html/app/.env");
        }
        return redirect()->back()->with('success', 'success');
    }
    public function lang(Request $request,$name)
    {
        $this->check();
        if (!is_string($name)) {
            abort(400, 'Not Valid Username');
        }
        if($name=='fa' OR $name=='en' OR $name=='ru')
        {
            Process::run("sed -i \"s/APP_LOCALE=.*/APP_LOCALE=$name/g\" /var/www/html/app/.env");
        }

        return redirect()->back()->with('success', 'success');
    }
    public function index(Request $request,$name)
    {

        $this->check();
        if (!is_string($name)) {
            abort(400, 'Not Valid Username');
        }

        $setting = Settings::all();
        $ipadapter = Ipadapter::all();
        $iplist = Adapterlist::all();
        $apis =Api::all();

        if($name=='general') {
            $status=$setting[0]->multiuser;
            $tls_port=$setting[0]->tls_port;
            $traffic_base=env('TRAFFIC_BASE');
            return view('settings.general', compact('traffic_base','status','tls_port'));}
        if($name=='backup') {
            $token_bot=env('BOT_TOKEN');
            $id_admin=env('BOT_ID_ADMIN');
            $list = Process::run("ls /var/www/html/app/storage/backup");
            $output = $list->output();
            $backuplist = preg_split("/\r\n|\n|\r/", $output);
            $lists=$backuplist;
            $domain=explode(':',$_SERVER['HTTP_HOST']);
            $domain=$domain[0];
            $webhook_url = 'https://'.$domain.'/sync.php?bot=y';
            $api_url = "https://api.telegram.org/bot$token_bot/getWebhookInfo";
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);

            if ($response === false) {
            } else {
                $webhook_info = json_decode($response, true);

                if ($webhook_info && isset($webhook_info['result'])) {
                    if ($webhook_info['result']['url'] === $webhook_url && $webhook_info['ok'] === true) {
                        $status_webhoock='🟢';
                    } else {
                        $status_webhoock='🔴';
                    }
                } else {
                    $status_webhoock='🔴';
                }

            }
            curl_close($ch);
            return view('settings.backup', compact('lists','token_bot','id_admin','status_webhoock'));
        }
        if($name=='api') {
            $apis=$apis;
            return view('settings.api', compact('apis'));}
        if($name=='block') {
            $check_status = Process::run("sudo iptables -L OUTPUT");
            $output = $check_status->output();
            $output = preg_split("/\r\n|\n|\r/", $output);
            $output = count($output) - 3;
            $status=$output;
            return view('settings.block', compact('status'));
        }
        if($name=='fakeaddress') {return view('settings.fake');}
        if($name=='wordpress') {
            $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
            $http_host=$_SERVER['HTTP_HOST'];
            $output=$http_host.'/';
            $output=explode(':',$output);
            $output=$protocol.'://'.$output[0];
            $address=$output;
            return view('settings.wordpress', compact('address'));
        }
        if($name=='ip-adapter') {
            return view('settings.ip', compact('ipadapter','iplist'));
        }
        if($name=='iprotate') {
            $rotators = CloudflareRotator::orderBy('id','desc')->get();
            $server_ip = trim(@file_get_contents('https://ipinfo.io/ip'));
            return view('settings.iprotate', compact('rotators','server_ip'));
        }
        if($name=='serverip') {
            $cfg = \App\Models\ServerIpRotator::first();
            return view('settings.serverip', compact('cfg'));
        }
        if($name=='autobackup') {
            $ab = \App\Models\AutoBackup::first();
            return view('settings.autobackup', compact('ab'));
        }
        if($name=='mail') {
            return view('settings.mail');
        }
        if($name=='cronjob') {

            function is_https() {
                return (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') ||
                    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
                    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                    (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
            }

            function displayServerURL() {
                $protocol = is_https() ? "https" : "http";
                $serverURL = $protocol . "://" . $_SERVER['HTTP_HOST'];
                return $serverURL;
            }

            $address= displayServerURL();


            exec("sudo cronx", $outputs, $returnVar);
            return view('settings.crontab', compact('outputs','address'));
        }

    }
    public function change_port_ssh(Request $request)
    {
        $this->check();
        $request->validate([
            'port_ssh' => 'required|numeric',
        ]);

        exec("sudo sed -i 's/^\\s*Port\\s.*/Port {$request->port_ssh}/' /etc/ssh/sshd_config", $output, $returnVar);
        if ($returnVar === 0) {
            shell_exec("sed -i 's/PORT_SSH=.*/PORT_SSH={$request->port_ssh}/g' /var/www/html/app/.env");
            shell_exec("sudo sed -i \"s/DEFAULT_HOST =.*/DEFAULT_HOST = \'127.0.0.1:{$request->port_ssh}\'/g\" /usr/local/bin/wss");
            shell_exec("sudo sed -i \"s/connect =.*/connect = 0.0.0.0:{$request->port_ssh}/g\" /etc/stunnel/stunnel.conf");
            shell_exec("sudo systemctl daemon-reload");
            shell_exec("sudo systemctl enable wss");
            shell_exec("sudo systemctl restart wss");
        }
        return response()->json(['message' => __('settings-port-alert-success')]);

    }

    public function change_port_ssh_tls(Request $request)
    {
        $this->check();
        $request->validate([
            'port_ssh_tls' => 'required|numeric',
        ]);
        shell_exec("sudo sed -i \"s/accept =.*/accept = {$request->port_ssh_tls}/g\" /etc/stunnel/stunnel.conf");
        shell_exec("sudo systemctl enable stunnel4");
        shell_exec("sudo systemctl restart stunnel4");
        Settings::where('id', '1')->update(['tls_port' => $request->port_ssh_tls]);
        return response()->json(['message' => __('settings-port-alert-success')]);


    }
    public function update_general(Request $request)
    {
        $this->check();
        $request->validate([
            'direct_login'=>'required|string',
            'lang'=>'required|string',
            'mode'=>'required|string',
            'status_traffic'=>'string',
            'status_multiuser'=>'string',
            'status_day'=>'string',
            'status_log'=>'string',
            'anti_user'=>'string',
        ]);
        if($request->lang=='fa' OR $request->lang=='en' OR $request->lang=='ru')
        {
            Process::run("sed -i \"s/APP_LOCALE=.*/APP_LOCALE=$request->lang/g\" /var/www/html/app/.env");
        }
        if($request->mode=='night' OR $request->mode=='light')
        {
            Process::run("sed -i \"s/APP_MODE=.*/APP_MODE=$request->mode/g\" /var/www/html/app/.env");
        }

        Process::run("sed -i \"s/PANEL_DIRECT=.*/PANEL_DIRECT=$request->direct_login/g\" /var/www/html/app/.env");

        // Custom host (optional). If empty, links fall back to the page URL host.
        $custom_host = trim((string) $request->input('custom_host', ''));
        // allow only safe hostname/domain characters
        if ($custom_host !== '' && !preg_match('/^[A-Za-z0-9.\-:]+$/', $custom_host)) {
            $custom_host = '';
        }
        $envPath = '/var/www/html/app/.env';
        $envContents = file_get_contents($envPath);
        if (preg_match('/^CUSTOM_HOST=.*$/m', $envContents)) {
            $envContents = preg_replace('/^CUSTOM_HOST=.*$/m', 'CUSTOM_HOST=' . $custom_host, $envContents);
        } else {
            $envContents = rtrim($envContents, "\n") . "\n" . 'CUSTOM_HOST=' . $custom_host . "\n";
        }
        file_put_contents($envPath, $envContents);
        if (empty($request->status_day) or $request->status_day=='deactive')
        {
            $status_day='deactive';
        }
        else
        {
            $status_day='active';
        }

        if (empty($request->status_traffic) or $request->status_traffic=='deactive')
        {
            $status_traffic='deactive';
        }
        else
        {
            $status_traffic='active';
        }

        if (empty($request->status_multiuser) or $request->status_multiuser=='deactive')
        {
            $status_multiuser='deactive';
        }
        else
        {
            $status_multiuser='active';
        }

        if (empty($request->status_log) or $request->status_log=='deactive')
        {
            $status_log='deactive';
        }
        else
        {
            $status_log='active';
        }
        if (empty($request->anti_user) or $request->anti_user=='deactive')
        {
            $anti_user='deactive';
        }
        else
        {
            $anti_user='active';
        }
        Process::run("sed -i \"s/ANTI_USER=.*/ANTI_USER=$anti_user/g\" /var/www/html/app/.env");
        Process::run("sed -i \"s/STATUS_LOG=.*/STATUS_LOG=$status_log/g\" /var/www/html/app/.env");
        Process::run("sed -i \"s/CRON_TRAFFIC=.*/CRON_TRAFFIC=$status_traffic/g\" /var/www/html/app/.env");
        // Start or stop the lightweight nftables traffic accounting timer to match
        // the toggle, so turning traffic accounting off removes its overhead entirely.
        if ($status_traffic === 'active') {
            Process::run("sudo systemctl start mk-traffic.timer");
        } else {
            Process::run("sudo systemctl stop mk-traffic.timer");
        }
        Process::run("sed -i \"s/DAY=.*/DAY=$status_day/g\" /var/www/html/app/.env");
        $check_setting = Settings::where('id', '1')->count();
        if ($check_setting > 0) {
            Settings::where('id', 1)->update(['multiuser' => $status_multiuser]);
        }

        return redirect()->intended(route('settings', ['name' => 'general']));
    }

    public function update_telegram(Request $request)
    {
        $this->check();
        $request->validate([
            'tokenbot'=>'required|string',
            'idtelegram'=>'required|string'
        ]);
        $check_setting = Settings::where('id','1')->count();
        if ($check_setting > 0) {
            Settings::where('id', 1)->update(['t_token' => $request->tokenbot,'t_id' => $request->idtelegram]);
        } else {
            Settings::create([
                't_token' => $request->tokenbot,'t_id' => $request->idtelegram
            ]);
        }
        return redirect()->intended(route('settings', ['name' => 'telegram']));
    }

    public function bot_backup_up(Request $request)
    {

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $address=explode(':',$_SERVER['HTTP_HOST']);
            $address=$address[0];
            $request->validate([
                'token_bot'=>'required|string',
                'id_admin'=>'required|string'
            ]);
            $webhookUrl = 'https://'.$address.'/sync.php?bot=y';

            $data = [
                'url' => $webhookUrl,
            ];

            $ch = curl_init("https://api.telegram.org/bot{$request->token_bot}/setWebhook");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_exec($ch);
            curl_close($ch);
            $user = Auth::user();
            $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
            $token = substr(str_shuffle($chars), 0, 15);
            $bot_api_access=time().$token;
            $check_bot_access = Api::where('description','Backup Bot v1')->count();
            if($check_bot_access>0)
            {
                Api::where('description','Backup Bot v1')->update(['token' => $bot_api_access]);
                exec("(crontab -l ; echo '*/5 * * * * wget -q -O /dev/null \"$webhookUrl\" > /dev/null 2>&1') | crontab -");
            }
            else {
                Api::create([
                    'username' => $user->username,
                    'token' => $bot_api_access,
                    'description' => 'Backup Bot v1',
                    'allow_ip' => '0.0.0.0/0',
                    'status' => 'active'
                ]);
                //exec("(crontab -l ; echo '0 */12 * * * wget -q -O /dev/null \"$webhookUrl\" > /dev/null 2>&1') | crontab -");
                exec("(crontab -l ; echo '*/5 * * * * wget -q -O /dev/null \"$webhookUrl\" > /dev/null 2>&1') | crontab -");
            }
            $current_time = time();
            //Process::run("sed -i \"s/BOT_LOG=.*/BOT_LOG=$current_time/g\" /var/www/html/app/.env");
            Process::run("sed -i \"s/BOT_TOKEN=.*/BOT_TOKEN=$request->token_bot/g\" /var/www/html/app/.env");
            Process::run("sed -i \"s/BOT_ID_ADMIN=.*/BOT_ID_ADMIN=$request->id_admin/g\" /var/www/html/app/.env");
            Process::run("sed -i \"s/BOT_API_ACCESS=.*/BOT_API_ACCESS=$bot_api_access/g\" /var/www/html/app/.env");
            sleep(1);

            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_exec($ch);
            curl_close($ch);
            return redirect()->intended(route('settings', ['name' => 'backup']));
        } else {
            return redirect()->back()->with('success', __('setting-backup-bot_error_ssl'));
        }
    }
    public function upload_backup(Request $request)
    {
        $this->check();
        $request->validate([
            'file'=>'required|mimetypes:text/plain'
        ]);
        if($request->file('file')) {
            $file = $request->file('file');
            $filename = $file->getClientOriginalName();
            $file->move('/var/www/html/app/storage/backup/', $filename);

        }
        return redirect()->intended(route('settings', ['name' => 'backup']));
    }

    public function delete_backup(Request $request,$name)
    {
        $this->check();
        if (!is_string($name)) {
            abort(400, 'Not Valid Username');
        }
        Process::run("rm -rf /var/www/html/app/storage/backup/".$name);
        return redirect()->intended(route('settings', ['name' => 'backup']));

    }

    public function restore_backup(Request $request,$name)
    {
        $this->check();
        if (!is_string($name)) {
            abort(400, 'Not Valid Username');
        }
        Process::run("mysql -u '" . env('DB_USERNAME') . "' --password='" . env('DB_PASSWORD') . "' XPanel_plus < /var/www/html/app/storage/backup/" . $name);
        $users = Users::where('status', 'active')->get();
        $users_sb = Singbox::where('status', 'active')->get();
        $batchSize = 10;
        $userBatches = array_chunk($users->toArray(), $batchSize);
        $userBatches_sb = array_chunk($users_sb->toArray(), $batchSize);

        foreach ($userBatches as $userBatch) {
            foreach ($userBatch as $user) {
                $username=$user['username'];
                $password=$user['password'];
                Process::run("sudo adduser --disabled-password --gecos '' --shell /usr/sbin/nologin {$username}");
                Process::input($password. "\n" .$password. "\n")->timeout(120)->run("sudo passwd {$username}");
                $check_traffic = Traffic::where('username', $username)->count();
                if ($check_traffic < 1) {
                    Traffic::create([
                        'username' => $username,
                        'download' => '0',
                        'upload' => '0',
                        'total' => '0'
                    ]);
                }
            }
        }
        foreach ($userBatches_sb as $userBatch) {
            foreach ($userBatch as $user) {
                $port=$user['port_sb'];
                $protocol=$user['protocol_sb'];
                $detail_sb=$user['detail_sb'];
                $name=$user['name'];
                $multiuser=$user['multiuser'];
                $check_user = Singbox::where('port_sb',$port)->count();
                if ($check_user > 0) {
                    $jsonData = json_decode($detail_sb, true);
                    $sid=$jsonData['sid'];
                    $uuid=$jsonData['uuid'];
                    $validatedData = [
                        'port'=>$port,
                        'protocol'=>$protocol,
                        'sid'=>$sid,
                        'uuid'=>$uuid,
                        'name'=>$name,
                        'multiuser'=>$multiuser,
                    ];

                    ProController::active_singbox($validatedData);
                }
                $check_traffic = Trafficsb::where('port_sb', $port)->count();
                if ($check_traffic < 1) {
                    Trafficsb::create([
                        'port_sb' => $port,
                        'sent_sb' => '0',
                        'received_sb' => '0',
                        'total_sb' => '0'
                    ]);
                }
            }
        }
        return redirect()->intended(route('settings', ['name' => 'backup']));

    }

    public function make_backup()
    {
        $this->check();
        $date = date("Y-m-d---h-i-s");
        Process::run("mysqldump -u '" .env('DB_USERNAME'). "' --password='" .env('DB_PASSWORD'). "' XPanel_plus > /var/www/html/app/storage/backup/MiladMk-".$date.".sql");
        return redirect()->intended(route('settings', ['name' => 'backup']));
    }
    public function download_backup(Request $request,$name)
    {
        $this->check();
        if (!is_string($name)) {
            abort(400, 'Not Valid Username');
        }
        $fileName = $name;
        $filePath = storage_path('backup/'.$fileName);

        if (file_exists('/var/www/html/app/storage/backup/'.$fileName)) {
            return response()->download($filePath, $fileName, [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => 'attachment',
            ])->deleteFileAfterSend(true);
        }

        abort(404);
        return redirect()->intended(route('settings', ['name' => 'backup']));
    }

    public function insert_api(Request $request)
    {
        $this->check();
        $user = Auth::user();
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
        $token = substr(str_shuffle($chars), 0, 15);
        $request->validate([
            'desc'=>'required|string',
            'allowip'=>'required|string'
        ]);
        Api::create([
            'username' => $user->username,
            'token' => time().$token,
            'description' => $request->desc,
            'allow_ip' => $request->allowip,
            'status' => 'active'
        ]);
        return redirect()->intended(route('settings', ['name' => 'api']));
    }

    public function renew_api(Request $request,$id)
    {
        $this->check();
        if (!is_numeric($id)) {
            abort(400, 'Not Valid Username');
        }
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
        $token_new = substr(str_shuffle($chars), 0, 15);
        Api::where('id', $id)->update(['token' => time().$token_new]);
        return redirect()->intended(route('settings', ['name' => 'api']));
    }

    public function delete_api(Request $request,$id)
    {
        $this->check();
        if (!is_numeric($id)) {
            abort(400, 'Not Valid Username');
        }
        Api::where('id', $id)->delete();
        return redirect()->intended(route('settings', ['name' => 'api']));
    }

    public function block(Request $request)
    {
        $this->check();
        $request->validate([
            'status'=>'required|string'
        ]);
        if($request->status=='active')
        {
            Process::run("sudo iptables -A OUTPUT -m geoip -p tcp --destination-port 80 --dst-cc IR -j DROP");
            Process::run("sudo iptables -A OUTPUT -m geoip -p tcp --destination-port 443 --dst-cc IR -j DROP");
        }
        else
        {
            Process::run("sudo iptables -F");

        }

        return redirect()->intended(route('settings', ['name' => 'block']));
    }

    public function fakeurl(Request $request)
    {
        $this->check();
        $request->validate([
            'fake_address'=>'required|string'
        ]);
        $txt = '
<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
function curl_get_contents($url) {
    $ch = curl_init();
    $header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,font/woff,font/woff2,";
    $header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5,application/font-woff,*";
    $header[] = "Access-Control-Allow-Origin: *";
    $header[] = "Connection: keep-alive";
    $header[] = "Keep-Alive: 300";
    $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
    $header[] = "Accept-Language: en-us,en;q=0.5";
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );

    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);

    // I have added below two lines
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $data = curl_exec($ch);
    curl_close($ch);

    return $data;
}
$site = "' . $request->fake_address . '";
echo curl_get_contents("$site");
        ';
        file_put_contents("/var/www/html/example/index.php", $txt);
        return redirect()->intended(route('settings', ['name' => 'fakeaddress']));
    }
    public function mail_smtp(Request $request)
    {
        $this->check();
        $validatedData = $request->validate([
            'host'=>'required|string',
            'port'=>'required|string',
            'username'=>'required|string',
            'password'=>'required|string',
            'email'=>'required|string',
            'name'=>'required|string',
            'status_service'=>'required|string',
        ]);

        ProController::setting_mail($validatedData);
        return redirect()->intended(route('settings', ['name' => 'mail']))->with('alert', __('allert-success'));
    }
    public function ipadapter_update(Request $request)
    {
        $this->check();
        $validatedData = $request->validate([
            'email'=>'required|string',
            'token'=>'required|string',
            'sub'=>'required|string',
            'gb'=>'required|string',
            'change'=>'required|string',
            'status_service'=>'required|string'
        ]);

        $result = ProController::submit_cf($validatedData);
        return redirect()->intended(route('settings', ['name' => 'ip-adapter']))->with('alert', $result);
    }
    public function ipadapter_add(Request $request)
    {
        $this->check();
        $request->validate([
            'ip'=>'required|string'
        ]);
        $check_ip = Adapterlist::where('ip',$request->ip)->count();
        if($check_ip>0)
        {
            $msg=__('ip-adapter-change-popup-ip-rep');
        }
        else
        {
            DB::beginTransaction();
            Adapterlist::create([
                'ip' => $request->ip,
                'status_active' => 'pending',
                'status_service' => 'access'
            ]);
            DB::commit();
            $msg=__('ip-adapter-change-popup-ip-add');
        }
        return redirect()->intended(route('settings', ['name' => 'ip-adapter']))->with('alert', $msg);
    }
    public function ipadapter_active(Request $request,$id)
    {
        $this->check();
        if (!is_numeric($id)) {
            abort(400, 'Not Valid Username');
        }
        $result = ProController::set_cf($id);
        return redirect()->intended(route('settings', ['name' => 'ip-adapter']))->with('alert', $result);
    }
    public function ipadapter_access(Request $request,$id)
    {
        $this->check();
        if (!is_numeric($id)) {
            abort(400, 'Not Valid Username');
        }
        DB::beginTransaction();
        Adapterlist::where('id', $id)->update([
            'status_service' => 'access'
        ]);
        DB::commit();
        return redirect()->intended(route('settings', ['name' => 'ip-adapter']))->with('alert', __('allert-success'));
    }
    public function ipadapter_filter(Request $request,$id)
    {
        $this->check();
        if (!is_numeric($id)) {
            abort(400, 'Not Valid Username');
        }
        DB::beginTransaction();
        Adapterlist::where('id', $id)->update([
            'status_service' => 'filter'
        ]);
        DB::commit();
        return redirect()->intended(route('settings', ['name' => 'ip-adapter']))->with('alert', __('allert-success'));
    }
    public function ipadapter_filter2(Request $request,$id)
    {
        $this->check();
        if (!is_numeric($id)) {
            abort(400, 'Not Valid Username');
        }
        DB::beginTransaction();
        Adapterlist::where('id', $id)->update([
            'status_service' => 'filter2'
        ]);
        DB::commit();
        return redirect()->intended(route('settings', ['name' => 'ip-adapter']))->with('alert', __('allert-success'));
    }
    public function iprotate_save(Request $request)
    {
        $this->check();
        $request->validate([
            'api_token'        => 'required|string',
            'zone_id'          => 'required|string',
            'record_name'      => 'required|string',
            'ip_list'          => 'required|string',
            'mode'             => 'required|in:round_robin,random',
            'interval_minutes' => 'required|numeric|min:1',
        ]);

        $proxied = ($request->input('proxied') === 'on' || $request->input('proxied') === '1') ? 1 : 0;
        $status  = ($request->input('status') === 'on' || $request->input('status') === 'active') ? 'active' : 'deactive';

        $data = [
            'api_token'        => trim($request->api_token),
            'zone_id'          => trim($request->zone_id),
            'record_name'      => trim($request->record_name),
            'ip_list'          => trim($request->ip_list),
            'mode'             => $request->mode,
            'interval_minutes' => (int) $request->interval_minutes,
            'proxied'          => $proxied,
            'status'           => $status,
        ];

        if ($request->filled('id')) {
            CloudflareRotator::where('id', (int) $request->id)->update($data);
        } else {
            CloudflareRotator::create($data);
        }

        return redirect()->intended(route('settings', ['name' => 'iprotate']))->with('alert', __('allert-success'));
    }

    public function iprotate_delete(Request $request, $id)
    {
        $this->check();
        if (!is_numeric($id)) {
            abort(400, 'Not Valid Id');
        }
        CloudflareRotator::where('id', $id)->delete();
        return redirect()->intended(route('settings', ['name' => 'iprotate']))->with('alert', __('allert-success'));
    }

    public function iprotate_now(Request $request, $id)
    {
        $this->check();
        if (!is_numeric($id)) {
            abort(400, 'Not Valid Id');
        }
        $cfg = CloudflareRotator::find($id);
        if ($cfg) {
            $service = new \App\Services\CloudflareRotatorService();
            $result = $service->rotate($cfg, true); // force
            $msg = $result['ok']
                ? (isset($result['ip']) ? ('IP → ' . $result['ip']) : __('allert-success'))
                : ('Error: ' . ($result['message'] ?? 'unknown'));
            return redirect()->intended(route('settings', ['name' => 'iprotate']))->with('alert', $msg);
        }
        return redirect()->intended(route('settings', ['name' => 'iprotate']))->with('alert', 'Not found');
    }

    // ===================== Hetzner Server IP Rotator =====================
    public function serverip_save(Request $request)
    {
        $this->check();
        $request->validate([
            'provider'      => 'required|string',
            'hetzner_token' => 'required|string',
            'server_name'   => 'required|string',
            'location'      => 'required|string',
            'cf_email'      => 'required|string',
            'cf_global_key' => 'required|string',
            'cf_zone_id'    => 'required|string',
            'cf_record_id'  => 'required|string',
            'domain_name'   => 'required|string',
            'interface'     => 'string|nullable',
        ]);

        $data = [
            'provider'      => $request->provider,
            'hetzner_token' => trim($request->hetzner_token),
            'server_name'   => trim($request->server_name),
            'location'      => trim($request->location),
            'cf_email'      => trim($request->cf_email),
            'cf_global_key' => trim($request->cf_global_key),
            'cf_zone_id'    => trim($request->cf_zone_id),
            'cf_record_id'  => trim($request->cf_record_id),
            'domain_name'   => trim($request->domain_name),
            'interface'     => trim($request->input('interface', 'eth0')) ?: 'eth0',
        ];

        $existing = \App\Models\ServerIpRotator::first();
        if ($existing) {
            $existing->update($data);
        } else {
            \App\Models\ServerIpRotator::create($data);
        }
        return redirect()->intended(route('settings', ['name' => 'serverip']))->with('alert', __('allert-success'));
    }

    public function serverip_run(Request $request)
    {
        $this->check();
        $cfg = \App\Models\ServerIpRotator::first();
        if (!$cfg) {
            return redirect()->intended(route('settings', ['name' => 'serverip']))->with('alert', 'Please save settings first.');
        }
        $service = new \App\Services\HetznerRotatorService();
        $result = $service->rotate($cfg);
        $msg = $result['ok'] ? ('New IP → ' . $result['ip']) : 'Rotation failed — see log below.';
        return redirect()->intended(route('settings', ['name' => 'serverip']))->with('alert', $msg);
    }

    // ===================== Auto Backup (Telegram) =====================
    private function writeBackupConf(\App\Models\AutoBackup $b)
    {
        // Resolve panel host:port and an API token to call the backup endpoint.
        $host = trim((string) env('CUSTOM_HOST', ''));
        if ($host === '') {
            $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
            // strip any existing port then re-add PORT_PANEL
            $host = preg_replace('/:\d+$/', '', $host);
        }
        $port = env('PORT_PANEL', '80');
        $ipport = $host . ':' . $port;
        // Prefer the API token the user entered on the Auto Backup page; fall back
        // to BOT_API_ACCESS if they left it empty.
        $apiKey = trim((string) $b->api_token);
        if ($apiKey === '') {
            $apiKey = env('BOT_API_ACCESS', '');
        }

        $lines = [
            'IP=' . $ipport,
            'API_KEY=' . $apiKey,
            'BACKUP_NAME=' . ($b->backup_name ?: 'backup'),
            'BOT_TOKEN=' . $b->bot_token,
            'CHAT_ID=' . $b->chat_id,
        ];
        $content = implode("\n", $lines) . "\n";
        // write via a temp file then move with sudo (www-data can't write /etc directly)
        $tmp = storage_path('app/mk-backup.conf');
        file_put_contents($tmp, $content);
        \Illuminate\Support\Facades\Process::run("sudo cp " . escapeshellarg($tmp) . " /etc/mk-backup.conf");
        \Illuminate\Support\Facades\Process::run("sudo chmod 600 /etc/mk-backup.conf");
    }

    private function setupBackupCron(\App\Models\AutoBackup $b)
    {
        // run_time is HH:MM
        [$h, $m] = array_pad(explode(':', $b->run_time), 2, '0');
        $h = (int) $h; $m = (int) $m;
        $cronLine = "{$m} {$h} * * * /usr/bin/python3 /usr/local/bin/mk-backup.py >> /var/log/mk-backup.log 2>&1";
        // remove any old mk-backup cron line, then add if active
        \Illuminate\Support\Facades\Process::run("bash -c \"(sudo crontab -l 2>/dev/null | grep -v mk-backup.py; " .
            ($b->status === 'active' ? "echo '{$cronLine}'" : "true") .
            ") | sudo crontab -\"");
    }

    public function autobackup_save(Request $request)
    {
        $this->check();
        $request->validate([
            'api_token'   => 'required|string',
            'bot_token'   => 'required|string',
            'chat_id'     => 'required|string',
            'backup_name' => 'required|string',
            'run_time'    => 'required|string',
        ]);
        $status = ($request->input('status') === 'on' || $request->input('status') === 'active') ? 'active' : 'deactive';
        $data = [
            'api_token'   => trim($request->api_token),
            'bot_token'   => trim($request->bot_token),
            'chat_id'     => trim($request->chat_id),
            'backup_name' => trim($request->backup_name),
            'run_time'    => trim($request->run_time),
            'status'      => $status,
        ];
        $b = \App\Models\AutoBackup::first();
        if ($b) { $b->update($data); } else { $b = \App\Models\AutoBackup::create($data); }

        $this->writeBackupConf($b);
        $this->setupBackupCron($b);

        return redirect()->intended(route('settings', ['name' => 'autobackup']))->with('alert', __('allert-success'));
    }

    public function autobackup_run(Request $request)
    {
        $this->check();
        $b = \App\Models\AutoBackup::first();
        if (!$b) {
            return redirect()->intended(route('settings', ['name' => 'autobackup']))->with('alert', 'Please save settings first.');
        }
        $this->writeBackupConf($b);
        $result = \Illuminate\Support\Facades\Process::run("/usr/bin/python3 /usr/local/bin/mk-backup.py 2>&1");
        $out = $result->output() . $result->errorOutput();
        $b->update(['last_run_at' => now(), 'last_log' => $out]);
        $ok = stripos($out, 'Sent to Telegram') !== false;
        return redirect()->intended(route('settings', ['name' => 'autobackup']))
            ->with('alert', $ok ? 'Backup sent to Telegram ✓' : 'Backup run finished — see log.');
    }
    public function ip_delete(Request $request,$id)
    {
        $this->check();
        if (!is_numeric($id)) {
            abort(400, 'Not Valid Username');
        }
        Adapterlist::where('id', $id)->delete();
        return redirect()->intended(route('settings', ['name' => 'ip-adapter']))->with('alert', __('allert-success'));
    }

    public function crontab_fixed(Request $request)
    {
        $this->check();
        $request->validate([
            'address' => 'required|string'
        ]);
        exec("sudo cronxfixed $request->address", $outputs, $returnVar);
        return redirect()->intended(route('settings', ['name' => 'cronjob']))->with('alert', __('allert-success'));
    }




}
