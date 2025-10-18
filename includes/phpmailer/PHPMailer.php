<?php
/**
 * PHPMailer - PHP email creation and transport class.
 * Simplified version for direct inclusion without Composer
 * This is a minimal implementation - for full version visit: https://github.com/PHPMailer/PHPMailer
 */

namespace PHPMailer\PHPMailer;

class PHPMailer
{
    const CHARSET_UTF8 = 'utf-8';
    const ENCODING_8BIT = '8bit';
    const ENCRYPTION_STARTTLS = 'tls';
    const ENCRYPTION_SMTPS = 'ssl';
    
    public $CharSet = self::CHARSET_UTF8;
    public $Encoding = self::ENCODING_8BIT;
    public $From = '';
    public $FromName = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $Host = '';
    public $Port = 587;
    public $SMTPAuth = false;
    public $Username = '';
    public $Password = '';
    public $SMTPSecure = '';
    public $Priority = 3;
    public $SMTPDebug = 0;
    
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $ReplyTo = [];
    protected $attachment = [];
    protected $CustomHeader = [];
    protected $isHTML = true;
    protected $smtp = null;
    protected $lastMessageID = '';
    
    public function __construct($exceptions = false)
    {
        // Constructor
    }
    
    public function isSMTP()
    {
        // Set mailer to use SMTP
        return true;
    }
    
    public function isHTML($isHtml = true)
    {
        $this->isHTML = $isHtml;
    }
    
    public function setFrom($address, $name = '')
    {
        $this->From = $address;
        $this->FromName = $name;
        return true;
    }
    
    public function addAddress($address, $name = '')
    {
        $this->to[] = ['address' => $address, 'name' => $name];
        return true;
    }
    
    public function addCC($address, $name = '')
    {
        $this->cc[] = ['address' => $address, 'name' => $name];
        return true;
    }
    
    public function addBCC($address, $name = '')
    {
        $this->bcc[] = ['address' => $address, 'name' => $name];
        return true;
    }
    
    public function addReplyTo($address, $name = '')
    {
        $this->ReplyTo[] = ['address' => $address, 'name' => $name];
        return true;
    }
    
    public function addAttachment($path, $name = '')
    {
        $this->attachment[] = ['path' => $path, 'name' => $name];
        return true;
    }
    
    public function addCustomHeader($name, $value = null)
    {
        if ($value === null) {
            $this->CustomHeader[] = $name;
        } else {
            $this->CustomHeader[] = $name . ': ' . $value;
        }
    }
    
    public function clearAddresses()
    {
        $this->to = [];
    }
    
    public function clearCCs()
    {
        $this->cc = [];
    }
    
    public function clearBCCs()
    {
        $this->bcc = [];
    }
    
    public function clearReplyTos()
    {
        $this->ReplyTo = [];
    }
    
    public function clearAttachments()
    {
        $this->attachment = [];
    }
    
    public function send()
    {
        if (empty($this->to)) {
            throw new Exception('No recipients specified');
        }
        
        // Connect to SMTP server
        $smtp = fsockopen($this->SMTPSecure === 'ssl' ? 'ssl://' . $this->Host : $this->Host, $this->Port, $errno, $errstr, 30);
        
        if (!$smtp) {
            throw new Exception("SMTP connection failed: $errstr ($errno)");
        }
        
        stream_set_timeout($smtp, 30);
        
        // Read greeting
        $this->getResponse($smtp);
        
        // Send EHLO
        fputs($smtp, "EHLO " . gethostname() . "\r\n");
        $this->getResponse($smtp);
        
        // Start TLS if needed
        if ($this->SMTPSecure === 'tls') {
            fputs($smtp, "STARTTLS\r\n");
            $this->getResponse($smtp);
            
            if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception('STARTTLS failed');
            }
            
            // Send EHLO again after STARTTLS
            fputs($smtp, "EHLO " . gethostname() . "\r\n");
            $this->getResponse($smtp);
        }
        
        // Authenticate
        if ($this->SMTPAuth) {
            fputs($smtp, "AUTH LOGIN\r\n");
            $this->getResponse($smtp);
            
            fputs($smtp, base64_encode($this->Username) . "\r\n");
            $this->getResponse($smtp);
            
            fputs($smtp, base64_encode($this->Password) . "\r\n");
            $response = $this->getResponse($smtp);
            
            if (strpos($response, '235') === false) {
                throw new Exception('SMTP authentication failed');
            }
        }
        
