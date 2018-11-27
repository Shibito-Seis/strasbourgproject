<?php

if (!defined('PP_ASTRAPATH'))
    define('PP_ASTRAPATH', dirname(__FILE__) . '/astra/');

if (!class_exists('PP_setup')) {
    class PP_setup{

        protected $api_base_url = "https://www.getastra.com/a/api/pp/";
        protected $response = array();

        function is_request_from_astra(){
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : "empty";

            if(strpos($user_agent, "astra") !== false){
                return true;
            }

            return false;
        }

        function notify_failure(){
            $this->response = array(
                'success' => false,
                'message' => '',
                'api_response' => '',
            );

            $data = $this->collect_data();

            if(empty($data)){
                $this->response['message'] = "Unable to collect data";
                return false;
            }


            $request = $this->api_base_url . 'themecloud/notify_failure';
            $authorization = "Authorization: Bearer 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";

            //echo http_build_query($data, '', '&');

            $session = curl_init($request);
            curl_setopt($session, CURLOPT_HTTPHEADER, array($authorization));
            curl_setopt($session, CURLOPT_POST, true);
            curl_setopt($session, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
            curl_setopt($session, CURLOPT_HEADER, false);
            /* curl_setopt($session, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2); */
            curl_setopt($session, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($session, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($session, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($session);


            if(!curl_errno($session))
            {
                $info = curl_getinfo($session);
            }else{
                $info['body'] = array();
                return $info;
            }

            curl_close($session);

//echo $response;
            $info['body'] = json_decode($response, true);

            $this->response['api_response'] = $info;

            //print_r($info);
            //die();
            return $info;

        }

        function register(){

            $this->response = array(
                'success' => false,
                'message' => '',
                'api_response' => '',
            );

            if(!$this->is_request_from_astra() && $this->is_eligible()){
                $data = $this->collect_data();
                $request = $this->send_request($data);


                if($this->request_ok($request)){
                    $updated_config = $this->update_config($request);
                    $this->response["success"] = TRUE;
                }
            }else{
                $this->response['message'] = "Not eligible for a license";
            }

            return $this->response;
        }

        protected function request_ok($request){

            if($request['http_code'] !== 200){
                $this->response['message'] = "API response not 200";
                return false;
            }

            $json = $request['body'];

            if(!empty($json) && is_array($json) && count($json) > 0 && $json['success'] == true){
                return true;
            }

            $this->response['message'] = "API response conditions not met";
            return false;
        }

        protected function is_eligible(){

            if(empty($_SERVER["SERVER_NAME"])){
                $this->response['message'] = "Website is currently not eligible";
                return false;
            }

            if (isset($_SERVER['themecloud']) && strpos($_SERVER["SERVER_NAME"], 'themecloud.website') === false) {
                return TRUE;
            } else {
                $this->response['message'] = "This is a Trial themecloud website";
                return FALSE;
            }

            /*

            $is_trial = isset($_SERVER['TRIAL']) && $_SERVER['TRIAL'] === "false" ? true : false;
            if(isset($_SERVER['themecloud']) && $is_trial === true){
                return true;
            }
            */

            $this->response['message'] = "Website is currently not eligible";
            return false;
        }

        protected function update_config($request){

            $keys = array('CZ_SECRET_KEY', 'CZ_CLIENT_KEY', 'CZ_ACCESS_KEY', 'CZ_SITE_KEY');

            require_once(PP_ASTRAPATH . 'astra-config.php');
            require_once(PP_ASTRAPATH . 'libraries/Update_config.php');

            foreach($keys as $key){
                if(!empty($request['body'][$key])){
                    $key_value = base64_encode('"' . $request['body'][$key] . '"');
                    $update_config = update_config($key, $key_value, false);
                    if($update_config == false){
                        $this->response['message'] = "Unable to update config file";
                        return false;
                    }
                }else{
                    $this->response['message'] = "$key not found in API response";
                    return false;
                }
            }

            // Iterate each key and update the config file
            // Only return true if all the keys have been udpated
            return true;
        }

        protected function collect_data_wp(){
            $tc_include_path = "/app/wp-load.php";
            if(!file_exists($tc_include_path)){
                $this->response['message'] = "WordPress Load file not found";
                return array();
            }

            include($tc_include_path);

            $wp = array();
            $wp['site_url']  = get_bloginfo('url');
            $wp['cms_version']  = get_bloginfo('version');

            $users = get_users('role=Administrator&number=1');

            $user = !empty($users[0]) ? $users[0] : '';

            $wp['user_email']  = !empty($user->user_email) ? $user->user_email : "";
            $wp['user_username'] = !empty($user->user_login) ? $user->user_login : "";
            $wp['user_firstname'] = get_user_meta($user->ID, 'first_name', true);
            $wp['user_lastname'] = get_user_meta($user->ID, 'last_name', true);
            $wp['cms_name'] = 'themecloud';
            return $wp;
        }

        protected function collect_data(){

            $wp = $this->collect_data_wp();

            if(empty($wp)){
                return array();
            }

            $server_keys = array('HTTP_USER_AGENT', 'SERVER_NAME', 'SERVER_ADDR', 'SERVER_SOFTWARE', 'HTTP_SCHEME', 'HTTP_HOST', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR');

            foreach($server_keys as $key){
                $data[$key] = !empty($_SERVER[$key]) ? $_SERVER[$key] : '';
            }

            $data['php_version'] = function_exists('phpversion') ? phpversion() : '';

            return $data + $wp;
        }

        protected function send_request($data){
            if(empty($data)){
                $this->response['message'] = "Unable to collect data";
                return false;
            }


            $request = $this->api_base_url . 'themecloud/register';
            $authorization = "Authorization: Bearer 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";

            //echo http_build_query($data, '', '&');

            $session = curl_init($request);
            curl_setopt($session, CURLOPT_HTTPHEADER, array($authorization));
            curl_setopt($session, CURLOPT_POST, true);
            curl_setopt($session, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
            curl_setopt($session, CURLOPT_HEADER, false);
            /* curl_setopt($session, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2); */
            curl_setopt($session, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($session, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($session, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($session);


            if(!curl_errno($session))
            {
                $info = curl_getinfo($session);
            }else{
                $info['body'] = array();
                return $info;
            }
            curl_close($session);

            $info['body'] = json_decode($response, true);

            $this->response['api_response'] = $info;

            //print_r($info);
            return $info;
        }
    }
}

?>