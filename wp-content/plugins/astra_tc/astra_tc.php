<?php

/*
  Plugin Name: Astra Security for Themecloud
  Plugin URI: https://www.getastra.com/
  Description: Website Security Simplified
  Version: 2.3
  Author: Astra Web Security
  Author URI: https://www.getastra.com/
 */

defined('ABSPATH') or ( 'Plugin file cannot be accessed directly.' );

//error_reporting(E_ALL);
if (!class_exists('Astra_tc')) {

    class Astra_tc {

        protected $firewall_path = "";
        protected $autoload_file = "Astra.php";

        function cz_action_user_login_failed($username) {
            require_once($this->firewall_path . 'libraries/API_connect.php');
            $client_api = new Api_connect();
            $ret = $client_api->send_request("has_loggedin", array("username" => $username, "success" => 0,), "wordpress");

            return true;
        }

        function cz_action_user_login_success($user_info, $u) {
            require_once($this->firewall_path . 'libraries/API_connect.php');

            $user = $u->data;

            unset($user->user_pass, $user->ID, $user->user_nicename, $user->user_url, $user->user_registered, $user->user_activation_key, $user->user_status);

            if (current_user_can('manage_options')) {
                $user->admin = 1;
            }

            $client_api = new Api_connect();
            $ret = $client_api->send_request("has_loggedin", array("user" => $user, "success" => 1,), "wordpress");

            return true;
        }

        protected function check_url($url) {
            try {
                $curl = curl_init($url);
                curl_setopt($curl, CURLOPT_NOBODY, true);
                $result = curl_exec($curl);
                if ($result !== false) {
                    $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                    if ($statusCode == 404) {
                        return FALSE;
                    } else {
                        return TRUE;
                    }
                } else {
                    return FALSE;
                }
            } catch (Exception $e) {
                $headers = @get_headers($url);
                if (strpos($headers[0], '404') === false)
                    return TRUE;
                else
                    return FALSE;
            }
        }

        protected function api_file_url() {
            if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
                $current_url = "https://";
            } else {
                $current_url = "http://";
            }

            $current_url .= str_replace(realpath($_SERVER["DOCUMENT_ROOT"]), $_SERVER['HTTP_HOST'], realpath(dirname(__FILE__)));

            return $current_url;
        }

        function is_request_from_astra(){
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : "empty";

            if(strpos($user_agent, "astra") !== false){
                return true;
            }

            return false;
        }

        protected function is_tc_eligible(){
            /*
            $is_trial = isset($_SERVER['TRIAL']) && $_SERVER['TRIAL'] === "false" ? true : false;
            if(isset($_SERVER['themecloud']) && $is_trial === true){
                return true;
            }

            return false;
            */

            if(empty($_SERVER["SERVER_NAME"])){
                $this->response['message'] = "Website is currently not eligible";
                return false;
            }

            if (isset($_SERVER['themecloud']) && strpos($_SERVER["SERVER_NAME"], 'themecloud.website') === false) {
                return true;
            }

            return false;

        }

        protected function is_tc_setup(){
            require_once($this->firewall_path . 'astra-config.php');

            if(defined("CZ_SECRET_KEY") && !empty(CZ_SECRET_KEY)){
                return TRUE;
            }

            if(!$this->is_tc_eligible()){
                return true;
            }

            if(!file_exists(__DIR__ . "/PP_setup.php") || $this->is_request_from_astra()){
                return true;
            }

            opcache_reset();

            $flag_register = __DIR__ . '/cz-flag-register.txt';

            if(file_exists($flag_register)){
                return true;
            }else{
                $current = time();
                file_put_contents($flag_register, $current);
            }

            include(__DIR__ . '/PP_setup.php');

            $setup = new PP_setup();
            $register = $setup->register();

            $file = __DIR__ . '/cz-tc-register-attempt.txt';

            //print_r($register);

            if($register['success'] == false){
                if(!file_exists($file)){
                    $current = time();
                    file_put_contents($file, $current);
                }else{
                    // Notify astra.
                    // Disable Astra
                    // Delete PP_setup file?
                    $setup->notify_failure();
                    unlink($file);
                    unlink(__DIR__ . "/PP_setup.php");
                }
            }elseif($register['success'] == true){
                if(file_exists(__DIR__ . "/PP_setup.php")){
                    unlink(__DIR__ . "/PP_setup.php");
                }
            }

            return false;

        }

        public function __construct() {

            $this->firewall_path = __DIR__ . '/astra/';

            if(!$this->is_tc_setup()){
                return false;
            }

            if (file_exists($this->firewall_path . $this->autoload_file)) {
                if (!is_admin()) {
                    require_once($this->firewall_path . $this->autoload_file);
                    $astra = new Astra();
                } else {
                    require_once 'astra_admin.php';
                }
                add_action('wp_login', array(&$this, 'cz_action_user_login_success'), 10, 2);
                add_action('wp_login_failed', array(&$this, 'cz_action_user_login_failed'), 10, 1);
            }
        }

    }

    new Astra_tc;
}