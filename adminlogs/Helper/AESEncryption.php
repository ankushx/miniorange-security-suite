<?php

namespace MiniOrange\AdminLogs\Helper;

/**
 * AES Encryption Helper
 * Provides encryption and decryption methods for sensitive data
 */
class AESEncryption 
{
    /**
     * Encrypt data using AES encryption
     * 
     * @param string $string Data to encrypt
     * @param string $pass Encryption password/key
     * @return string Encrypted data (base64 encoded)
     */
    public static function encrypt_data($string, $pass)
    {
        $result = '';
        for($i = 0; $i < strlen($string); $i++) {
            $char = substr($string, $i, 1);
            if(strlen($pass)>1)
            {
                $keychar = substr($pass, ($i % strlen($pass))-1, 1);
                $char = chr(ord($char) + ord($keychar));
                $result .= $char;
            }
           
        }

        return base64_encode($result);
    }

    /**
     * Decrypt data using AES decryption
     * 
     * @param string $string Encrypted data (base64 encoded)
     * @param string $pass Decryption password/key
     * @return string Decrypted data
     */
    public static function decrypt_data($string, $pass)
    {
        $result = '';
         if(!is_null($string)){
            $string = base64_decode((string)$string);
        }
        else{
            $string ='';
        }

        for($i = 0; $i < strlen($string); $i++) {
            $char = substr($string, $i, 1);
            if(strlen($pass)>1)
            {
                $keychar = substr($pass, ($i % strlen($pass))-1, 1);
                $char = chr(ord($char) - ord($keychar));
                $result .= $char;
            }
        }

        return $result;
    }
}

