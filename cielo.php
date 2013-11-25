<?php

defined('_JEXEC') or die('Restricted access');

if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVmPaymentCielo extends vmPSPlugin {

    // instance of class
    public static $_this = false;
    private $status = array(
        0 => 'Transação criada',
        1 => 'Transação em Andamento',
        2 => 'Transação Autenticada',
        3 => 'Transação não Autenticada',
        4 => 'Transação Autorizada',
        5 => 'Transação não Autorizada',
        6 => 'Transação Capturada',
        9 => 'Transação Cancelada',
        10 => 'Transação em Autenticação',
        12 => 'Transação em Cancelamento'
    );
    
    private $lr = array(
        '00' => 'Transação autorizada',
        '01' => 'Transação referida pelo emissor',
        '04' => 'Cartão com restrição',
        '05' => 'Transação não autorizada',
        '06' => 'Tente novamente',
        '07' => 'Cartão com restrição',
        '12' => 'Transação inválida',
        '13' => 'Valor inválido (Verifique valor mínimo de R$5,00 para parcelamento)',
        '14' => 'Cartão inválido',
        '15' => 'Emissor inválido',
        '41' => 'Cartão com restrição',
        '51' => 'Saldo insuficiente',
        '54' => 'Cartão vencido',
        '57' => 'Transação não permitida',
        '58' => 'Transação não permitida',
        '62' => 'Cartão com restrição',
        '63' => 'Cartão com restrição',
        '76' => 'Tente novamente',
        '78' => 'Cartão não foi desbloqueado pelo portador',
        '82' => 'Transação inválida',
        '91' => 'Banco indisponível',
        '96' => 'Tente novamente',
        'AA' => 'Tente novamente',
        'AC' => 'Cartão de débito tentando utilizar produto crédito',
        'GA' => 'Transação referida pela Cielo (Aguarde contato da Cielo)',
        'N7' => 'Código de segurança inválido (Visa)'
        
    );

    function __construct(& $subject, $config) {
        //if (self::$_this)
        //   return self::$_this;
        parent::__construct($subject, $config);

        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $varsToPush = array('payment_logos' => array('', 'char'),
            'NomeLoja' => array('', 'string'),
            'Codigo' => array('', 'string'),
            'Chave' => array('', 'string'),
            'TipoParcelamento' => array('', 'char'),
            'Parcelas' => array('', 'int'),
            'TipoParcelamento' => array('', 'char'),
            'Captura' => array('', 'int'),
            'Autorizar' => array('', 'int'),
            'StatusAutorizado' => array('', 'char'),
            'StatusNaoAutorizado' => array('', 'char'),
            'Ambiente' => array('', 'int')
        );

        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

        $editor = JFactory::getEditor();
        if ($_GET["task"] == "edit" && $_GET["view"] == "paymentmethod" && $_GET["controller"] == "paymentmethod") {
            $document = JFactory::getDocument();
            $document->addScript(JURI::root() . "media/editors/tinymce/jscripts/tiny_mce/tiny_mce.js");
            $document->addScriptDeclaration('tinyMCE.init({directionality: "ltr",editor_selector : "tinymce",language : "en",mode : "specific_textareas",skin : "default",theme : "advanced",inline_styles : true,gecko_spellcheck : true,entity_encoding : "raw",extended_valid_elements : "hr[id|title|alt|class|width|size|noshade|style],img[class|src|border=0|alt|title|hspace|vspace|width|height|align|onmouseover|onmouseout|name|style],a[id|class|name|href|target|title|onclick|rel|style]",force_br_newlines : false, force_p_newlines : true, forced_root_block : "p",invalid_elements : "script,applet,iframe",relative_urls : true,remove_script_host : false,document_base_url : "' . JURI::root() . '",content_css : "' . JURI::root() . 'templates/system/css/editor.css",theme_advanced_toolbar_location : "top",theme_advanced_toolbar_align : "left",theme_advanced_source_editor_height : "550",theme_advanced_source_editor_width : "750",theme_advanced_resizing : true,theme_advanced_resize_horizontal : false,theme_advanced_statusbar_location : "bottom", theme_advanced_path : true});');
        }

        // self::$_this = $this;
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * @author Valérie Isaksen
     */
    protected function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment Cielo Table');
    }

    /**
     * Fields to create the payment table
     * @return string SQL Fileds
     */
    function getTableSQLFields() {
        $SQLfields = array(
            'id' => 'tinyint(1) unsigned NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(11) UNSIGNED DEFAULT NULL',
            'order_number' => 'char(32) DEFAULT NULL',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL',
            'payment_name' => 'char(255) NOT NULL DEFAULT \'\' ',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
            'payment_currency' => 'char(3) ',
            'tid' => ' VARCHAR(100) ',
            'status' => 'INT(2) DEFAULT NULL',
            'inicio' => ' TIMESTAMP DEFAULT CURRENT_TIMESTAMP ',
            'ped_moeda' => 'smallint(11) DEFAULT NULL',
            'ped_valor' => 'DOUBLE DEFAULT NULL',
            'ped_data_hora' => 'VARCHAR(100) DEFAULT NULL',
            'pag_bandeira' => 'VARCHAR(20) DEFAULT NULL',
            'pag_produto' => 'VARCHAR(20) DEFAULT NULL',
            'pag_parcelas' => 'INT(11) DEFAULT NULL',
            'aute_codigo' => 'INT(2) DEFAULT NULL',
            'aute_mensagem' => 'VARCHAR(100) DEFAULT NULL',
            'aute_data_hora' => 'VARCHAR(20) DEFAULT NULL',
            'aute_valor' => 'INT(12) DEFAULT NULL',
            'aute_eci' => 'INT(2) DEFAULT NULL',
            'auto_codigo' => 'INT(2) DEFAULT NULL',
            'auto_mensagem' => 'VARCHAR(100) DEFAULT NULL',
            'auto_data_hora' => 'VARCHAR(20) DEFAULT NULL',
            'auto_valor' => 'INT(12) DEFAULT NULL',
            'auto_lr' => 'INT(2) DEFAULT NULL',
            'auto_arp' => 'INT(6) DEFAULT NULL',
            'auto_nsu' => 'INT(6) DEFAULT NULL',
            'auto_codigo' => 'INT(2) DEFAULT NULL',
            'capt_mensagem' => 'VARCHAR(100) DEFAULT NULL',
            'capt_data_hora' => 'VARCHAR(20) DEFAULT NULL',
            'capt_valor' => 'INT(12) DEFAULT NULL',
            'canc_codigo' => 'INT(2) DEFAULT NULL',
            'canc_mensagem' => 'VARCHAR(100) DEFAULT NULL',
            'canc_data_hora' => 'VARCHAR(20) DEFAULT NULL',
            'canc_valor' => 'INT(12) DEFAULT NULL',
            'pan' => 'VARCHAR(40) DEFAULT NULL',
            'url_autenticacao' => 'VARCHAR(250) DEFAULT NULL'
        );

        return $SQLfields;
    }

    function getPluginParams() {
        $db = JFactory::getDbo();
        $sql = "select virtuemart_paymentmethod_id from #__virtuemart_paymentmethods where payment_element = 'cielo'";
        $db->setQuery($sql);
        $id = (int) $db->loadResult();
        return $this->getVmPluginMethod($id);
    }

    /**
     *
     *
     * @author Valérie Isaksen
     */
    function plgVmConfirmedOrder($cart, $order) {

        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
// 		$params = new JParameter($payment->payment_params);
        $lang = JFactory::getLanguage();
        $filename = 'com_virtuemart';
        $lang->load($filename, JPATH_ADMINISTRATOR);
        $vendorId = 0;

        $html = "";

        if (!class_exists('VirtueMartModelOrders'))
            require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
        $this->getPaymentCurrency($method);
        // END printing out HTML Form code (Payment Extra Info)
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
        $db = &JFactory::getDBO();
        $db->setQuery($q);
        $currency_code_3 = $db->loadResult();
        $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
        $totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
        $cd = CurrencyDisplay::getInstance($cart->pricesCurrency);


        $this->_virtuemart_paymentmethod_id = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['payment_name'] = $this->renderPluginName($method);
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;
        $dbValues['cost_per_transaction'] = $method->cost_per_transaction;
        $dbValues['cost_percent_total'] = $method->cost_percent_total;
        $dbValues['payment_currency'] = $currency_code_3;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency;
        $dbValues['tax_id'] = $method->tax_id;
        $this->storePSPluginInternalData($dbValues);

        $html = '<table>' . "\n";
        $html .= $this->getHtmlRow('STANDARD_PAYMENT_INFO', $dbValues['payment_name']);
        if (!empty($payment_info)) {
            $lang = & JFactory::getLanguage();
            if ($lang->hasKey($method->payment_info)) {
                $payment_info = JTExt::_($method->payment_info);
            } else {
                $payment_info = $method->payment_info;
            }
            $html .= $this->getHtmlRow('STANDARD_PAYMENTINFO', $payment_info);
        }
        if (!class_exists('VirtueMartModelCurrency'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
        $currency = CurrencyDisplay::getInstance('', $order['details']['BT']->virtuemart_vendor_id);
        $html .= $this->getHtmlRow('STANDARD_ORDER_NUMBER', $order['details']['BT']->order_number);
        $html .= $this->getHtmlRow('STANDARD_AMOUNT', $currency->priceDisplay($order['details']['BT']->order_total));
        //$html .= $this->getHtmlRow('STANDARD_AMOUNT', $totalInPaymentCurrency.' '.$currency_code_3);
        $html .= '</table>' . "\n";



        $urlAutenticacao = $this->getUrlAutenticacao($order, $cart->useSSL);

        $html .= "Você será direcionado para o ambiente seguro da CIELO, caso seu navegador bloqueie a janela ";
        $html .="<a href='{$urlAutenticacao}' target='_blank'>Clique aqui</a>";
        $html .= "<script>window.setTimeout(function(){location.href='{$urlAutenticacao}'},5000)</script>";


        //JFactory::getApplication()->enqueueMessage(utf8_encode("Seu pedido foi realizado com sucesso. Voc\EA ser\E1 direcionado para o site pagseguro, onde efetuar\E1 o pagamento da sua compra."));

        return $this->processConfirmedOrderPaymentResponse(2, $cart, $order, $html);
// 		return true;  // empty cart, send order
    }

    private function getUrlAutenticacao($order, $useSSL = false) {
        $method = $this->getPluginParams();
        $session = JFactory::getSession();
        $_cielo = $session->get('cielo', new stdClass(), 'vm');

        $numero_pedido = $order['details']['BT']->order_number;
        $parcelas = $_cielo->parcelas;
        $bandeira = $_cielo->bandeira;
        $valor_pedido = number_format($order['details']['BT']->order_total, 2, "", "");
        //$valor_pedido = number_format(1, 2, "", "");
        $data_hora = date("c");
        $forma_produto = $parcelas == 1 ? 1 : $method->TipoParcelamento;
        if ($parcelas > 1 && $forma_produto == 1) { // forma errada, assumindo parcelado loja
            $forma_produto = 2;
        }
        $autorizar = $method->Autorizar;
        $capturar = $method->Captura == 1 ? "true" : "false"; // string mesmo
        $id_pedido = str_pad($order['details']['BT']->order_number, 8, 0, STR_PAD_LEFT);
        $url_retorno = ($useSSL ? "https://" : "http://") .
                $_SERVER['HTTP_HOST'] .
                JRoute::_('index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&order=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id, true, $useSSL);
        $numero = $method->Codigo;
        $chave = $method->Chave;


        $xml = "<?xml version='1.0' encoding='ISO-8859-1'?>
<requisicao-transacao id='1' versao='1.1.1'>
	<dados-ec>
		<numero>$numero</numero>
		<chave>$chave</chave>
	</dados-ec>
	<dados-pedido>
		<numero>$id_pedido</numero>
		<valor>$valor_pedido</valor>
		<moeda>986</moeda>
		<data-hora>$data_hora</data-hora>
		<idioma>PT</idioma>
	</dados-pedido>
	<forma-pagamento>
		<bandeira>$bandeira</bandeira>
		<produto>$forma_produto</produto>
		<parcelas>$parcelas</parcelas>
	</forma-pagamento>
	<url-retorno>$url_retorno</url-retorno>
	<autorizar>$autorizar</autorizar>
	<capturar>$capturar</capturar>
</requisicao-transacao>";

        $xml_array = $this->curl_xml($xml);
        $data = array(
            'tid' => $xml_array['tid'],
            'ped_numero' => $xml_array["dados-pedido"]["numero"],
            'ped_valor' => $xml_array["dados-pedido"]["valor"],
            'ped_moeda' => $xml_array["dados-pedido"]["meoda"],
            'ped_data_hora' => $xml_array["dados-pedido"]["data-hora"],
            'pag_bandeira' => $xml_array["forma-pagamento"]["bandeira"],
            'pag_produto' => $xml_array["forma-pagamento"]["produto"],
            'pag_parcelas' => $xml_array["forma-pagamento"]["parcelas"],
            'status' => $xml_array["status"],
            'url_autenticacao' => $xml_array["url-autenticacao"],
        );
        $this->storePSPluginInternalData($data);

        return $xml_array["url-autenticacao"];
    }

    /**
     * Display stored payment data for an order
     *
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id) {
        if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
            return null; // Another method was selected, do nothing
        }

        $db = JFactory::getDBO();
        $q = 'SELECT * FROM `' . $this->_tablename . '` '
                . 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
        $db->setQuery($q);
        if (!($paymentTable = $db->loadObject())) {
            vmWarn(500, $q . " " . $db->getErrorMsg());
            return '';
        }
        $this->getPaymentCurrency($paymentTable);

        $params = $this->getPluginParams();
        $tid = $paymentTable->tid;
        $xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>
<requisicao-consulta id=\"5\" versao=\"1.1.1\">
<tid>{$tid}</tid>
<dados-ec>
<numero>{$params->Codigo}</numero>
<chave>{$params->Chave}</chave>
</dados-ec>
</requisicao-consulta>";
        $info = $this->curl_xml($xml);
        $html = '<table class="adminlist">' . "\n";
        $html .=$this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('STANDARD_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('STANDARD_PAYMENT_TOTAL_CURRENCY', 'R$ ' . number_format($paymentTable->payment_order_total, 2, ",", "."));
        $html .= "<tr><td>Status do pagamento:</td><td> <b> " . $this->status[$info['status']] . "</b></td></tr>";
        $html .= "<tr><td>ID da transação:</td><td> <b> " . $tid . "</b></td></tr>";
        if (isset($info["pan"])) {
            $html .= "<tr><td class='key'>PAN:</td><td> <b> " . $info["pan"] . "</b></td></tr>";
        }
        if (isset($info["forma-pagamento"])) {
            $html .= "<tr><td colspan='2' class='key'><h4>Forma de pagamento</h4><hr/></td></tr>";
            $html .= "<tr><td class='key'>Bandeira:</td><td> <b> " . ucfirst($info["forma-pagamento"]["bandeira"]) . "</b></td></tr>";
            $html .= "<tr><td class='key'>Parcelas:</td><td> <b> " . $info["forma-pagamento"]["parcelas"] . "</b></td></tr>";
        }
        if (isset($info["autenticacao"])) {
            $html .= "<tr><td colspan='2' class='key'><h4>Autenticação</h4><hr/></td></tr>";
            $html .= "<tr><td class='key'>Código:</td><td> <b> " . $info["autenticacao"]["codigo"] . "</b></td></tr>";
            $html .= "<tr><td class='key'>Mensagem:</td><td> <b> " . $info["autenticacao"]["mensagem"] . "</b></td></tr>";
            $html .= "<tr><td class='key'>Horário:</td><td> <b> " . date("d/m/Y H:i:s", strtotime($info["autenticacao"]["data-hora"])) . "</b></td></tr>";
            $html .= "<tr><td class='key'>Valor:</td><td> <b> " . $info["autenticacao"]["valor"] . "</b></td></tr>";
            $html .= "<tr><td class='key'>ECI:</td><td> <b> " . $info["autenticacao"]["eci"] . "</b></td></tr>";
        }
        if (isset($info["autorizacao"])) {
            $html .= "<tr><td colspan='2' class='key'><h4>Autorização</h4><hr/></td></tr>";
            $html .= "<tr><td class='key'>Código:</td><td> <b> " . $info["autorizacao"]["codigo"] . "</b></td></tr>";
            $html .= "<tr><td class='key'>Mensagem:</td><td> <b> " . $info["autorizacao"]["mensagem"] . "</b></td></tr>";
            $html .= "<tr><td class='key'>Horário:</td><td> <b> " . date("d/m/Y H:i:s", strtotime($info["autorizacao"]["data-hora"])) . "</b></td></tr>";
            $html .= "<tr><td class='key'>Valor:</td><td> <b> " . $info["autorizacao"]["valor"] . "</b></td></tr>";
            $html .= "<tr><td class='key'>LR:</td><td> <b> " . $info["autorizacao"]["lr"] . " - " . $this->lr[$info["autorizacao"]["lr"]] . "</b></td></tr>";
            $html .= "<tr><td class='key'>ARP:</td><td> <b> " . $info["autorizacao"]["arp"] . "</b></td></tr>";
            $html .= "<tr><td class='key'>NSU:</td><td> <b> " . $info["autorizacao"]["nsu"] . "</b></td></tr>";
        }
        if (isset($info["captura"])) {
            $html .= "<tr><td colspan='2' class='key'><h4>Captura</h4><hr/></td></tr>";
            $html .= "<tr><td class='key'>Código:</td><td> <b> " . $info["captura"]["codigo"] . "</b></td></tr>";
            $html .= "<tr><td class='key'>Mensagem:</td><td> <b> " . $info["captura"]["mensagem"] . "</b></td></tr>";
            $html .= "<tr><td class='key'>Horário:</td><td> <b> " . date("d/m/Y H:i:s", strtotime($info["captura"]["data-hora"])) . "</b></td></tr>";
            $html .= "<tr><td class='key'>Valor:</td><td> <b> " . $info["captura"]["valor"] . "</b></td></tr>";
        }
        if (isset($info["cancelamento"])) {
            $html .= "<tr><td colspan='2' class='key'><h4>Cancelamento</h4><hr/></td></tr>";
            $html .= "<tr><td class='key'>Código:</td><td> <b> " . $info["cancelamento"]["codigo"] . "</b></td></tr>";
            $html .= "<tr><td class='key'>Mensagem:</td><td> <b> " . $info["cancelamento"]["mensagem"] . "</b></td></tr>";
            $html .= "<tr><td class='key'>Horário:</td><td> <b> " . date("d/m/Y H:i:s", strtotime($info["cancelamento"]["data-hora"])) . "</b></td></tr>";
            $html .= "<tr><td class='key'>Valor:</td><td> <b> " . $info["cancelamento"]["valor"] . "</b></td></tr>";
        }
        $html .= '</table>' . "\n";
        return $html;
    }

    function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
        if (preg_match('/%$/', $method->cost_percent_total)) {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        } else {
            $cost_percent_total = $method->cost_percent_total;
        }
        return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     * @author: Valerie Isaksen
     *
     * @param $cart_prices: cart prices
     * @param $payment
     * @return true: if the conditions are fulfilled, false otherwise
     *
     */
    protected function checkConditions($cart, $method, $cart_prices) {

// 		$params = new JParameter($payment->payment_params);
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $amount = $cart_prices['salesPrice'];
        $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount OR
                ($method->min_amount <= $amount AND ($method->max_amount == 0) ));
        if (!$amount_cond) {
            return false;
        }
        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        // probably did not gave his BT:ST address
        if (!is_array($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id']))
            $address['virtuemart_country_id'] = 0;
        if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            return true;
        }

        return false;
    }

    /*
     * We must reimplement this triggers for joomla 1.7
     */

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     * @author Valérie Isaksen
     *
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @author Max Milbers
     * @author Valérie isaksen
     *
     * @param VirtueMartCart $cart: the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     *
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) {
        return $this->OnSelectCheck($cart);
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param object $cart Cart object
     * @param integer $selected ID of the method selected
     * @return boolean True on succes, false on failures, null when this plugin was not selected.
     * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     *
     * @author Valerie Isaksen
     * @author Max Milbers
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    public function displayListFE(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
        parent::displayListFE($cart, $selected, $htmlIn);

        $session = JFactory::getSession();
        $_cielo = $session->get('cielo', new stdClass(), 'vm');
        $method = $this->getPluginParams();
        $selectParcelas = '<select name="parcelas">';
        for ($i = 1; $i <= $method->Parcelas; $i++) {
            if ($_cielo && $_cielo->parcelas && $_cielo->parcelas == $i) {
                $selectParcelas .= '<option selected value="' . $i . '">' . $i . '</option>';
            } else {
                $selectParcelas .= '<option value="' . $i . '">' . $i . '</option>';
            }
        }
        $selectParcelas .= '</select>';

        $selectBandeira = '<select name="bandeira">';
        $bandeiras = array(
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'diners' => 'Diners',
            'discover' => 'Discover',
            'elo' => 'Elo'
        );
        foreach ($bandeiras as $bandeira => $label) {
            if ($_cielo && $_cielo->bandeira && $_cielo->bandeira == $bandeira) {
                $selectBandeira .= '<option selected value="' . $bandeira . '">' . $label . '</option>';
            } else {
                $selectBandeira .= '<option value="' . $bandeira . '">' . $label . '</option>';
            }
        }

        $selectBandeira .= '</select>';
        $formCielo =
                '<table><tr><td>' .
                $htmlIn[count($htmlIn) - 1][0] .
                '
           </td><td>
            <label>
                Selecione a bandeira de seu cartão:<br/>
                ' . $selectBandeira . '
            </label>
        </td><td>
            <label>
                Selecione a quantidade de parcelas:<br/>
                ' . $selectParcelas . '
            </label>
            </td>
          </tr>
        </table>
        '
        ;

        //buscar último método de pagamento (Cielo)
        $htmlIn[count($htmlIn) - 1][0] = "<fieldset>" . $formCielo . "</fieldset>";
    }

    /*
     * plgVmonSelectedCalculatePricePayment
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
     * @author Valerie Isaksen
     * @cart: VirtueMartCart the current cart
     * @cart_prices: array the new cart prices
     * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
     *
     *
     */

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     * @author Valerie Isaksen
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     *
     */
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array()) {
        $session = JFactory::getSession();
        $_cielo = $session->get('cielo', 0, 'vm');
        if ($_cielo == 0) {
            $app = JFactory::getApplication();
            return $app->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart&task=editpayment', true, $cart->useSSL), "Selecione a forma de pagamento");
        }
        return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     * @author Max Milbers
     * @author Valerie Isaksen
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * This event is fired during the checkout process. It can be used to validate the
     * method data as entered by the user.
     *
     * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
     * @author Max Milbers

      public function plgVmOnCheckoutCheckDataPayment(  VirtueMartCart $cart) {
      return null;
      }
     */

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id  method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    function plgVmonShowOrderPrintPayment($order_number, $method_id) {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPayment($name, $id, &$data) {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    public function onSelectCheck(VirtueMartCart $cart) {

        $session = JFactory::getSession();
        $_cielo = new stdClass();
        $_cielo->bandeira = JRequest::getString('bandeira', 'visa');
        $_cielo->parcelas = JRequest::getInt('parcelas', 1);
        $session->set('cielo', $_cielo, 'vm');

        parent::onSelectCheck($cart);
    }

    //Notice: We only need to add the events, which should work for the specific plugin, when an event is doing nothing, it should not be added

    /**
     * Save updated order data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.
     * @author Oscar van Eijk
     *
      public function plgVmOnUpdateOrderPayment(  $_formData) {
      return null;
      }

      /**
     * Save updated orderline data to the method specific table
     *
     * @param array $_formData Form data
     * @return mixed, True on success, false on failures (the rest of the save-process will be
     * skipped!), or null when this method is not actived.
     * @author Oscar van Eijk
     *
      public function plgVmOnUpdateOrderLine(  $_formData) {
      return null;
      }

      /**
     * plgVmOnEditOrderLineBE
     * This method is fired when editing the order line details in the backend.
     * It can be used to add line specific package codes
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise
     * @author Oscar van Eijk
     *
      public function plgVmOnEditOrderLineBEPayment(  $_orderId, $_lineId) {
      return null;
      }

      /**
     * This method is fired when showing the order details in the frontend, for every orderline.
     * It can be used to display line specific package codes, e.g. with a link to external tracking and
     * tracing systems
     *
     * @param integer $_orderId The order ID
     * @param integer $_lineId
     * @return mixed Null for method that aren't active, text (HTML) otherwise
     * @author Oscar van Eijk
     *
      public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
      return null;
      }

      /**
     * This event is fired when the  method notifies you when an event occurs that affects the order.
     * Typically,  the events  represents for payment authorizations, Fraud Management Filter actions and other actions,
     * such as refunds, disputes, and chargebacks.
     *
     * NOTE for Plugin developers:
     *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
     *
     * @param $return_context: it was given and sent in the payment form. The notification should return it back.
     * Used to know which cart should be emptied, in case it is still in the session.
     * @param int $virtuemart_order_id : payment  order id
     * @param char $new_status : new_status for this order id.
     * @return mixed Null when this method was not selected, otherwise the true or false
     *
     * @author Valerie Isaksen
     *
     *
     */
    function plgVmOnPaymentNotification() {
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        $order_number = JRequest::getString('order', '');
        $virtuemart_paymentmethod_id = JRequest::getInt('pm', '');
        $modelOrder = VmModel::getModel('orders');
        $document = JFactory::getDocument();

        if (empty($order_number) or empty($virtuemart_paymentmethod_id) or !$this->selectedThisByMethodId($virtuemart_paymentmethod_id)) {
            return NULL;
        }

        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            return NULL;
        }
        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return NULL;
        }

        if(JRequest::getBool('restart')){
            $order = $modelOrder->getOrder($virtuemart_order_id);
            $link = $this->getUrlAutenticacao($order);
            $document->addScriptDeclaration("location.href='{$link}'");
            return false;
        }


        $method = $this->getPluginParams();

        //consultar situação do pedido
        $tid = $paymentTable->tid;

        $xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>
<requisicao-consulta id=\"5\" versao=\"1.1.1\">
<tid>$tid</tid>
<dados-ec>
<numero>" . $method->Codigo . "</numero>
<chave>" . $method->Chave . "</chave>
</dados-ec>
</requisicao-consulta>";
        $xml = $this->curl_xml($xml);
        $sucesso = false;
        $mensagem = "";

        $order = array();

        $retryLink = JRoute::_("index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&order={$order_number}&pm={$virtuemart_paymentmethod_id}&restart=1", true, $cart->useSSL);

        //@TODO Atualizar o status da requisição da CIELO e persistir no banco de dados (internalData)
        switch ($xml['status']) {
            case 3:
            case 5:
            case 8:
                $mensagem = "Transação não autorizada. Clique <a href='{$retryLink}'>aqui</a> para tentar novamente";
                $order['customer_notified'] = 1;
                break;
            case 9:
                $mensagem = "Transação cancelada pelo usuário. Clique <a href='{$retryLink}'>aqui</a> para tentar novamente";
                break;
            case 0:
            case 1:
            case 2:
            case 4:
            case 10:
                $mensagem = "A transação está em andamento, tentaremos uma nova conexão em 60 segundos. Aguarde ou clique <a href='{$retryLink}'>aqui</a> para tentar novamente";
                $document->addScriptDeclaration("window.setTimeout(function(){location.reload()}, 60000);");
                break;
            case 6: //aprovado
                $mensagem = "Transação aprovada com sucesso";
                $order['customer_notified'] = 1;
                $sucesso = true;
                break;
        }

        if ($sucesso) {
            $order['order_status'] = $method->StatusAutorizado;
        } else {
            $order['order_status'] = $method->StatusNaoAutorizado;
        }

        $order['comments'] = $mensagem;
        $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, TRUE);

        echo $mensagem;

        return $sucesso;
    }

    /**
     * plgVmOnPaymentResponseReceived
     * This event is fired when the  method returns to the shop after the transaction
     *
     *  the method itself should send in the URL the parameters needed
     * NOTE for Plugin developers:
     *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
     *
     * @param int $virtuemart_order_id : should return the virtuemart_order_id
     * @param text $html: the html to display
     * @return mixed Null when this method was not selected, otherwise the true or false
     *
     * @author Valerie Isaksen
     *
     *
      function plgVmOnPaymentResponseReceived(, &$virtuemart_order_id, &$html) {
      return null;
      }
     */
    protected function renderPluginName($plugin) {

        $return = '';
        $plugin_name = $this->_psType . '_name';
        $plugin_desc = $this->_psType . '_desc';
        $description = '';
        // 		$params = new JParameter($plugin->$plugin_params);
        // 		$logo = $params->get($this->_psType . '_logos');
        $logosFieldName = $this->_psType . '_logos';
        $logos = $plugin->$logosFieldName;
        if (!empty($logos)) {
            $return = $this->displayLogos($logos) . ' ';
        }
        if (!empty($plugin->$plugin_desc)) {
            $description = '<span class="' . $this->_type . '_description">' . $plugin->$plugin_desc . '</span>';
        }
        if (empty($return)) {
            $pluginName = '<span class="' . $this->_type . '_name">' . $plugin->$plugin_name . '</span>' . $description;
        } else {
            $pluginName = $return . $description;
        }
        return $pluginName;
    }

    private function curl_xml($xml) {

        $method = $this->getPluginParams();

        $curl = curl_init();

        $url_cielo = $method->Ambiente == "0" ?
                "https://qasecommerce.cielo.com.br/servicos/ecommwsec.do" :
                "https://ecommerce.cbmp.com.br/servicos/ecommwsec.do";

        if (is_resource($curl)) {
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            //curl_setopt( $curl , CURLOPT_FOLLOWLOCATION , 1 );
            curl_setopt($curl, CURLOPT_URL, $url_cielo);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query(array('mensagem' => $xml)));

            $xml = curl_exec($curl);
            $ern = curl_errno($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ((bool) $ern) {
                echo 'Opz, ocorreu um erro[', $ern, ']: ', $err;
                return false;
            } else {

                $DadosEnvio = simplexml_load_string($xml);
                $xml_array = json_decode(json_encode($DadosEnvio), 1);
                if (!empty($xml_array["codigo"]) && !empty($xml_array["mensagem"]))
                    throw new Exception("Atenção<br/>({$xml_array["codigo"]}){$xml_array["mensagem"]}\"<a onclick='history.back();'>Voltar</a>");

                return $xml_array;
            }
        } else {
            echo 'Opz, não foi possível criar o recurso da cURL';
            return false;
        }
    }

}

// No closing tag