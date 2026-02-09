<?php

namespace App\Jobs;

/**
 * Legacy alias for SaturnTask.
 *
 * This class exists to handle old queued/failed jobs that were serialized
 * under the original "CoolifyTask" name before the rename to SaturnTask.
 * Without this alias, those jobs fail with "__PHP_Incomplete_Class" error.
 */
class CoolifyTask extends SaturnTask
{
    //
}
