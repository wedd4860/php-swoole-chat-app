<?php

namespace framework\Socket\Helpers;

class Cipher
{
	static public function Encrypt($string, $key = 's5v8y/B?E(H+MbQeThWmYq3t6w9z$C&F')
	{
		$key = substr($key, 0, 32);
		$aIVBytes = [];
		$aKeyBytes = [];
		$aPlanBytes = [];
		$len_key = strlen($key);
		for ($i = 0; $i < $len_key; $i++) {
			if ($i < 16) $aIVBytes[] = ord($key[$i]);
			else $aKeyBytes[] = ord($key[$i]);
		}

		$string = base64_encode($string);
		$len_string = strlen($string);
		for ($i = 0; $i < $len_string; $i++) {
			$aPlanBytes[] = ord($string[$i]);
		}
		$bszChiperText = KISA_SEED_CBC::SEED_CBC_Encrypt($aKeyBytes, $aIVBytes, $aPlanBytes, 0, count($aPlanBytes));

		$sEncText = "";
		$cnt_text = count((array)$bszChiperText);
		for ($i = 0; $i < $cnt_text; $i++) {
			$sEncText .= chr($bszChiperText[$i]);
		}
		$sEncText = base64_encode($sEncText);
		return $sEncText;
	}

	static public function Decrypt($string, $key = 's5v8y/B?E(H+MbQeThWmYq3t6w9z$C&F')
	{
		$key = substr($key, 0, 32);
		$aIVBytes = [];
		$aKeyBytes = [];
		$aPlanBytes = [];
		$len_key = strlen($key);
		for ($i = 0; $i < $len_key; $i++) {
			if ($i < 16) $aIVBytes[] = ord($key[$i]);
			else $aKeyBytes[] = ord($key[$i]);
		}
		$string = base64_decode($string);
		$len_string = strlen($string);
		for ($i = 0; $i < $len_string; $i++) {
			$aPlanBytes[] = ord($string[$i]);
		}
		$bszPlainText = KISA_SEED_CBC::SEED_CBC_Decrypt($aKeyBytes, $aIVBytes, $aPlanBytes, 0, count($aPlanBytes));

		$sDecText = "";
		$cnt = count((array)$bszPlainText);
		for ($i = 0; $i < $cnt; $i++) {
			$sDecText .= chr($bszPlainText[$i]);
		}
		$sDecText = base64_decode($sDecText);

		return $sDecText;
	}

	static public function getMysqlPassword($password)
	{
		return '*' . strtoupper(sha1(sha1($password, true)));
	}

	static public function checkPassword($pwEnc, $pw)
	{
		if (substr($pwEnc, 0, 1) == '*') {
			return (strcmp($pwEnc, \framework\Socket\Helpers\Cipher::getMysqlPassword($pw)) === 0);
		} else if (substr($pwEnc, 1, 8) == 'argon2id') {
			return password_verify($pw, $pwEnc);
		} else if (substr($pwEnc, 0, 6) == 'SHA256') {
			$count = explode('$', $pwEnc)[1];
			if (!$count || strlen($count) > 2 || (int)$count == 0) return false;
			return (strcmp($pwEnc, \framework\Socket\Helpers\Cipher::getSHA256Password($pw, $count)) === 0);
		} else {
			return false;
		}
	}

	static public function getSHA256Password($password, $count = 10)
	{
		for ($i = 0; $i < $count; $i++) {
			$password = hash('sha256', $password);
		}
		return 'SHA256$' . $count . '$' . $password;
	}

	static public function getARGON2IDPassword($password, $cost = 5)
	{
		return password_hash($password, PASSWORD_ARGON2ID, ['time_cost' => $cost, 'threads' => 2]);
	}

	static public function EncryptAES($string, $key)
	{
		return base64_encode(openssl_encrypt($string, "aes-256-cbc", $key, true, str_repeat(chr(0), 16)));
	}

	static public function DecryptAES($string, $key)
	{
		return openssl_decrypt(base64_decode($string), "aes-256-cbc", $key, true, str_repeat(chr(0), 16));
	}
}
