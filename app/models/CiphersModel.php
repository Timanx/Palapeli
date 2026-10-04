<?php

declare(strict_types=1);

namespace App\Models;

use Nette\Database\Row;
use Nette\Utils\Strings;


/**
 * Ciphers placed on checkpoints (table ciphers, one row per year and checkpoint).
 *
 * Checkpoint 0 is the start, checkpoint "checkpoint_count - 1" is the finish.
 */
final class CiphersModel extends BaseModel
{
	/**
	 * Cipher with paths of its attached files (cipher_image, solution_image, pdf_file).
	 */
	public function getCipher(int $year, int $checkpoint): ?Row
	{
		return $this->database->query('
			SELECT
				ciphers.*,
				CONCAT(cipher_image.path, cipher_image.name) AS cipher_image,
				CONCAT(solution_image.path, solution_image.name) AS solution_image,
				CONCAT(pdf_file.path, pdf_file.name) AS pdf_file
			FROM ciphers
			LEFT JOIN files AS cipher_image ON cipher_image.id = ciphers.cipher_image_id
			LEFT JOIN files AS solution_image ON solution_image.id = ciphers.solution_image_id
			LEFT JOIN files AS pdf_file ON pdf_file.id = ciphers.pdf_file_id
			WHERE year = ? AND checkpoint_number = ?
		', $year, $checkpoint)->fetch();
	}


	/**
	 * Inserts or updates the textual data of a cipher.
	 * @param array{name: ?string, cipher_description: ?string, solution_description: ?string, solution: ?string, dead_solution: ?string, code: ?string, specification: ?string, checkpoint_close_time: ?string, has_password_solution: bool} $data
	 */
	public function upsertCipher(int $year, int $checkpoint, array $data): void
	{
		$data['has_password_solution'] = (int) $data['has_password_solution'];

		$this->database->query(
			'INSERT INTO ciphers ? ON DUPLICATE KEY UPDATE ?',
			['year' => $year, 'checkpoint_number' => $checkpoint] + $data,
			$data,
		);
	}


	public function attachFile(int $year, int $checkpoint, CipherFileType $type, int $fileId): void
	{
		$this->database->query(
			'UPDATE ciphers SET ?name = ? WHERE year = ? AND checkpoint_number = ?',
			$type->value,
			$fileId,
			$year,
			$checkpoint,
		);
	}


	/**
	 * Text revealed to a team that asked for the total hint ("totálka"); falls back to the solution.
	 */
	public function getDeadSolution(int $year, int $checkpoint): ?string
	{
		return $this->database->query("
			SELECT COALESCE(NULLIF(dead_solution, ''), NULLIF(solution, ''))
			FROM ciphers
			WHERE checkpoint_number = ? AND year = ?
		", $checkpoint, $year)->fetchField();
	}


	/**
	 * Checks the code a team found on a checkpoint (case insensitive).
	 * A checkpoint without a configured code never accepts anything.
	 */
	public function checkCode(int $year, int $checkpoint, string $code): bool
	{
		$requiredCode = $this->getColumn($year, $checkpoint, 'code');

		return $requiredCode !== null
			&& $requiredCode !== ''
			&& mb_strtoupper($code) === mb_strtoupper($requiredCode);
	}


	/**
	 * Checks a password-type cipher solution. The answer is webalized (no diacritics,
	 * spaces turned into dashes) before comparing, so the stored solution must be in that form.
	 */
	public function checkSolution(int $year, int $checkpoint, string $solution): bool
	{
		$requiredSolution = $this->getColumn($year, $checkpoint, 'solution');

		return $requiredSolution !== null
			&& $requiredSolution !== ''
			&& mb_strtoupper(Strings::webalize($solution)) === mb_strtoupper($requiredSolution);
	}


	/**
	 * @return array<int, Row>  checkpoint number => row with checkpoint_close_time formatted as H:i
	 */
	public function getCheckpointCloseTimes(int $year): array
	{
		return $this->database->query("
			SELECT checkpoint_number, TIME_FORMAT(checkpoint_close_time, '%H:%i') AS checkpoint_close_time
			FROM ciphers
			WHERE year = ?
		", $year)->fetchAssoc('checkpoint_number');
	}


	/**
	 * @return array<int, ?string>  checkpoint number => specification ("upřesnítko")
	 */
	public function getSpecifications(int $year): array
	{
		return $this->database->query('
			SELECT checkpoint_number, specification
			FROM ciphers
			WHERE year = ?
		', $year)->fetchPairs('checkpoint_number', 'specification');
	}


	public function hasCheckpointPasswordSolution(int $year, int $checkpoint): bool
	{
		return (bool) $this->getColumn($year, $checkpoint, 'has_password_solution');
	}


	private function getColumn(int $year, int $checkpoint, string $column): mixed
	{
		return $this->database->query(
			'SELECT ?name FROM ciphers WHERE year = ? AND checkpoint_number = ?',
			$column,
			$year,
			$checkpoint,
		)->fetchField();
	}
}
