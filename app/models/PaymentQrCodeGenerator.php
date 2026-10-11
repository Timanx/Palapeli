<?php

declare(strict_types=1);

namespace App\Models;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;


/**
 * QR code for the entry fee payment in the Czech "QR Platba" (Short Payment Descriptor) format.
 */
final class PaymentQrCodeGenerator
{
	private const Size = 150;

	/** Czech account number: optional prefix (up to 6 digits), number (up to 10 digits), bank code (4 digits). */
	public const AccountPattern = '(?:\d{1,6}-)?\d{1,10}/\d{4}';

	/** Maximum length of the message for the recipient in the SPD format. */
	private const MessageMaxLength = 60;


	/**
	 * Builds the SPD payload, null when the account is not a valid Czech account number.
	 */
	public function createPaymentString(string $account, int $amount, string $variableSymbol, string $message): ?string
	{
		$iban = self::createIban($account);
		if ($iban === null) {
			return null;
		}

		// "*" separates the fields of the payload
		$message = mb_substr(str_replace('*', '', $message), 0, self::MessageMaxLength);

		return 'SPD*1.0*ACC:' . $iban
			. '*AM:' . $amount
			. '*CC:CZK*X-VS:' . $variableSymbol
			. '*MSG:' . $message . '*';
	}


	/**
	 * Converts a Czech account "[prefix-]number/bank code" to IBAN, null when the account is not valid.
	 * IBAN = CZ + check digits + bank code (4) + prefix (6) + number (10), zero padded.
	 */
	public static function createIban(string $account): ?string
	{
		if (!preg_match('~^(?:(\d{1,6})-)?(\d{1,10})/(\d{4})$~D', str_replace(' ', '', $account), $m)) {
			return null;
		}

		$bban = $m[3] . str_pad($m[1], 6, '0', STR_PAD_LEFT) . str_pad($m[2], 10, '0', STR_PAD_LEFT);

		// ISO 13616: move "CZ00" to the end, letters to numbers (C = 12, Z = 35), check digits = 98 - mod 97
		$remainder = 0;
		foreach (str_split($bban . '123500') as $digit) {
			$remainder = ($remainder * 10 + (int) $digit) % 97;
		}

		return 'CZ' . str_pad((string) (98 - $remainder), 2, '0', STR_PAD_LEFT) . $bban;
	}


	/**
	 * Returns the QR code as a PNG data URI usable in <img src>.
	 */
	public function createDataUri(string $paymentString): string
	{
		$builder = new Builder(
			writer: new PngWriter,
			data: $paymentString,
			size: self::Size,
		);

		return $builder->build()->getDataUri();
	}
}
