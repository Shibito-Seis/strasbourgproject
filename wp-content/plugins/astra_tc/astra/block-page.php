<?php

include('libraries/Astra_ip.php');
$astra_ip = new Astra_ip();
$client_ip = $astra_ip->get_ip_address();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Attention!</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>

        * {
            line-height: 1.2;
            margin: 0;
        }

        html {
            color: #888;
            display: table;
            font-family: sans-serif;
            height: 100%;
            text-align: center;
            width: 100%;
        }

        body {
            display: table-cell;
            vertical-align: middle;
            margin: 2em auto;
        }

        h1 {
            color: #555;
            font-size: 2em;
            font-weight: 400;
        }

        p {
            margin: 0 auto;
        }

        div.image{
            margin-bottom: 20px;
        }

        div.image img{
            width: 50%;
        }

        @media (min-width: 768px){
            div.image img{
                width: 15%;
            }
        }

        div.go-back{
            padding-top: 20px;
        }

        div.go-back a{
            font-size: 14px;
        }

        p{
            width: 600px;
            margin-top: 30px;
        }
        a{
            cursor: pointer;
            color: #2b64d0;
        }
        @media only screen and (max-width: 280px) {

            body, p {
                width: 95%;
            }

            h1 {
                font-size: 1.5em;
                margin: 0 0 0.3em;
            }

        }

    </style>

    <?php

    function hide_email($email) {
        $character_set = '+-.0123456789@ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz';
        $key = str_shuffle($character_set); $cipher_text = ''; $id = 'e'.rand(1,999999999);
        for ($i=0;$i<strlen($email);$i+=1) $cipher_text.= $key[strpos($character_set,$email[$i])];
        $script = 'var a="'.$key.'";var b=a.split("").sort().join("");var c="'.$cipher_text.'";var d="";';
        $script.= 'for(var e=0;e<c.length;e++)d+=b.charAt(a.indexOf(c.charAt(e)));';
        $script.= 'document.getElementById("'.$id.'").innerHTML="<a href=\\"mailto:"+d+"\\">"+d+"</a>"';
        $script = "eval(\"".str_replace(array("\\",'"'),array("\\\\",'\"'), $script)."\")";
        $script = '<script type="text/javascript">/*<![CDATA[*/'.$script.'/*]]>*/</script>';
        return '<span id="'.$id.'">[javascript protected email address]</span>'.$script;
    }
    $support_email = hide_email('help@getastra.com');
    ?>
</head>
<body>

<div class='image'>
    <img alt='What ASTRA Means' src='https://www.getastra.com/assets/images/hotlink-ok/astra-logo-landing-pages.png'>
</div>
<h1>Sorry, this is not allowed.</h1>
<p>Thank you for visiting our website, unfortunately our website protection system has detected an issue with your IP address and wont let you proceed any further.</p><p>If you feel this is an error, please email us with the time you had the issue to <?php echo $support_email; ?> or submit <span class="call_support" onclick="FreshWidget.show(); return false;" >a support</span> request. Thank you for your patience.</p>
<p><small><a href='https://www.getastra.com/'>https://www.getastra.com/</a></small></p>

<div class='go-back'>
    <p><a href='/'>Go to Homepage</a></p>
</div>

<script type="text/javascript" src="https://s3.amazonaws.com/assets.freshdesk.com/widget/freshwidget.js"></script>
<script type="text/javascript">
    var ip = '<?php echo $client_ip; ?>';
    FreshWidget.init("", {
        "queryString": "&formTitle=Request block review&submitTitle=Submit&submitThanks=We+have+received+your+request.+Our+engineers+will+get+back+to+you+via+email+shortly.&widgetType=popup&captcha=yes&helpdesk_ticket[subject]=Requesting block review ["+(window.location.hostname)+"]["+ip+"]&searchArea=no&disable[custom_field][product_id]=true&helpdesk_ticket[ticket_body_attributes]=<div>test</div>",
        "utf8": "✓",
        "widgetType": "popup",
        "buttonType": "text",
        "buttonText": "Support",
        "buttonColor": "white",
        "buttonBg": "rgb(12, 82, 146)",
        "alignment": "4",
        "offset": "235px",
        "formHeight": "500px",
        "captcha": "yes",
        "url": "https://astrawebsecurity.freshdesk.com"
    });
</script>
</body>
</html>