<?php

if(is_tc_admin_eligible()){
    add_action('admin_menu', 'mt_add_pages');
}

function mt_add_pages() {
    add_menu_page(__('ASTRA','menu-test'), __('ASTRA','menu-test'), 'manage_options', 'mt-top-level-handle', 'mt_toplevel_page', 'dashicons-shield-alt' );
}

function mt_toplevel_page() {
    if (!current_user_can('manage_options')) {
        die("Sorry, but you do not have permission to perform this action.");
    }
    $autoload_file = "Astra.php";
    $config_file = "astra-config.php";

    $firewall_path = dirname(__FILE__) . '/astra/';
    if (file_exists($firewall_path . $autoload_file)) {
        if (is_admin()) {
            require_once($firewall_path . $autoload_file);
            $astra = new Astra();
            $token = $astra->get_sso_token();
            do_login(CZ_SITE_KEY, $token);
        }
    }

}

function is_tc_admin_eligible(){
    $is_trial = isset($_SERVER['TRIAL']) && $_SERVER['TRIAL'] === "false" ? true : false;
    if(isset($_SERVER['themecloud']) && $is_trial === true){
        return true;
    }

    return false;
}


function do_login($site_key, $token){
    $url = "https://www.getastra.com/a/ext/login/$site_key";

    $str = "<form action='$url' method='post' name='frm'>";
    $str .= "<input type='hidden' name='token' value='" . $token . "'>";
    $str .= "</form>
<script type='text/javascript'>
    document.frm.submit();
</script>";

    echo $str;
    exit;
}

?>