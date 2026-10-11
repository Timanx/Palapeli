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


	/**
	 * Builds the SPD payload.
	 *
	 * The IBAN is assembled from a Czech account in the form "<number>/<bank code>" with the
	 * fixed check digits "28" and a zero account prefix. This is only valid for the account the
	 * organizers have been using; a different account requires computing the IBAN check digits.
	 */
	public function createPaymentString(string $account, int $amount, string $variableSymbol, string $message): string
	{
		[$accountNumber, $bankCode] = explode('/', $account, 2) + [1 => ''];

		return 'SPD*1.0*ACC:CZ28' . $bankCode . '000000' . $accountNumber
			. '*AM:' . $amount
			. '*CC:CZK*X-VS:' . $variableSymbol
			. '*MSG:' . $message . '*';
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
