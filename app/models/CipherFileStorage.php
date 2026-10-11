<?php

declare(strict_types=1);

namespace App\Models;

use Nette\Http\FileUpload;
use Nette\Utils\FileSystem;


/**
 * Stores files uploaded for ciphers into www/cipher_images/<year>/<checkpoint>/
 * and registers them in the table files.
 */
final class CipherFileStorage
{
	/** Directory relative to www/; the same relative path is stored in the database. */
	private const BaseDirectory = 'cipher_images';


	public function __construct(
		private readonly string $wwwDir,
		private readonly FilesModel $filesModel,
	) {
	}


	/**
	 * Saves the upload (if any) and returns the ID of the file record, or null when nothing was uploaded.
	 */
	public function store(FileUpload $upload, int $year, int $checkpoint, CipherFileType $type): ?int
	{
		if (!$upload->isOk()) {
			return null;
		}

		$relativeDir = sprintf('%s/%d/%d/', self::BaseDirectory, $year, $checkpoint);
		FileSystem::createDir($this->wwwDir . '/' . $relativeDir);

		// e.g. img_s03.png; the extension is taken from the sanitized client name
		$extension = pathinfo($upload->getSanitizedName(), PATHINFO_EXTENSION);
		$fileName = sprintf('%ss%02d', $type->filePrefix(), $checkpoint) . ($extension === '' ? '' : '.' . $extension);

		$upload->move($this->wwwDir . '/' . $relativeDir . $fileName);
		return $this->filesModel->insertFile($relativeDir, $fileName);
	}
}
