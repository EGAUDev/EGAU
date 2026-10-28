<?php
namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Un dato que la base v2.2 no acepta (cupo lleno, horario empalmado, código
 * repetido...). Es un error de validación: la pantalla recibe un 422 con
 * {errors: {campo: [mensaje]}} igual que con las reglas del formulario.
 */
class ErrorDeDatos extends ValidationException
{
    public function __construct(string $campo, string $mensaje)
    {
        $validator = Validator::make([], []);
        $validator->errors()->add($campo, $mensaje);
        parent::__construct($validator);
    }
}
