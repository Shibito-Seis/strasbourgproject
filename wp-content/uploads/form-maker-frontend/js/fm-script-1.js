    var fm_currentDate = new Date();
    var FormCurrency_1 = '$';
    var FormPaypalTax_1 = '0';
    var check_submit1 = 0;
    var check_before_submit1 = {};
    var required_fields1 = ["6","9","10","11","13","14"];
    var labels_and_ids1 = {"6":"type_text","9":"type_name","10":"type_date_fields","11":"type_submitter_mail","12":"type_checkbox","13":"type_own_select","14":"type_textarea","16":"type_submit_reset"};
    var check_regExp_all1 = [];
    var check_paypal_price_min_max1 = [];
    var file_upload_check1 = [];
    var spinner_check1 = [];
    var scrollbox_trigger_point1 = '20';
    var header_image_animation1 = 'none';
    var scrollbox_loading_delay1 = '0';
    var scrollbox_auto_hide1 = '1';
         function before_load1() {
     
}

 function before_submit1() {
      }

 function before_reset1() {
     
}
 function after_submit1() {
     
}
    function onload_js1() {
  jQuery("#wdform_10_day1").blur(function() {if (jQuery(this).val() == "0") jQuery(this).val(""); else add_0(this)});
  jQuery("#wdform_10_month1").blur(function() {if (jQuery(this).val() == "0") jQuery(this).val(""); else add_0(this)});
    }
    function condition_js1() {
    }
    function check_js1(id, form_id) {
    if (id != 0) {
    x = jQuery("#" + form_id + "form_view"+id);
    }
    else {
    x = jQuery("#form"+form_id);
    }    }
    function onsubmit_js1() {
    
  jQuery("<input type=\"hidden\" name=\"wdform_12_allow_other1\" value=\"no\" />").appendTo("#form1");
  jQuery("<input type=\"hidden\" name=\"wdform_12_allow_other_num1\" value=\"0\" />").appendTo("#form1");
    var disabled_fields = "";
    jQuery("#form1 div[wdid]").each(function() {
      if(jQuery(this).css("display") == "none") {
        disabled_fields += jQuery(this).attr("wdid");
        disabled_fields += ",";
      }
    })
    if(disabled_fields) {
      jQuery("<input type=\"hidden\" name=\"disabled_fields1\" value =\""+disabled_fields+"\" />").appendTo("#form1");
    };    }
    form_view_count1 = 0;
    jQuery(document).ready(function () {
    if (jQuery('form#form1 .wdform_section').length > 0) {
    fm_document_ready(1);
    }
    });
    jQuery(document).ready(function () {
    if (jQuery('form#form1 .wdform_section').length > 0) {
    formOnload(1);
    }
    });
    