<?php
namespace App\Services;

use App\Helpers\Env;

final class EmailService
{
    public function sendVerificationCode(string $to, string $code, string $context='cambio de correo'): void
    {
        $app=(string)Env::get('APP_NAME','Pastelería Dulce Naranja');
        $subject='Código de verificación - '.$app;
        $safeApp=htmlspecialchars($app,ENT_QUOTES,'UTF-8');
        $safeContext=htmlspecialchars($context,ENT_QUOTES,'UTF-8');
        $html='<!doctype html><html><body style="font-family:Arial,sans-serif;background:#fff8f2;color:#4b2e23;padding:24px">'
            .'<div style="max-width:560px;margin:auto;background:#fff;border:1px solid #f0dfd4;border-radius:18px;padding:28px">'
            .'<h2 style="margin-top:0">'.$safeApp.'</h2>'
            .'<p>Usa este código para continuar con el '.$safeContext.':</p>'
            .'<div style="font-size:32px;font-weight:800;letter-spacing:8px;background:#fff0e4;border-radius:14px;padding:18px;text-align:center">'.htmlspecialchars($code,ENT_QUOTES,'UTF-8').'</div>'
            .'<p style="color:#8d7569">El código expira en 10 minutos. Si no solicitaste este cambio, ignora este mensaje.</p>'
            .'</div></body></html>';

        $transport=strtolower((string)Env::get('MAIL_TRANSPORT','smtp'));
        if($transport==='mail'){
            $from=(string)Env::get('MAIL_FROM_ADDRESS',Env::get('SMTP_USERNAME',''));
            if($from==='') throw new \RuntimeException('MAIL_FROM_ADDRESS no está configurado.');
            $headers=[
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: '.($this->encodeHeader((string)Env::get('MAIL_FROM_NAME',$app))).' <'.$from.'>'
            ];
            if(!@mail($to,$subject,$html,implode("\r\n",$headers))) throw new \RuntimeException('No fue posible enviar el correo de verificación.');
            return;
        }

        $this->sendSmtp($to,$subject,$html);
    }

    private function sendSmtp(string $to,string $subject,string $html):void
    {
        $host=(string)Env::get('SMTP_HOST','');
        $port=(int)Env::get('SMTP_PORT','587');
        $username=(string)Env::get('SMTP_USERNAME','');
        $password=(string)Env::get('SMTP_PASSWORD','');
        $encryption=strtolower((string)Env::get('SMTP_ENCRYPTION','tls'));
        $from=(string)Env::get('MAIL_FROM_ADDRESS',$username);
        $fromName=(string)Env::get('MAIL_FROM_NAME',Env::get('APP_NAME','Pastelería Dulce Naranja'));
        if($host===''||$from==='') throw new \RuntimeException('Configura SMTP_HOST y MAIL_FROM_ADDRESS en el archivo .env.');

        $remote=($encryption==='ssl'?'ssl://':'').$host.':'.$port;
        $errno=0;$errstr='';
        $fp=@stream_socket_client($remote,$errno,$errstr,15,STREAM_CLIENT_CONNECT);
        if(!$fp) throw new \RuntimeException('No se pudo conectar al servidor SMTP.');
        stream_set_timeout($fp,15);
        try{
            $this->expect($fp,[220]);
            $this->command($fp,'EHLO localhost',[250]);
            if($encryption==='tls'){
                $this->command($fp,'STARTTLS',[220]);
                if(!@stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new \RuntimeException('No se pudo activar TLS con el servidor SMTP.');
                $this->command($fp,'EHLO localhost',[250]);
            }
            if($username!==''){
                $this->command($fp,'AUTH LOGIN',[334]);
                $this->command($fp,base64_encode($username),[334]);
                $this->command($fp,base64_encode($password),[235]);
            }
            $this->command($fp,'MAIL FROM:<'.$from.'>',[250]);
            $this->command($fp,'RCPT TO:<'.$to.'>',[250,251]);
            $this->command($fp,'DATA',[354]);
            $headers=[
                'From: '.$this->encodeHeader($fromName).' <'.$from.'>',
                'To: <'.$to.'>',
                'Subject: '.$this->encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                'Date: '.date(DATE_RFC2822),
                'Message-ID: <'.bin2hex(random_bytes(12)).'@'.preg_replace('/[^a-z0-9.-]/i','',$host).'>'
            ];
            $body=preg_replace('/(?m)^\./','..',$html)??$html;
            fwrite($fp,implode("\r\n",$headers)."\r\n\r\n".$body."\r\n.\r\n");
            $this->expect($fp,[250]);
            $this->command($fp,'QUIT',[221]);
        } finally {
            fclose($fp);
        }
    }

    private function command($fp,string $command,array $codes):void
    {
        fwrite($fp,$command."\r\n");
        $this->expect($fp,$codes);
    }

    private function expect($fp,array $codes):void
    {
        $response='';$code=0;
        while(($line=fgets($fp,515))!==false){
            $response.=$line;
            if(preg_match('/^(\d{3})([ -])/',$line,$m)){
                $code=(int)$m[1];
                if($m[2]===' ') break;
            }
        }
        if(!in_array($code,$codes,true)) throw new \RuntimeException('El servidor de correo rechazó la solicitud (SMTP '.$code.').');
    }

    private function encodeHeader(string $value):string
    {
        return '=?UTF-8?B?'.base64_encode($value).'?=';
    }
}
