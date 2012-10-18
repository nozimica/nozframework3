<?php
/** Perfiles: n (2^(n-1))
             4 (8).- administrador
             3 (4).- editor
             2 (2).- usuario
             1 (1).- visita
             0 (0).- -- especial para submenus --
 */

$actionDefinition = array(
    // defines properties of default actions
    'ingresar'        => array( 1, 'Ingresar al sistema', 'html-in')
    , 'salir'         => array( 1, 'Salir del sistema', 'html-out')
    , 'inicio'        => array( 8, 'Inicio', 'html-start')
);

