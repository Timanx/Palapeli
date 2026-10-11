<?php

declare(strict_types=1);

namespace App\Models;


/**
 * Files that can be attached to a cipher. The value is the column in table ciphers.
 */
enum CipherFileType: string
{
	case CipherImage = 'cipher_image_id';
	case SolutionImage = 'solution_image_id';
	case Pdf = 'pdf_file_id';


	/**
	 * Prefix of the stored file name, e.g. "img_s03.png".
	 */
	public function filePrefix(): string
	{
		return match ($this) {
			self::CipherImage => 'img_',
			self::SolutionImage => 'sol_',
			self::Pdf => 'pdf_',
		};
	}
}
