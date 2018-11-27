<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

ini_set('display_errors', 'Off');
error_reporting(-1);

if (function_exists("opcache_reset")) {
    opcache_reset();
}

if (!defined('ASTRAPATH'))
    define('ASTRAPATH', dirname(__FILE__) . '/');

require_once(ASTRAPATH . 'Astra.php');
require_once(ASTRAPATH . 'astra-config.php');
require_once(ASTRAPATH . 'libraries/Crypto.php');
/* Dream */
if (!class_exists('Api')) {

    class Api
    {

        protected $receivedResponse;
        protected $response = array();

        public function __construct($included = FALSE)
        {
            if (!$included)
                $this->run();
        }

        protected function respond($code, $msg = "")
        {
            $this->response['origin'] = 'client';
            $this->response['code'] = $code;
            if ($msg == "") {
                switch ($code) {
                    case 0:
                        $msg = "failed";
                        break;
                    case 1:
                        $msg = "success";
                        break;
                    default:
                        $msg = "error";
                        break;
                }
            }

            $this->response['msg'] = $msg;

            header('Content-Type: application/json');
            echo json_encode($this->response);

            die();
        }

        protected function authenticate()
        {

            $accessResponse = !empty($_POST['access_code']) ? $_POST['access_code'] : '';

            if ($accessResponse != CZ_ACCESS_KEY)
                $this->respond(-1, "Access Code doesn't match");

            $encResponse = $_POST['encRequest'];

            $crypto = new Astra_crypto();
            $this->receivedResponse = @unserialize($crypto->decrypt($encResponse, CZ_SECRET_KEY));

            //echo $this->receivedResponse['client_key'] . "-" . CZ_CLIENT_KEY;
            if (($this->receivedResponse == false) || ($this->receivedResponse['client_key'] != CZ_CLIENT_KEY))
                $this->respond(-1, "Secret/Client Key Mismatch");

            return TRUE;
        }

        public function bad_bots_update()
        {
            require_once(ASTRAPATH . 'libraries\Update_bad_bots.php');
            if (update_bad_bots())
                $this->respond(1);
            else
                $this->respond(0, "Unable to respond");
        }

        protected function filters_update()
        {
            require_once(ASTRAPATH . 'libraries\Crypto.php');

            $dataArray['client_key'] = CZ_CLIENT_KEY;
            $dataArray['api'] = "get_filters";
            $str = serialize($dataArray);

            $crypto = new Astra_crypto();
            $encrypted_data = $crypto->encrypt($str, CZ_SECRET_KEY);

            $postdata = http_build_query(
                array(
                    'encRequest' => $encrypted_data,
                    'access_code' => CZ_ACCESS_KEY,
                )
            );

            $opts = array('http' =>
                array(
                    'method' => 'POST',
                    'header' => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $postdata
                )
            );
            $context = stream_context_create($opts);

            $server_reply = file_get_contents(CZ_API_URL, FALSE, $context);

            $filter_file_path = 'libraries/plugins/IDS/default_filter.xml';

            if (!empty($server_reply)) {
                if (is_writeable(dirname(ASTRAPATH . $filter_file_path))) {
                    $dlHandler = fopen(ASTRAPATH . $filter_file_path, 'w');
                    if (!fwrite($dlHandler, $server_reply)) {
                        $this->respond(0, "Unable to write");
                        exit();
                    }
                    fclose($dlHandler);
                    $this->respond(1);
                } else {
                    $this->respond(0, "Directory not writable");
                }
            } else {
                $this->respond(0, "Empty Response");
            }

            $this->respond(0);
        }

        protected function astra_update()
        {
            require_once(ASTRAPATH . 'libraries/Updater.php');

            echo_debug("API Call received");
            echo_debug($this->receivedResponse);

            $server_version = $this->receivedResponse['version'];

            $platform = isset($this->receivedResponse['platform']) ? $this->receivedResponse['platform'] : 'php';

            echo_debug("Loading updater");

            $updater = new Astra_updater($server_version);

            if ($updater->is_update()) {
                if ($updater->download_file()) {
                    sleep(1);
                    if ($updater->update($platform)) {
                        sleep(1);
                        $updater->delete();

                        ## Initialize the Setup Process
                        require_once('libraries/API_connect.php');
                        $connect = new API_connect();
                        if (!$connect->report_update()) {
                            $this->respond(-1, "Unable to report update");
                        }
                    }

                    $this->respond(1);
                }
            }

            $this->respond(-1, "Noting to update");
        }

        protected function update_config()
        {
            require_once(ASTRAPATH . 'libraries/Update_config.php');

            if (isset($this->receivedResponse['constant_check_for_quotes'])) {
                $quotes = $this->receivedResponse['constant_check_for_quotes'];
            } else
                $quotes = FALSE;

            if (update_config($this->receivedResponse['constant_name'], $this->receivedResponse['constant_value'], $quotes)) {
                $this->respond(1);
            } else {
                $this->respond(0);
            }
        }

        protected function get_version()
        {
            $ret = explode(".", CZ_ASTRA_CLIENT_VERSION);
            $this->response['version'] = array('major' => $ret[0], 'minor' => $ret[1], 'patch' => $ret[2]);
            $this->respond(1);
        }

        protected function valid_param($val)
        {
            if($this->receivedResponse['rule_type'] === "url"){
                return true;
            }

            $val = strtolower($val);
            $allowed = array('get.', 'post.', 'request.', 'cookie.', 'session.');

            foreach ($allowed as $a) {
                if (substr($val, 0, strlen($a)) == $a)
                    return TRUE;
            }
            return FALSE;
        }

        protected function whitelist_url()
        {
            ASTRA::connect_db();

            $action = $this->receivedResponse['rule_action'];
            $val = $this->receivedResponse['rule_val'];

            $allowed_rule_action = array('add', 'delete');

            if (in_array($action, $allowed_rule_action)) {
                switch ($action) {
                    case 'add':
                        $result = ASTRA::$_db->add_whitelist_url($val);
                        break;
                    case 'delete':
                        $result = ASTRA::$_db->delete_whitelist_url($val);
                        break;
                }

                if($result == TRUE){
                    $this->respond(1);
                }else{
                    $this->respond(0, "Unable to update db");
                }

            } else {
                $this->respond('0', 'Not a valid action');
            }

        }

        protected function custom_rule()
        {
            //rule_action = add, delete
            //rule_type = "trusted", "blocked", "exception", "html" ,"json"

            ASTRA::connect_db();

            $action = $this->receivedResponse['rule_action'];
            $type = $this->receivedResponse['rule_type'];
            $val = $this->receivedResponse['rule_val'];

            $allowed_rule_action = array('add', 'delete');

            $ip_check = array('trusted', 'blocked');
            $param_check = array('exception', 'html', 'json', 'url');
            $allowed_rule_type = array_merge($ip_check, $param_check);

            if (in_array($action, $allowed_rule_action) && in_array($type, $allowed_rule_type)) {
                if (in_array($type, $ip_check) && filter_var($val, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== TRUE) {
                    if (ASTRA::$_db->edit_ip_exception($action, $type, $val)) {
                        $this->respond(1);
                    } else {
                        $this->respond('0', 'Unable to set');
                    }
                } elseif (in_array($type, $param_check) && $this->valid_param($val)) {
                    if (ASTRA::$_db->edit_param_exception($action, $type, $val)) {
                        $this->respond(1);
                    } else {
                        $this->respond(0, "Couldn't edit exception");
                    }
                } else {
                    $this->respond('0', 'Could not validate');
                }
            } else {
                $this->respond('0', 'Not Allowed');
            }
        }

        public function run()
        {

            if (!$this->authenticate()) {
                $this->respond(-1, "Invalid API Call");
            }

            echo_debug($this->receivedResponse);

            $api = $this->receivedResponse['api'];

            switch ($api) {
                case "ping":
                    $this->respond(1);
                    break;
                case "version":
                    $this->get_version();
                    break;
                case "update":
                    $this->astra_update();
                    break;
                case "update_bad_bots":
                    $this->bad_bots_update();
                    break;
                case "update_filters":
                    $this->filters_update();
                    break;
                case "custom_rule":
                    $this->custom_rule();
                    break;
                case "update_config":
                    $this->update_config();
                    break;
                default:
                    echo "ping";
            }
        }

    }

}

if (class_exists('Api')) {
    $astra_api = new Api();
}