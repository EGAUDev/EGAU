<?php
namespace App\Services;

use RuntimeException;

/** El archivo que se sube ya existe en otro recurso (mismo SHA-256). */
class DuplicadoException extends RuntimeException
{
    public function __construct(string $mensaje, public readonly int $idExistente)
    {
        parent::__construct($mensaje);
    }
}
