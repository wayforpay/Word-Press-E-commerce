<?php
/**
 *
 * Payment module
 * For plugin : E-commerce
 * Payment system : WayForPay
 * Cards : Visa, Mastercard, Privat24, Terminal, etc.
 *
 * Ver 1.0
 */

$nzshpcrt_gateways[$num]['name'] = 'WayForPay';
$nzshpcrt_gateways[$num]['internalname'] = 'wayforpay';
$nzshpcrt_gateways[$num]['function'] = 'gateway_wayforpay';
$nzshpcrt_gateways[$num]['form'] = "form_wayforpay";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_wayforpay";
$nzshpcrt_gateways[$num]['payment_type'] = "credit_card";
$nzshpcrt_gateways[$num]['display_name'] = 'WayForPay';

/**
 * @param $separator
 * @param $sessionid
 */
function gateway_wayforpay($separator, $sessionid)
{
    global $wpdb;
    $purchase_log_sql = "SELECT * FROM `" . WPSC_TABLE_PURCHASE_LOGS . "` WHERE `sessionid`= " . $sessionid . " LIMIT 1";
    $purchase_log = $wpdb->get_results($purchase_log_sql, ARRAY_A);

    $cart_sql = "SELECT * FROM `" . WPSC_TABLE_CART_CONTENTS . "` WHERE `purchaseid`='" . $purchase_log[0]['id'] . "'";
    $cart = $wpdb->get_results($cart_sql, ARRAY_A);

    // User details
    $cData = $_POST['collected_data'];
    $keys = "'" . implode("', '", array_keys($cData)) . "'";

    $que = "SELECT `id`,`type`, `unique_name` FROM `" . WPSC_TABLE_CHECKOUT_FORMS . "` WHERE `id` IN ( " . $keys . " ) AND `active` = '1'";
    $dat_name = $wpdb->get_results($que, ARRAY_A);

    $orderID = $_SERVER["HTTP_HOST"] . '_' . md5("wayforpay_" . $purchase_log[0]['id']);

    $forSend = array(
        'orderReference' => $orderID,
        'orderDate' => time(),
        'currency' => 'UAH',
        'language' => get_option('wayforpay_language')
    );
    $forSend['returnUrl'] = (get_option('wayforpay_returnUrl') != "") ? get_option('wayforpay_returnUrl') : false;
    $forSend['serviceUrl'] = (get_option('wayforpay_serviceUrl') != "") ? get_option('wayforpay_serviceUrlUrl') : false;
    foreach ($cart as $item) {
        $forSend['productName'][] = $item['name'];
        $forSend['productCount'][] = $item['quantity'];
        $forSend['productPrice'][] = $item['price'];
    }
    $forSend['amount'] = $purchase_log['0']['totalprice'];


    foreach ($dat_name as $v) {
        $billData[$v['unique_name']] = $v['id'];
    }

    $array = array(
        "clientFirstName" => 'billingfirstname',
        "clientLastName" => 'billinglastname',
        "clientAddress" => 'billingaddress',
        "clientCity" => 'billingcity',
        "clientPhone" => 'billingphone',
        "clientEmail" => 'billingemail',
        "clientCountry" => 'billingcountry',
        "clientZipCode" => 'billingpostcode',
        "deliveryFirstName" => 'shippingfirstname',
        "deliveryLastName" => 'shippinglastname',
        "deliveryAddress" => 'shippingaddress',
        "deliveryCity" => 'shippingcity',
        "deliveryCountry" => 'shippingcountry',
        "deliveryZipCode" => 'shippingpostcode'
    );


    foreach ($array as $k => $val) {
        $val = $billData[$val];
        if (!empty($_POST['collected_data'][$val])) {
            $val = $_POST['collected_data'][$val];
            if ($k == 'clientPhone') {
                $val = str_replace(array('+', ' ', '(', ')'), array('', '', '', ''), $val);
                if (strlen($val) == 10) {
                    $val = '38' . $val;
                } elseif (strlen($val) == 11) {
                    $val = '3' . $val;
                }
            } else if (($k == 'clientCountry' || $k == 'deliveryCountry') && is_array($val)) {
                $val = 'UKR';
            }
            $forSend[$k] = str_replace("\n", ', ', $val);
        }
    }

    if (($_POST['collected_data'][get_option('email_form_field')] != null) && ($forSend['clientEmail'] == null)) {
        $forSend['clientEmail'] = $_POST['collected_data'][get_option('email_form_field')];
    }


    $img = WPSC_URL . '/images/indicator.gif';

    $button = "<img style='position:absolute; top:50%; left:47%; margin-top:-125px; margin-left:-60px;' src='$img' >
	<script>
		function submitWayForPayForm()
		{
			document.getElementById('form_wayforpay').submit();
		}
		setTimeout( submitWayForPayForm, 200 );
	</script>";


    $pay = WayForPay::getInst()->fillPayForm($forSend);
    echo $button;
    echo $pay;

    $data = array(
        'processed' => 2,
        'transactid' => $orderID,
        'date' => time()
    );

    $where = array('sessionid' => $sessionid);
    $format = array('%d', '%s', '%s');
    $wpdb->update(WPSC_TABLE_PURCHASE_LOGS, $data, $where, $format);
    transaction_results($sessionid, false, $orderID);

    exit();
}

