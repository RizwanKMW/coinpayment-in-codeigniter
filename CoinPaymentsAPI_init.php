<?php

require_once "CoinPaymentsAPI.php";
require_once FCPATH.'vendor/autoload.php';
//require "Payment.php"; //db

$coin = new CoinPaymentsAPI();
$coin->Setup("11d98f5eb1003990e7f2cc5c95328cabb900e3edd39f4bb904fe3240d597491e","f00CBb0dBB8Db5Ae8c9cd91796c9b71b363E599292d118aD374649A2D8158ac7");
