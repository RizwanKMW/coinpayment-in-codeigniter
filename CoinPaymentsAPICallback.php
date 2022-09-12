<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class CoinPaymentsAPICallback extends CI_Controller {
	public function __construct()
	{
		parent::__construct();
		
	}
	
	public function db_payment_ipn(){
		
		require_once 'CoinPaymentsAPI_init.php';	
		/*
			$_POST['amount1']="19";
			$_POST['amount2']="0";
			$_POST['currency1']="USD";
			$_POST['currency2']="BTC";
			$_POST['status']="2";
			$_POST['txn_id'] = "CPGB0HCETG65FDARAVDYW";
			*/
		
		$merchant_id = "91471f7aa4b53ed3bfe68d2d8e585cd5";
		$ipn_secret = "jklasdfjoiasdkhfiuwety8r963246jhsadfy";
		$debug_email = "bitsleaders@gmail.com";
		$txn_id = $this->input->post('txn_id');
		if(!$txn_id){ die; }
		$payment = $this->db->get_where('payments', array('gateway_id' => $txn_id, "used"=>"0"));
		$payment = $payment->row();
		$status = intval($_POST['status']);
		$order_currency = $payment->to_currency; //BTC
		$order_total = $payment->amount; //BTC
/*
		if (!isset($_POST['ipn_mode']) || $_POST['ipn_mode'] != 'hmac') {
			$this->edie("IPN Mode is not HMAC");
		}

		if (!isset($_SERVER['HTTP_HMAC']) || empty($_SERVER['HTTP_HMAC'])) {
			$this->edie("No HMAC Signature Sent.");
		}

		$request = file_get_contents('php://input');
		if ($request === false || empty($request)) {
			$this->edie("Error in reading Post Data");
		}

		if (!isset($_POST['merchant']) || $_POST['merchant'] != trim($merchant_id)) {
			$this->edie("No or incorrect merchant id.");
		}

		$hmac =  hash_hmac("sha512", $request, trim($ipn_secret));
		if (!hash_equals($hmac, $_SERVER['HTTP_HMAC'])) {
			$this->edie("HMAC signature does not match.");
		}
		
*/
		$amount1 = floatval($_POST['amount1']); //IN USD
		$amount2 = floatval($_POST['amount2']); //IN BTC
		$currency1 = $_POST['currency1']; //USD
		$currency2 = $_POST['currency2']; //BTC
		$status = intval($_POST['status']);
		$profileDet = $this->mm->getProfileById($payment->user_id);
		$email = $profileDet->email;
		$username = $profileDet->username;
		if ($currency2 != $order_currency) {
			$this->edie("Currency Mismatch");
		}

		if ($amount2 < $order_total) {
			$this->edie("Amount is lesser than order total");
		}
		
		if ($status >= 100 || $status == 2) {
			
			// Payment is complete, set status to success in payment table	
			$this->db->set('status', 'success');
			$this->db->where(array("gateway_id" =>$txn_id));
			$this->db->update('payments');		
			////////////// HANDLE REFERRAL SYSTEM
			
			$this->refferalReward($payment->user_id,$payment->id,"buy");
			//////////////////////// means to renew bot for increase expiry days
			$isRenew = $payment->renewFor;
			if($isRenew=="payment_1" || $isRenew=="payment_3" || $isRenew=="payment_6" || $isRenew=="payment_12"){
			
				$bot_id = $payment->bot_id;
				$bot_username = $this->mm->getBotById($bot_id);
				$bot_username = $bot_username->bot_username;
				
				$renewPeriod = str_replace("payment_","",$payment->renewFor);
				if($renewPeriod==1){ $strMonths="Month"; }else { $strMonths="Months";}
				$this->increaseExpiry($bot_id,$renewPeriod);
				notification("Your Bot Is Renew successfully","Bot is renew for next $renewPeriod $strMonths",$payment->user_id, time(),"GENERAL");
				////////////EMAIL SEND
				$emailTemp = $this->load->view("emailTemplates/email_renew_success.html","",TRUE);
				$emailTemp = str_replace("[BOT_USERNAME]",$bot_username,$emailTemp);
				$emailTemp = str_replace("[MONTHS]",$renewPeriod." ".$strMonths,$emailTemp);
				$emailTemp = str_replace("[DASHBOARD_LINK]",base_url()."dashboard",$emailTemp);
				sendmail($email,"Bot Renewed Successfully",$emailTemp);
				////////////EMAIL SEND
				
				$this->db->set('used', '1');
				$this->db->where(array("gateway_id" =>$txn_id));
				$this->db->update('payments');	
				
			}else{
				//create New Bot
				$timeNow = time();
				$bot_cat = $payment->bot_cat;
				$expiray = 86400*30; //one month
				if($bot_cat==3){
					$expiray = 86400*30*3; //1 year
				}else if($bot_cat==6){
					$expiray = 86400*30*6; //1 year
				}
				$expiray = $expiray+$timeNow;
				///// EXPIRY NOTIFICATION set_error_handler
				notification("Bot Will be expired soon","Your bot will be expire soon check details in manage bot settings",$payment->user_id, time()+$expiray,"BOTEXPIRY");
				
				////////////////
				$botkey = $this->random_string();
				$bot_pass = $this->random_string_gen(6);
				$bot_uname = $this->random_string_gen(6);
				$data = array(
					'user_id' => $payment->user_id,
					'payment_id' => $payment->id,
					'botkey' => $botkey,
					'created' => $timeNow, 
					'expiry' => $expiray,
					'botPassword' => $bot_uname,
					'bot_username' =>$bot_pass,
					'exchange_id' =>1
				);
				$this->db->insert('bot', $data);
				notification("Congratulations, Your Payment Is Approved","We are glad to announce that your bot is Ready, Add payment keys and other stuff, then We will install it soon",$payment->id, time(),"GENERAL");
				
				
				///////////////// Get Bot Id and insert to payment table, so reference is maked, and change used to 1, 
				$BotDetails = $this->db->get_where('bot', array('payment_id' => $payment->id));
				$BotDetails = $BotDetails->row();
				$bot_id = $BotDetails->id;
				////////////EMAIL SEND
				$bot_keys_link="https://bitsleader.com/dashboard/managing_bot/$bot_id";
				$emailTemp = $this->load->view("emailTemplates/email_bot_payment_success.html","",TRUE);
				$emailTemp = str_replace("[NAME]",$username,$emailTemp);
				$emailTemp = str_replace("[BOT_KEYS_LINK]",$bot_keys_link,$emailTemp);
				$emailTemp = str_replace("[APIKEYSGUIDE]","https://www.youtube.com/watch?v=934LwsNIV-0",$emailTemp);
				sendmail($email,"Bot Payment Successful",$emailTemp);
				@sendmail('bitsleaders@gmail.com',"Bot Payment Successful",$username." buy bot for \$".$payment->entered_amount); // send email to admins
				////////////EMAIL SEND
				$this->db->set('used', '1');
				$this->db->set('bot_id', $bot_id);
				$this->db->where(array("gateway_id" =>$txn_id));
				$this->db->update('payments');	
			}
			
			
			
		} else if ($status < 0) {
			// Payment Error
			
			$this->db->set('status', 'error');
			$this->db->where(array("gateway_id" =>$txn_id));
			$this->db->update('payments');
			////////////EMAIL SEND
			$emailTemp = $this->load->view("emailTemplates/email_bot_payment_fail.html","",TRUE);			
			$emailTemp = str_replace("<NAME>",$username,$emailTemp);
			$emailTemp = str_replace("<PAYMENT_LINK>",$payment->gateway_url,$emailTemp);
			sendmail($email,"Bot Payment Failed",$emailTemp);
			////////////EMAIL SEND
		} else {
			// Payment Pending
			////////////EMAIL SEND
			$emailTemp = $this->load->view("emailTemplates/email_bot_payment_pending.html","",TRUE);			
			$emailTemp = str_replace("<NAME>",$username,$emailTemp);
			$emailTemp = str_replace("<PAYMENT_LINK>",$payment->gateway_url,$emailTemp);
			sendmail($email,"Bot Payment Initialized",$emailTemp);
			////////////EMAIL SEND
			
			
			$this->db->set('status', 'pending');
			$this->db->where(array("gateway_id" =>$txn_id));
			$this->db->update('payments');	
		}
		//file_put_contents("test.txt",$this->input->post());
		die("IPN OK");
	}
	public function db_payment_ipn_token(){
		require_once 'CoinPaymentsAPI_init.php';
		
		//////////////////// BYPASS PAYMENT only in testing 
		/*
			$_POST['amount1']="31";
			$_POST['amount2']="0.20000";
			$_POST['currency1']="USD";
			$_POST['currency2']="LTC";
			$_POST['status']="2";
			$_POST['txn_id'] = "CPFL6XGVKQBIDYNMVJXHAE7A1G";
			*/		
		//////////////////// BYPASS PAYMENT
		$merchant_id = "91471f7aa4b53ed3bfe68d2d8e585cd5";
		$ipn_secret = "jklasdfjoiasdkhfiuwety8r963246jhsadfy";
		$debug_email = "bitsleaders@gmail.com";
		$txn_id = $this->input->post('txn_id');
		$payment = $this->db->get_where('payments', array('gateway_id' => $txn_id, "used"=>"0"));
		$payment = $payment->row();
		$profile = $this->mm->getProfileById($payment->user_id);
		$username = $profile->username;
		$status = intval($_POST['status']);
		$order_currency = $payment->to_currency; //BTC
		$order_total = $payment->amount; //BTC
/*
		if (!isset($_POST['ipn_mode']) || $_POST['ipn_mode'] != 'hmac') {
			$this->edie("IPN Mode is not HMAC");
		}

		if (!isset($_SERVER['HTTP_HMAC']) || empty($_SERVER['HTTP_HMAC'])) {
			$this->edie("No HMAC Signature Sent.");
		}

		$request = file_get_contents('php://input');
		if ($request === false || empty($request)) {
			$this->edie("Error in reading Post Data");
		}

		if (!isset($_POST['merchant']) || $_POST['merchant'] != trim($merchant_id)) {
			$this->edie("No or incorrect merchant id.");
		}

		$hmac =  hash_hmac("sha512", $request, trim($ipn_secret));
		if (!hash_equals($hmac, $_SERVER['HTTP_HMAC'])) {
			$this->edie("HMAC signature does not match.");
		}
		
*/
		$amount1 = floatval($_POST['amount1']); //IN USD
		$amount2 = floatval($_POST['amount2']); //IN BTC
		$currency1 = $_POST['currency1']; //USD
		$currency2 = $_POST['currency2']; //BTC
		$status = intval($_POST['status']);
		
		$profileDet = $this->mm->getProfileById($payment->user_id);
		$email = $profileDet->email;
		if ($currency2 != $order_currency) {
			$this->edie("Currency Mismatch");
		}

		if ($amount2 < $order_total) {
			$this->edie("Amount is lesser than order total");
		}
		
		if ($status >= 100 || $status == 2) {
			// calculate tokens 
			$metaPerDollar = $this->mm->getMeta('tokens_per_dollar'); // tokens in one dollar 
			@$metaPerDollar = $metaPerDollar->metavalue;
			$tokens = $metaPerDollar*$amount1;
			
			// Payment is complete, set status to success in payment table	
			$this->db->set('status', 'success');
			$this->db->where(array("gateway_id" =>$txn_id));
			$this->db->update('payments');		
			
			//create New tOKEN Payment

			$data = array(
				'user_id' => $payment->user_id,
				'payment_id' => $payment->id,
				'tokens' => $tokens,
				'datetime' =>date('Y-m-d H:i:s'), 
			);
			$this->db->insert('tokens', $data);
			////////////EMAIL SEND
			$emailTemp = $this->load->view("emailTemplates/email_ico_payment_success.html","",TRUE);
			$emailTemp = str_replace("[NAME]",$username,$emailTemp);
			sendmail($email,"\$BTL Payment Successful",$emailTemp);
			////////////EMAIL SEND
			
		} else if ($status < 0) {
			// Payment Error
			$this->db->set('status', 'error');
			$this->db->where(array("gateway_id" =>$txn_id));
			$this->db->update('payments');
			
			////////////EMAIL SEND
			$emailTemp = $this->load->view("emailTemplates/email_ico_payment_fail.html","",TRUE);
			$emailTemp = str_replace("[NAME]",$username,$emailTemp);
			$emailTemp = str_replace("<PAYMENT_LINK>",$payment->gateway_url,$emailTemp);
			sendmail($email,"\$BTL Payment Failed",$emailTemp);
			////////////EMAIL SEND
			
		} else {
			// Payment Pending
			$this->db->set('status', 'pending');
			$this->db->where(array("gateway_id" =>$txn_id));
			$this->db->update('payments');	
		}
		//file_put_contents("test.txt",$this->input->post());
		die("IPN OK");
	}
		public function edie($error_msg)
		{
			global $debug_email;
			$report =  "ERROR : " . $error_msg . "\n\n";
			$report.= "POST DATA\n\n";
			foreach ($_POST as $key => $value) {
				$report .= "|$key| = |$value| \n";
			}
			//mail($debug_email, "Payment Error", $report);
			die($error_msg);
		}
		
		public function random_string() {
			$s1= $this->random_string_gen(20);
			$s2= $this->random_string_gen(20);
			$s=$s1."".$s2;
			return $s;
		}
		
		public function random_string_gen($length) {
			$key = '';
			$keys = array_merge(range(0, 9), range('a', 'z'), range('A', 'Z'));

			for ($i = 0; $i < $length; $i++) {
				$key .= $keys[array_rand($keys)];
			}
			return $key;
		}
		
		public function increaseExpiry($botId, $renewPeriod){
			//echo $botId." ".$renewPeriod;
			$botDetails = $this->mm->getBot($botId);
			$previoud_expiray = $botDetails->expiry;
			$expiray = 86400*30; //one month
			if($renewPeriod==3){
				$expiray = 86400*30*3; //3 months
			}else if($renewPeriod==6){
				$expiray = 86400*30*6; //6 months
			}
			$expiray = $expiray+$previoud_expiray;
			///////////////// update expiry date in notifications
			
			/////////////////////////////////
			$this->db->set('expiry', $expiray);
			$this->db->where(array("id" =>$botId));
			$this->db->update('bot');
		}
		public function refferalReward($user_id, $payment_id, $renewOrBuy="buy"){

				//1. GET user id by $payment->user_id
				//2. GET referralBY from users where id =$payment->user_id
				// 3. if(referralBY not empty){
				// 4. payment_id, user_id, reffered_id, 20%, status(withdraw), datetime INSERT TO refferalPayments
				// 5. withdraw (pending)
			$amount1 = $_POST['amount1']; //USD
			$profileDet = $this->mm->getProfileById($user_id);
			$reffered_by_username = $profileDet->referralBY;
			$profileDetRefferedBy = $this->mm->getProfileByUsername($reffered_by_username);
			$profileDetRefferedBy_id=null;
			if($profileDetRefferedBy){
				$profileDetRefferedBy_id = $profileDetRefferedBy->id;
			}
			if($reffered_by_username && $profileDetRefferedBy_id!=null){
				$rewardis = ($amount1)*(20/100);
				$data = array(
					'payment_id' => $payment_id,
					'user_id' => $user_id,
					'reffered_by_id' => $profileDetRefferedBy_id,
					'reward' => $rewardis,
					'status' => 'unpaid'
				);
				$this->db->set('datetime', 'NOW()', FALSE);
				$this->db->insert('referral_payments', $data);
		  }
		}

		

}
