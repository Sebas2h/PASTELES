<?php
namespace App\Services;

final class EmailChangeService
{
    private const KEY='_email_change_verification';
    private const CODE_TTL=600;
    private const VERIFIED_TTL=900;
    private const RESEND_WAIT=60;
    private const MAX_ATTEMPTS=5;

    public function __construct(private ?EmailService $mailer=null)
    {
        $this->mailer??=new EmailService();
    }

    public function sendCurrentCode(int $userId,string $email):void
    {
        $state=$this->state();
        if(($state['user_id']??null)===$userId && isset($state['current_sent_at']) && time()-(int)$state['current_sent_at']<self::RESEND_WAIT){
            throw new \RuntimeException('Espera un minuto antes de solicitar otro código.');
        }
        $code=$this->code();
        $this->mailer->sendVerificationCode($email,$code,'cambio de correo electrónico');
        $_SESSION[self::KEY]=[
            'user_id'=>$userId,
            'current_email'=>$email,
            'current_hash'=>password_hash($code,PASSWORD_DEFAULT),
            'current_expires'=>time()+self::CODE_TTL,
            'current_attempts'=>0,
            'current_sent_at'=>time(),
            'current_verified'=>false,
        ];
    }

    public function verifyCurrent(int $userId,string $code):bool
    {
        $state=$this->state();
        if(($state['user_id']??null)!==$userId) return false;
        if(($state['current_expires']??0)<time()) return false;
        if(($state['current_attempts']??0)>=self::MAX_ATTEMPTS) return false;
        $_SESSION[self::KEY]['current_attempts']=(int)($state['current_attempts']??0)+1;
        if(!password_verify(trim($code),(string)($state['current_hash']??''))) return false;
        $_SESSION[self::KEY]['current_verified']=true;
        $_SESSION[self::KEY]['current_verified_until']=time()+self::VERIFIED_TTL;
        unset($_SESSION[self::KEY]['current_hash']);
        return true;
    }

    public function currentVerified(int $userId):bool
    {
        $s=$this->state();
        return ($s['user_id']??null)===$userId && !empty($s['current_verified']) && (int)($s['current_verified_until']??0)>=time();
    }

    public function sendNewCode(int $userId,string $newEmail):void
    {
        if(!$this->currentVerified($userId)) throw new \RuntimeException('Primero debes verificar tu correo actual.');
        $state=$this->state();
        if(isset($state['new_sent_at']) && time()-(int)$state['new_sent_at']<self::RESEND_WAIT && ($state['new_email']??'')===$newEmail){
            throw new \RuntimeException('Espera un minuto antes de solicitar otro código.');
        }
        $code=$this->code();
        $this->mailer->sendVerificationCode($newEmail,$code,'verificación del nuevo correo electrónico');
        $_SESSION[self::KEY]['new_email']=$newEmail;
        $_SESSION[self::KEY]['new_hash']=password_hash($code,PASSWORD_DEFAULT);
        $_SESSION[self::KEY]['new_expires']=time()+self::CODE_TTL;
        $_SESSION[self::KEY]['new_attempts']=0;
        $_SESSION[self::KEY]['new_sent_at']=time();
    }

    public function verifyNew(int $userId,string $code):?string
    {
        $state=$this->state();
        if(!$this->currentVerified($userId)) return null;
        if(($state['new_expires']??0)<time()) return null;
        if(($state['new_attempts']??0)>=self::MAX_ATTEMPTS) return null;
        $_SESSION[self::KEY]['new_attempts']=(int)($state['new_attempts']??0)+1;
        if(!password_verify(trim($code),(string)($state['new_hash']??''))) return null;
        return isset($state['new_email'])?(string)$state['new_email']:null;
    }

    public function state():array
    {
        return is_array($_SESSION[self::KEY]??null)?$_SESSION[self::KEY]:[];
    }

    public function clear():void
    {
        unset($_SESSION[self::KEY]);
    }

    public function maskedEmail(string $email):string
    {
        [$local,$domain]=array_pad(explode('@',$email,2),2,'');
        $len=strlen($local);$visible=substr($local,0,min(2,$len));
        return $visible.str_repeat('•',max(2,$len-2)).'@'.$domain;
    }

    private function code():string
    {
        return (string)random_int(100000,999999);
    }
}
