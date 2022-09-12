1. add library
composer require coinpaymentsnet/coinpayments-php

2. paste CoinPaymentsAPI.php,CoinPaymentsAPI_init.php, CoinPaymentsAPICallback.php to the controller folder

3. register here https://www.coinpayments.net/register and generate api keys

4. put keys in CoinPaymentsAPI_init.php, edit permission and check all required permissions

5. dump this to db
CREATE TABLE `payments` (
  `id` int(255) NOT NULL,
  `file_id` varchar(255) NOT NULL,
  `from_currency` varchar(500) NOT NULL,
  `to_currency` varchar(500) NOT NULL,
  `entered_amount` varchar(1000) NOT NULL,
  `amount` varchar(50) NOT NULL,
  `gateway_id` varchar(1000) NOT NULL,
  `gateway_url` text NOT NULL,
  `status` varchar(500) NOT NULL,
  `email` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `payments`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

6. Add to constructor
	public function __construct (){
	    $this->db = db_connect();
	}


7. make payment code in controller

public function cryp_payment_init(){ 

	require_once 'CoinPaymentsAPI_init.php';
	$basicInfo = $coin->GetBasicProfile();
	$username = $basicInfo['result']['public_name'];
	$amount = 5;
	$buyer_email = 'icpusd@gmail.com';
	$scurrency = "USD";
	$rcurrency = "BTC";
	$request = [
	    'amount' => $amount,
	    'currency1' => $scurrency,
	    'currency2' => $rcurrency,
	    'buyer_email' => $buyer_email,
	    'item' => "Course payment",
	    'address' => "",
	    'ipn_url' => base_url()."CoinPaymentsAPICallback_IPN",
	];
	$result = $coin->CreateTransaction($request); //to coinpayments api server
	if ($result['error'] == "ok") {
	    //save to payments as initialized
	    $file_id=1;
	    $data= array(
	        'file_id' => $file_id,
	        'from_currency' => $scurrency,
	        'to_currency' => $rcurrency,
	        'entered_amount' => $amount,
	        'amount' => $result['result']['amount'],
	        'gateway_id' => $result['result']['txn_id'],
	        'gateway_url' => $result['result']['status_url'],
	        'status' => "initialized",
	        'email' => $buyer_email,
	    );
	    //print_r($data);die;

	    $this->db->table('payments')->insert($data);
	    $paymentURL = $result['result']['status_url'];
	   return redirect()->to($paymentURL);
	    
	} else {
	    print 'Error: ' . $result['error'] . "\n";
	    die();
	}
}


8. Goto coinpayments >account>account setting> mechant tab and get ipn_secret
also get merchant id from basic settings 
9. update callback function code

public function db_payment_ipn(){
		require_once 'CoinPaymentsAPI_init.php';	
		$merchant_id = "5056a474b3bc85ddde665570a71e2678";
		$ipn_secret = "rizikmw123456";
		$debug_email = "icpusd@gmail.com";
		$txn_id = $this->request->getPost('txn_id');
		if(!$txn_id){ die("TX Empty"); }
		$builder = $this->db->table('payments');
		$payment = $builder->getWhere(['gateway_id' => $txn_id]);
		$payment = $payment->getRow();

		$order_currency = $payment->to_currency; //BTC
		$order_total = $payment->amount; //BTC

		$amount1 = floatval($this->request->getPost('amount1')); //IN USD
		$amount2 = floatval($this->request->getPost('amount2')); //IN BTC
		$currency1 = $this->request->getPost('currency1'); //USD
		$currency2 = $this->request->getPost('currency2'); //BTC
		$status = intval($this->request->getPost('status'));

		if ($currency2 != $order_currency) {
			$this->edie("Currency Mismatch");
		}

		if ($amount2 < $order_total) {
			$this->edie("Amount is lesser than order total");
		}
		if ($status >= 100 || $status == 2) {
			
			// Payment is complete, set status to success in payments table	
			$builder->set('status', 'success');
			$builder->where(array("gateway_id" =>$txn_id));
			$builder->update();		

			//send mail with file link
			die("IPN OK");

		} else if ($status < 0) {
			// Payment Error
			
			$builder->set('status', 'error');
			$builder->where(array("gateway_id" =>$txn_id));
			$builder->update();
			////////////EMAIL SEND
			
			////////////EMAIL SEND
			die("IPN error");
		} else {
			// Payment Pending
			////////////EMAIL SEND
				//pending payment
			////////////EMAIL SEND
			
			
			$builder->set('status', 'pending');
			$builder->where(array("gateway_id" =>$txn_id));
			$builder->update();
			die("IPN pending");
		}
		
	}


