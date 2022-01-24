<?php

namespace App\Models;

use App\Presenters\BasePresenter;
use Nette;

class CiphersModel
{

    /** @var Nette\Database\Context */
    private $database;

    private $checkpoint = 1;
    private $year;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    public function setYear($year)
    {
        $this->year = $year;
    }

    public function setCheckpoint($checkpoint)
    {
        $this->checkpoint = $checkpoint;
    }

    public function getCipher($checkpoint)
    {
        return $this->database->query('
            SELECT ciphers.*, CONCAT(cipher_image.path, cipher_image.name) AS cipher_image, CONCAT(solution_image.path, solution_image.name) AS solution_image, CONCAT(pdf_file.path, pdf_file.name) AS pdf_file
            FROM ciphers
            LEFT JOIN files AS cipher_image ON cipher_image.id = ciphers.cipher_image_id
            LEFT JOIN files AS solution_image ON solution_image.id = ciphers.solution_image_id
            LEFT JOIN files AS pdf_file ON pdf_file.id = ciphers.pdf_file_id
            WHERE year = ? AND checkpoint_number = ?
        ', $this->year, $checkpoint)->fetch();
    }

    public function upsertCipher($checkpoint, $name, $cipherDescription, $solutionDescription, $solution, $code, $specification)
    {
        $this->database->query('
            INSERT INTO ciphers (year, checkpoint_number, name, cipher_description,  solution_description, solution, code, specification) VALUES
            (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = ?, cipher_description = ?, solution_description = ?, solution = ?, code = ?, specification = ? 
        ', $this->year, $checkpoint, $name, $cipherDescription, $solutionDescription, $solution, $code, $specification, $name, $cipherDescription, $solutionDescription, $solution, $code, $specification
        );
    }

    public function updateSolutionImage($solutionImageId)
    {
        $this->database->query('
                UPDATE ciphers
                SET solution_image_id = ?
                WHERE year = ? AND checkpoint_number = ?
            ', $solutionImageId, $this->year, $this->checkpoint);
    }

    public function updateCipherImage($cipherImageId)
    {
        $this->database->query('
                UPDATE ciphers
                SET cipher_image_id = ?
                WHERE year = ? AND checkpoint_number = ?
            ', $cipherImageId, $this->year, $this->checkpoint);
    }

    public function updatePDF($pdfId)
    {
        $this->database->query('
                UPDATE ciphers
                SET pdf_file_id = ?
                WHERE year = ? AND checkpoint_number = ?
            ', $pdfId, $this->year, $this->checkpoint);
    }

    public function getDeadSolution($checkpointNumber = null)
    {
        return $this->database->query('
            SELECT COALESCE(dead_solution, solution) AS dead_solution
            FROM ciphers
            WHERE checkpoint_number = ? AND year = ?
        ',
            $checkpointNumber ?? $this->checkpoint, $this->year
        )->fetchField('dead_solution');
    }

    public function checkCode($code, $checkpointNumber)
    {
        $requiredCode = $this->database->query('
            SELECT code
            FROM ciphers
            WHERE year = ? AND checkpoint_number = ?
        ', $this->year, $checkpointNumber)->fetchField('code');

        return mb_strtoupper($code) === mb_strtoupper($requiredCode);
    }

    public function getCheckpointCloseTimes()
    {
        return $this->database->query('
            SELECT checkpoint_number, TIME_FORMAT(checkpoint_close_time, \'%H:%i\') as checkpoint_close_time
            FROM ciphers
            WHERE year = ?
        ',
            $this->year)->fetchAssoc('checkpoint_number');
    }

    public function getSpecifications()
    {
        return $this->database->query('
            SELECT checkpoint_number, specification
            FROM ciphers
            WHERE year = ?
        ',
            $this->year)->fetchPairs('checkpoint_number', 'specification');
    }
}
