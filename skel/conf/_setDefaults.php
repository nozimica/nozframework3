<?php

# Project:
# The name of this project.
$__opt['projName']                             = '';

# DataBase:
# Dsn for the connection, null otherwise.
$__dbOpt = null; // 'phptype://username:password@hostspec/database';

# Authentication:
# If the authentication is disabled completely
$__authMainOpt['authDisabled']                 = true;
//Level of the auth logs. Possible values: [none|info|debug]
$__authMainOpt['authLogLevel']                 = 'none';
// If the authentication must be triggered on first visit to the system
$__authMainOpt['authOptional']                 = false;


# Auth constructor options:
#$__authOpt['postUsername']                     = 'username';
#$__authOpt['postPassword']                     = 'password';
#$__authOpt['table']                            = 'usuario';
#$__authOpt['usernamecol']                      = 'usu_username';
#$__authOpt['passwordcol']                      = 'usu_password';

## Name of the field that states the profile of the user 
## (if array, [0] field name, [1] alias):
#$__authOpt['profilecol']                       = array('usu_rol_id', 'usu_perfil');

## DB_FIELDS: extra fields to be retrieved when validating user 
## (if array, [0] field name, [1] alias):
#$__authOpt['db_fields'][]                      = 'usu_email';

