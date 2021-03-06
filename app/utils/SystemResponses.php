<?php

use Phalcon\Http\Response;
use Phalcon\Logger;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Mvc\Model\Query;
use Phalcon\Mvc\Model\Query\Builder as Builder;
use Phalcon\Mvc\Controller;

/**
 * All system communication with other systems 
 */
class SystemResponses extends Controller {

    /*
    get log file to use
    */

     private $EnvirofitClientSecret = "7OCCKa0qstZ0biil2H9vkAug4yZV9Cb9b4bV0kaG";
    private $EnvirofitClientID = 5;

    private function getLogFile($action = "") {

        /**
         * Read the configuration
         */
        $config = include APP_PATH . "/app/config/config.php";
       

        $logPathLocation = $config->logPath->location;
        switch ($action) {
            case 'success':
                return $logPathLocation . 'response_logs.log';
                break;
            case 'error':
                return $logPathLocation . 'error_logs.log';
                break;
            case 'metropol':
                return $logPathLocation . 'metropol_logs.log';
                break;
             case 'metropol_error':
                return $logPathLocation . 'metropol_error_logs.log';
                break;
            default:
                return $logPathLocation . 'apicalls_logs.log';
                break;
        }
    }

    public function metropolResponseLogs($requestType,$response){
        $logger = new FileAdapter($this->getLogFile('metropol'));
        $logger->log($requestType . ' ' . json_encode($response));
    }
    public function metropolResponseErrorLogs($requestType,$response){
        $logger = new FileAdapter($this->getLogFile('metropol_error'));
        $logger->log($requestType . ' ' . json_encode($response));
    }


    public function calculateTotalPages($total, $per_page) {
        $totalPages = (int) ($total / $per_page);
        if (($total % $per_page) > 0) {
            $totalPages = $totalPages + 1;
        }

        return $totalPages;
    }


    
    public function composePushLog($type, $description, $resolution) {//($data,$title,$body,$userID){
        $data = array();
        $data["origin"] = "Envirofit apis";
        $data["description"] = $description;
        $data["resolution"] = $resolution;
        $data["alertTime"] = date("d-m-Y H:i:s");
        $data["status"] = 0;
        $data["type"] = $type;
        $title = "Envirofit api notification";
        $body = $type . " notification";

        $userID = array();
        $id["userId"] = 111;
        array_push($userID, $id);
        $appName = "com.james.southwelservicemonitor";
        $this->sendAndroidPushNotification($data, $title, $body, $userID, $appName);

    }

    public function success($message, $data) {
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $response->setStatusCode(201, "SUCCESS");
        $success["success"] = $message;
        $success["data"] = $data;
        $success["code"] = 201;

        $response->setContent(json_encode($success));
        $logger = new FileAdapter($this->getLogFile('success'));
        $logger->log(date("Y-m-d H:i:s").'success '.$message);

        return $response;
    }

    public function successFromData($data) {

        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $response->setStatusCode(201, "SUCCESS");

        $response->setContent(json_encode($data));
        $logger = new FileAdapter($this->getLogFile('success'));
        $logger->log(date("Y-m-d H:i:s").' success from data '.$message);
       // $this->composePushLog("success", "from data" . $this->config->logPath->location, $data);

        return $response;
    }

    public function getSalesSuccess($data) {
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $response->setStatusCode(201, "SUCCESS");
        $success = array();
        $response->setContent(json_encode($data));
        $logger = new FileAdapter($this->getLogFile('success'));
        $logger->log(date("Y-m-d H:i:s").' get sales success');
        return $response;
    }

    /* formats page not found response messages */

    public function notFound($message, $data) {
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $error = array();
        $error["error"] = $message;
        $error["data"] = $data;
        $error["code"] = 404;
        $response->setStatusCode(404, "NOT FOUND");
        $response->setContent(json_encode($error));

        $logger = new FileAdapter($this->getLogFile('error'));
        $logger->log(date("Y-m-d H:i:s").' not faound error '.$message . ' '. $this->config->logPath->location);

       // $this->composePushLog("error", "NOT FOUND " . $message, " " . json_encode($data));
        return $response;
    }

    /* formats validation error response messages */

