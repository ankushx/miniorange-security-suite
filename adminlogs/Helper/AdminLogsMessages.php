<?php

namespace MiniOrange\AdminLogs\Helper;

/**
 * This class lists down all of our messages to be shown to the admin or
 * in the frontend. This is a constant file listing down all of our
 * constants. Has a parse function to parse and replace any dynamic
 * values needed to be inputed in the string. Key is usually of the form
 * {{key}}
 */
class AdminLogsMessages
{
    //General Flow Messages
    const REQUIRED_FIELDS                  = 'Please fill in the required fields.';
    const ERROR_OCCURRED                 = 'An error occured while processing your request. Please try again.';
    const NOT_REG_ERROR                    = 'Please register and verify your account before trying to configure your settings. Go the Account
                                            Section to complete your registration registered.';

    //cURL Error
    const CURL_ERROR                     = 'ERROR: <a href="http://php.net/manual/en/curl.installation.php" target="_blank">PHP cURL extension</a>
                                            is not installed or disabled. Query submit failed.';

    //Query Form Error
    const REQUIRED_QUERY_FIELDS         = 'Please fill up Email and Query fields to submit your query.';
    const ERROR_QUERY                     = 'Your query could not be submitted. Please try again.';
    const QUERY_SENT                    = 'Thanks for getting in touch! We shall get back to you shortly.';

    const SETTINGS_SAVED                = 'Settings saved successfully.';


    /**
     * Parse the message and replace the dynamic values with the
     * necessary values. The dynamic values needs to be passed in
     * the key value pair. Key is usually of the form {{key}}.
     *
     * @param $message
     * @param $data
     */
    public static function parse($message, $data = [])
    {
        $message = constant("self::".$message);
        foreach ($data as $key => $value) {
            $message = str_replace("{{" . $key . "}}", $value, $message);
        }
        return $message;
    }
}
