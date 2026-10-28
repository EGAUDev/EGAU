<?php
namespace App\Models\Core;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Cuenta de acceso de v2.2 (core.usuario). Los tokens de Sanctum se emiten
 * sobre ella; el nombre del token es el tipo con el que entró
 * (alumno, profesor o personal).
 */
class Usuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'core.usuario';
    public $timestamps = false;

    protected $hidden = ['password_hash', 'mfa_secreto'];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /** Tipo con el que entró en la petición actual (lo guarda el nombre del token). */
    public function tipoSesion(): ?string
    {
        return $this->currentAccessToken()?->name;
    }
}
