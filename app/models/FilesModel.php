<?php

declare(strict_types=1);

namespace App\Models;


/**
 * Uploaded files (table files); a file is identified by its directory and name.
 */
final class FilesModel extends BaseModel
{
	public function getFileId(string $path, string $fileName): ?int
	{
		$id = $this->database->query('SELECT id FROM files WHERE path = ? AND name = ?', $path, $fileName)->fetchField();
		return $id === null ? null : (int) $id;
	}


	/**
	 * Registers the file and returns its ID (an existing record is reused).
	 */
	public function insertFile(string $path, string $fileName): int
	{
		return $this->database->transaction(function () use ($path, $fileName): int {
			$id = $this->getFileId($path, $fileName);
			if ($id === null) {
				$this->database->query('INSERT INTO files ?', ['path' => $path, 'name' => $fileName]);
				$id = (int) $this->database->getInsertId();
			}

			return $id;
		});
	}
}
