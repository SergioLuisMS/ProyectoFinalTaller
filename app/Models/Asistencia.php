<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    //
    public function empleado()
{
    return $this->belongsTo(Empleado::class);
}

}
