<?php

namespace App\Enums;

enum BuildPackTypes: string
{
    case RAILPACK = 'railpack';
    case NIXPACKS = 'nixpacks';
    case STATIC = 'static';
    case DOCKERFILE = 'dockerfile';
    case DOCKERCOMPOSE = 'dockercompose';
}
