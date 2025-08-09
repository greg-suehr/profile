<?php

namespace App\Katzen\Service\Delete;

enum DeleteMode: string
{
    case BLOCK_IF_REFERENCED = 'block';
    case SOFT_DELETE = 'soft';
    case FORCE_WITH_INVALIDATIONS = 'force'; # TODO: e.g. mark recipes and menus "needs review"
    case REPLACE_WITH = 'replace'; # TODO: e.g. map to a substituting Item
}

?>
