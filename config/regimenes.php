<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Regímenes tributarios del emisor
|--------------------------------------------------------------------------
|
| Los cuatro que reconoce SUNAT, y nada más. Antes la lista era
| general / mype_restaurantes / nrus, que mezclaba dos cosas distintas:
| el régimen del contribuyente con una tasa de IGV especial.
|
| La tasa especial de restaurantes y hoteles (Ley 31556) no es un régimen:
| es un emisor MYPE cuya tasa de IGV difiere. Eso ya se configura por
| separado en `igv_rate_override`, así que tenerlo también acá obligaba a
| elegir entre declarar el régimen real o la tasa correcta.
|
*/

return [

    'lista' => [
        'rus' => 'Nuevo RUS',
        'rer' => 'Régimen Especial (RER)',
        'mype' => 'Régimen MYPE Tributario',
        'general' => 'Régimen General',
    ],

    'default' => 'general',

    /*
    | Régimen con restricciones de emisión: solo boletas, sin IGV, y las notas
    | únicamente contra boletas. Se declara acá para que las reglas no vayan
    | comparando contra un literal repartido por medio proyecto.
    */
    'restringido' => 'rus',

];