        // Send MAIL FROM
        fputs($smtp, "MAIL FROM: <{$this->From}>\r\n");
        $this->getResponse($smtp);
        
        // Send RCPT TO for each recipient
        foreach ($this->to as $recipient) {
            fputs($smtp, "RCPT TO: <{$recipient['address']}>\r\n");
            $this->getResponse($smtp);
        }
        
        foreach ($this->cc as $recipient) {
            fputs($smtp, "RCPT TO: <{$recipient['address']}>\r\n");
            $this->getResponse($smtp);
        }
        
        foreach ($this->bcc as $recipient) {
            fputs($smtp, "RCPT TO: <{$recipient['address']}>\r\n");
            $this->getResponse($smtp);
        }
        
        // Send DATA
        fputs($smtp, "DATA\r\n");
        $this->getResponse($smtp);
        
        // Build message
        $message = $this->buildMessage();
        fputs($smtp, $message);
        fputs($smtp, "\r\n.\r\n");
        $response = $this->getResponse($smtp);
        
        // Extract message ID from response
        if (preg_match('/<([^>]+)>/', $response, $matches)) {
            $this->lastMessageID = $matches[1];
        }
        
        // Send QUIT
        fputs($smtp, "QUIT\r\n");
        $this->getResponse($smtp);
        
        fclose($smtp);
        
        return true;
    }
    
    protected function getResponse($smtp)
    {
        $response = '';
        while ($line = fgets($smtp, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $response;
    }
    
    protected function buildMessage()
    {
        $boundary = '----=_Part_' . md5(uniqid());
        $message = '';
        
        // Headers
        $message .= "From: " . $this->encodeHeader($this->FromName) . " <{$this->From}>\r\n";
        
        foreach ($this->to as $recipient) {
            $name = $recipient['name'] ? $this->encodeHeader($recipient['name']) . " " : '';
            $message .= "To: {$name}<{$recipient['address']}>\r\n";
        }
        
        foreach ($this->cc as $recipient) {
            $name = $recipient['name'] ? $this->encodeHeader($recipient['name']) . " " : '';
            $message .= "Cc: {$name}<{$recipient['address']}>\r\n";
        }
        
        foreach ($this->ReplyTo as $recipient) {
            $name = $recipient['name'] ? $this->encodeHeader($recipient['name']) . " " : '';
            $message .= "Reply-To: {$name}<{$recipient['address']}>\r\n";
        }
        
        $message .= "Subject: " . $this->encodeHeader($this->Subject) . "\r\n";
        $message .= "Date: " . date('r') . "\r\n";
        $message .= "Message-ID: <" . md5(uniqid()) . "@" . gethostname() . ">\r\n";
        $message .= "X-Priority: {$this->Priority}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        
        // Custom headers
        foreach ($this->CustomHeader as $header) {
            $message .= $header . "\r\n";
        }
        
        if ($this->isHTML) {
            $message .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
            
            // Plain text part
            if (!empty($this->AltBody)) {
                $message .= "--{$boundary}\r\n";
                $message .= "Content-Type: text/plain; charset={$this->CharSet}\r\n";
                $message .= "Content-Transfer-Encoding: {$this->Encoding}\r\n\r\n";
                $message .= $this->AltBody . "\r\n\r\n";
            }
            
            // HTML part
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset={$this->CharSet}\r\n";
            $message .= "Content-Transfer-Encoding: {$this->Encoding}\r\n\r\n";
            $message .= $this->Body . "\r\n\r\n";
            
            $message .= "--{$boundary}--\r\n";
        } else {
            $message .= "Content-Type: text/plain; charset={$this->CharSet}\r\n";
            $message .= "Content-Transfer-Encoding: {$this->Encoding}\r\n\r\n";
            $message .= $this->Body . "\r\n";
        }
        
        return $message;
    }
    
    protected function encodeHeader($str)
    {
        if (empty($str)) {
            return '';
        }
        return '=?' . $this->CharSet . '?B?' . base64_encode($str) . '?=';
    }
    
    public function smtpConnect()
    {
        // Test connection
        return true;
    }
    
    public function smtpClose()
    {
        // Close connection
        return true;
    }
    
    public function getLastMessageID()
    {
        return $this->lastMessageID;
    }
}

class Exception extends \Exception
{
}

class SMTP
{
    const DEBUG_OFF = 0;
    const DEBUG_CLIENT = 1;
    const DEBUG_SERVER = 2;
    const DEBUG_CONNECTION = 3;
    const DEBUG_LOWLEVEL = 4;
}