    public function unProcessable($message, $data) {
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $error = array();
        $error["error"] = $message;
        $error["data"] = $data;
        $response->setStatusCode(422, "UNPROCESSABLE ENTITY");
        $response->setContent(json_encode($error));

        $logger = new FileAdapter($this->getLogFile('error'));
        $logger->log(date("Y-m-d H:i:s").' unProcessable ' .$message . ' ' . json_encode($data));
       // $this->composePushLog("error", "UNPROCESSABLE " . $message, " " . json_encode($data));

        return $response;
    }

    /* formats data error response messages */

    public function dataError($message, $data) {
        $response = new Response();
        $response->setHeader("Content-Type", "application/json");
        $response->setHeader("Access-Control-Allow-Origin", "*");
        $error["error"] = $message;
        $error["data"] = $data;
        $error["code"] = 421;
        $response->setStatusCode(421, "DATA ERROR");
        $response->setContent(json_encode($error));

        $logger = new FileAdapter($this->getLogFile('error'));
        $logger->log(date("Y-m-d H:i:s").'data error '.$message);
        //$this->composePushLog("error", "DATA ERROR " . $message, " " . $data);

        return $response;
    }

    public function sendMessage($msisdn, $message) {
        $postData = array(
            "sender" => "EnvirofitKE",
            "recipient" => trim($msisdn),
            "message" => $message
        );

        $channelAPIURL = "api.southwell.io/fastSMS/public/api/v1/messages";
        $username = "faith.wanjiku@envirofit.org";
        $password = "envirofit1234";


        $httpRequest = curl_init($channelAPIURL);
        curl_setopt($httpRequest, CURLOPT_NOBODY, true);
        curl_setopt($httpRequest, CURLOPT_POST, true);
        curl_setopt($httpRequest, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($httpRequest, CURLOPT_TIMEOUT, 10);
        curl_setopt($httpRequest, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($httpRequest, CURLOPT_HTTPHEADER, array('Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($postData))));
        curl_setopt($httpRequest, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($httpRequest, CURLOPT_USERPWD, "$username:$password");
        $postresponse = curl_exec($httpRequest);
        $httpStatusCode = curl_getinfo($httpRequest, CURLINFO_HTTP_CODE); //get status code
        curl_close($httpRequest);



        $response = array(
            'httpStatus' => $httpStatusCode,
            'response' => json_decode($postresponse)
        );

        $logger = new FileAdapter($this->getLogFile());
        $logger->log($message . ' ' . json_encode($response));

        return $response;
    }

    public function sendEmail1($ticket) {
//        $postData = array(
//            "sender" => "EnvirofitKE",
//            "recipient" => $msisdn,
//            "message" => $message
//        );

        $emailMessage = "<div>"
                . "<h5>Dear <strong>" . $ticket['assigneeName'] . "</strong>,</h5>"
                . "The following ticket has been assigned to you. Please ensure its been resolved."
                . "<p>Ticket Name: <strong>" . $ticket['ticketTitle'] . "</strong></p>"
                . "<p>Ticket Category: <strong>" . $ticket['ticketCategoryName'] . "</strong></p>"
                . "<p>Ticket Priority: <strong>" . $ticket['priorityName'] . "</strong></p>"
                . "<br/>"
                . "<br/>"
                . "Envirofit Customer Service Team"
                . "<br/>"
                . "Assigned by: " . $ticket['name']
                . "</div>";

        $workEmail = $ticket['workEmail'];

        $postData = array(
            "emailMessage" => $emailMessage,
            "emailSubject" => "Ticket Assignment Notification",
            "recipient" => $workEmail,
            "recipient_to_cc" => ""
        );

        $channelAPIURL = "api.southwell.io/mailer/";
        $username = "faith.wanjiku@envirofit.org";
        $password = "envirofit1234";


        $httpRequest = curl_init($channelAPIURL);
        curl_setopt($httpRequest, CURLOPT_NOBODY, true);
        curl_setopt($httpRequest, CURLOPT_POST, true);
        curl_setopt($httpRequest, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($httpRequest, CURLOPT_TIMEOUT, 10);
        curl_setopt($httpRequest, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($httpRequest, CURLOPT_HTTPHEADER, array('Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($postData))));
        $postresponse = curl_exec($httpRequest);
        $httpStatusCode = curl_getinfo($httpRequest, CURLINFO_HTTP_CODE); //get status code
        curl_close($httpRequest);



        $response = array(
            'httpStatus' => $httpStatusCode,
            'response' => json_decode($postresponse)
        );

        $logger = new FileAdapter($this->getLogFile());
        $logger->log($emailMessage . ' ' . json_encode($response));

        return $response;
    }

    public function sendEmail($ticket) {
        $mail = new PHPMailer;
        //Enable SMTP debugging. 
//        $mail->SMTPDebug = 3;
        //Set PHPMailer to use SMTP.
        $mail->isSMTP();
        //Set SMTP host name                          
        $mail->Host = "smtp.gmail.com";

        //Set this to true if SMTP host requires authentication to send email
        $mail->SMTPAuth = true;

        //Provide username and password     
        $mail->Username = "stats@southwell.io";
        $mail->Password = "MmeJK>99";

        //If SMTP requires TLS encryption then set it
        $mail->SMTPSecure = "tls";
//Set TCP port to connect to 
        $mail->Port = 587;

        $mail->From = "tech@southwell.io";
        $mail->FromName = "Envirofit Notifier";

        $workEmail = $ticket['workEmail'];
        $mail->addAddress($workEmail);
        $mail->isHTML(true);

        $mail->Subject = "Email Notification";
        $mail->Body = "<div style='background-color:#EBEEEE;padding:10px;'>"
                . "<h3>Ticket Assignment</h3>"
                . "<h5>Dear <strong>" . $ticket['assigneeName'] . "</strong>,</h5>"
                . "The following ticket has been assigned to you. Please ensure its been resolved."
                . "<p>Ticket Name: <strong>" . $ticket['ticketTitle'] . "</strong></p>"
                . "<p>Ticket Category: <strong>" . $ticket['ticketCategoryName'] . "</strong></p>"
                . "<p>Ticket Priority: <strong>" . $ticket['priorityName'] . "</strong></p>"
                . "<br/>"
                . "<br/>"
                . "Envirofit Customer Service Team"
                . "<br/>"
                . "Assigned by: " . $ticket['triggerName']
                . "</div>";

        $message = '';

        if (!$mail->send()) {
            $message = 'Error while sending email';
        } else {
            $message = "email has been sent successfully";
        }

        $logger = new FileAdapter($this->getLogFile());
        $logger->log($message);

        //return $message;
    }

    private function sendAndroidPushNotification($data, $title, $body, $userID, $appName) {
        $logger = new FileAdapter($this->getLogFile());


        $jsonPayload = array();
        $url;
        if (!$userID) {
            $url = "http://api.southwell.io/mobile_devices_v1/push/broadcast/$appName";

            $jsonPayload = array("appName" => $appName,
                "body" => $body,
                "title" => $title,
                "data" => $data);
        } else {
            $url = "http://api.southwell.io/mobile_devices_v1/push/broadcast/$appName";

            $jsonPayload = array("appName" => $appName,
                "body" => $body,
                "title" => $title,
                "data" => $data,
                "users" => $userID);
        }


        $headers = array(
            'Content-Type:application/json'
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonPayload));
        $result = curl_exec($ch);

        curl_close($ch);

        if ($result === true) {
            $logger->log("Push notification sent SUCCESS " . $result);
            return $result;
        } else {
            $logger->log("Push notification sent FAILED " . $result);
            return $result;
        }
    }

    public function sendPushNotification($data, $title, $body, $userID) {

        $appName = "com.southwell.envirofitsalesapp"; 
        return $this->sendAndroidPushNotification($data, $title, $body, $userID, $appName);
    }

    public function formatMobileNumber($mobile) {
        $mobile = preg_replace('/\s+/', '', $mobile);
        $input = substr($mobile, 0, -strlen($mobile) + 1);
        $number = '';
        if ($input == '0') {
            $number = substr_replace($mobile, '254', 0, 1);

            return $number;
        } elseif ($input == '+') {
            $number = substr_replace($mobile, '', 0, 1);
        } elseif ($input == '7') {
            $number = substr_replace($mobile, '2547', 0, 1);
        } else {
            $number = $mobile;
        }
        return $number;
    }

    protected function mobile($number) {
        $regex = '/^(?:\+?(?:[1-9]{3})|0)?7([0-9]{8})$/';
        if (preg_match_all($regex, $number, $capture)) {
            $msisdn = '2547' . $capture[1][0];
        } else {
            $msisdn = false;
        }
        return $msisdn;
    }


    public function sendPayment($customerData,$account,$depositAmount,$referenceNumber){
         $url ="https://lpgadmin.envirofit.org/api/v1/clientPayments";
         $verifCode = strtoupper(substr(md5(date('YmdHis').''.(10.4*100)),0,6));
         $data = array("meterNumber"=>$account,
                    "amount"=>$depositAmount,
                    "payerName"=>$customerData[0]['fullName'],
                    "paymentDateTime"=>date("Y-m-d H:i:s"),
                    "payerPhone"=>$customerData[0]['workMobile'],
                    "referenceNumber"=>$referenceNumber,
                     "currency"=>"KES",
                    "verifCode"=>$verifCode);   
    
         $authUrl="https://lpgadmin.envirofit.org/oauth/token";
                 //$url = $this->EnvirofitBaseAPIURL . "/" . $this->authToken; 
                 $payload = 
                  [ "client_secret" => $this->EnvirofitClientSecret, 
                     "client_id" => $this->EnvirofitClientID, 
                      "grant_type" => "client_credentials" 
                  ];
              /** * {"token_type":"Bearer","expires_in":86400,"access_token":"eyJ0..."} */ 
              $response = $this->sendPostJsonData($authUrl, $payload); 
              $statusCode = $response['statusCode']; 

              $this->success(json_encode($payload)." $statusCode authorization query ".json_encode($response));
              
              if ($statusCode == 200) { 
                    $response = json_decode($response['response']); 
                    if (!empty($response)) 
                        { 
                             $access_token = $response->access_token; 
                           //  $request_headers = array( 'Authorization' => 'Bearer ' . $access_token );
                             //$paymentResponse = $this->sendPostJsonData($url, $data,$request_headers); 
                             $httpRequest = curl_init($url);
                             curl_setopt($httpRequest, CURLOPT_NOBODY, true); 
                            curl_setopt($httpRequest, CURLOPT_POST, true); 
                            curl_setopt($httpRequest, CURLOPT_POSTFIELDS, json_encode($data)); 
                            curl_setopt($httpRequest, CURLOPT_TIMEOUT, 10); 
                            //timeout after 30 seconds 
                            curl_setopt($httpRequest, CURLOPT_RETURNTRANSFER, 1); 
                            curl_setopt($httpRequest, CURLOPT_HTTPHEADER, 
                                        array('Content-Type: application/json', 
                                                'Content-Length: ' . strlen(json_encode($data)),
                                                'authorization: Bearer '.$access_token)); 
                            //curl_setopt( $ch, CURLOPT_HEADER, 0); 
                            curl_setopt($httpRequest, CURLOPT_RETURNTRANSFER, true); 
                            curl_setopt($httpRequest, CURLOPT_HEADER, false); 
                            $response = curl_exec($httpRequest); 
                            $status = curl_getinfo($httpRequest, CURLINFO_HTTP_CODE); 
                            curl_close($httpRequest);
                            
                            $this->success("$status payment query ".json_encode($data));

                            if($status == 200 ){
                                $logger = new FileAdapter($this->getLogFile('success'));
                                $logger->log(date("Y-m-d H:i:s").' lpg refil payment '.$response);

                            }
                            else{
                                 $logger = new FileAdapter($this->getLogFile('error'));
                                 $logger->log(date("Y-m-d H:i:s").' lpg refil payment '.$response);

                            }
                        }
            }
                     
    }

    public function sendPostJsonData($url, $payload,$request_headers=null) { 
                $httpRequest = curl_init($url);

                curl_setopt($httpRequest, CURLOPT_NOBODY, true); 
                curl_setopt($httpRequest, CURLOPT_POST, true); 
                curl_setopt($httpRequest, CURLOPT_POSTFIELDS, json_encode($payload)); 
                curl_setopt($httpRequest, CURLOPT_TIMEOUT, 10); 
                //timeout after 30 seconds 
                curl_setopt($httpRequest, CURLOPT_RETURNTRANSFER, 1); 
                curl_setopt($httpRequest, CURLOPT_HTTPHEADER, array('Content-Type: ' 
                    . 'application/json', 'Content-Length: ' . strlen(json_encode($payload)))); 
                //curl_setopt( $ch, CURLOPT_HEADER, 0); 
                curl_setopt($httpRequest, CURLOPT_RETURNTRANSFER, true); 
                curl_setopt($httpRequest, CURLOPT_HEADER, false); 
                $response = curl_exec($httpRequest); 
                $status = curl_getinfo($httpRequest, CURLINFO_HTTP_CODE); 
                curl_close($httpRequest); 
                return array( "statusCode" => isset($status) ? $status : 0, "response" => $response );

        }

}