function nzshpcrt_wayforpay_callback()
{
    #Callback url : http://yoursite.com/?wayforpay_callback=true

    if (!isset($_GET['wayforpay_callback']) || ($_GET['wayforpay_callback'] != 'true')) return;

    $data = json_decode(file_get_contents("php://input"), true);
    echo WayForPay::getInst()->checkResponse($data);
    exit();
}

function nzshpcrt_wayforpay_results()
{
    return true;
}

function submit_wayforpay()
{

    $array = array(
        'wayforpay_merchantAccount',
        'wayforpay_secret_key',
        'wayforpay_url',
        'wayforpay_returnUrl',
        'wayforpay_serviceUrl',
        'wayforpay_language'
    );

    foreach ($array as $val) {
        if (isset($_POST[$val])) update_option($val, $_POST[$val]);
    }

    return true;
}


function form_wayforpay()
{


    $blLang = (get_bloginfo('language', "Display") !== "ru-RU") ? "en-US" : "ru-RU";
    $Cells = getCells();

    $otp = "";
    foreach ($Cells as $key => $val) {
        $dat = $val[$blLang];
        $otp .= "<div><label>$dat[name]</label>" .
            ((!$val['isInput']) ? $val['code'] : "<input type='text' size='40' value='" . get_option($key) . "' name='$key' />") .
            "<div class='subtext'>" . (($dat['subText'] == "") ? "&nbsp;" : $dat['subText']) . "</div>
				</div>";
    }


    $output = "<style>
		#wayforpayoptions label{ width:150px; font-weight:bold; display: inline-block; }
		#wayforpayoptions .subtext{ margin-left:160px; font-size:8px; font-style:italic; }
		#wayforpayoptions{ border:1px dotted #aeaeae; padding:5px; }
		</style>
		<div id='wayforpayoptions'>$otp</div>";
    return $output;
}

function getCells()
{

    $wayforpay_lang[get_option('wayforpay_language')] = "selected='selected'";
    return array(
        'wayforpay_merchantAccount' => array(
            "en-US" => array(
                'name' => 'Merchant Account',
                'subText' => 'Your merchant account at WayForPay'
            ),
            "ru-RU" => array(
                'name' => 'Идентификатор продавца',
                'subText' => 'Ваш идентификатор мерчанта в WayForPay'
            ),
            'isInput' => true,
            'code' => ""
        ),
        'wayforpay_secret_key' => array(
            "en-US" => array(
                'name' => 'Secret key',
                'subText' => ''
            ),
            "ru-RU" => array(
                'name' => 'Секретный ключ',
                'subText' => ''
            ),
            'isInput' => true,
            'code' => ""
        ),
        'wayforpay_url' => array(
            "en-US" => array(
                'name' => 'System url',
                'subText' => 'Default url - https://secure.wayforpay.com/pay'
            ),
            "ru-RU" => array(
                'name' => 'Адрес отправки запроса',
                'subText' => 'По умолчанию - https://secure.wayforpay.com/pay'
            ),
            'isInput' => true,
            'code' => ""
        ),
        'wayforpay_returnUrl' => array(
            "en-US" => array(
                'name' => 'Return Url',
                'subText' => ''
            ),
            "ru-RU" => array(
                'name' => 'Обратная ссылка',
                'subText' => 'URL, на который система должна перенаправлять клиента с результатом платежа'
            ),
            'isInput' => true,
            'code' => ""
        ),
        'wayforpay_serviceUrl' => array(
            "en-US" => array(
                'name' => 'Service Url',
                'subText' => ''
            ),
            "ru-RU" => array(
                'name' => 'Service URL',
                'subText' => 'URL, на который система должна отправлять результаты платежа (http://yoursite.com/?wayforpay_callback=true)'
            ),
            'isInput' => true,
            'code' => ""
        ),

        'wayforpay_language' => array(
            "en-US" => array(
                'name' => 'Language of payment page',
                'subText' => ''
            ),
            "ru-RU" => array(
                'name' => 'Язык на странице WayForPay',
                'subText' => ''
            ),
            'isInput' => false,
            'code' => "<select name='wayforpay_language'><option value=''> -- </option>" .
                "<option " . $wayforpay_lang['RU'] . " value='RU'>Russian</option>" .
                "<option " . $wayforpay_lang['UA'] . " value='UA'>Ukrainian</option>" .
                "<option " . $wayforpay_lang['EN'] . " value='EN'>English</option>" .
                "</select>"
        ),
    );
}

add_action('init', 'nzshpcrt_wayforpay_callback');
add_action('init', 'nzshpcrt_wayforpay_results');

?>