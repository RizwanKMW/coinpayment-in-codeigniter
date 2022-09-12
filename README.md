# coinpayment-in-codeigniter
1. Add library using composer by this command<br/>
`composer require coinpaymentsnet/coinpayments-php`
2. Paste CoinPaymentsAPI.php,CoinPaymentsAPI_init.php, CoinPaymentsAPICallback.php to the controller folder
3. Register here https://www.coinpayments.net/register and generate api keys
4. Put keys in CoinPaymentsAPI_init.php, edit permission and check all required permissions
5. dump this to db

`CREATE TABLE `payments` (
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
 `
