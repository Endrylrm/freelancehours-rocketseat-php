<?php

namespace App\Actions;

use Illuminate\Support\Facades\DB;

class ArrangePositions
{
    public static function run(int $id)
    {
        DB::update('
            WITH RankedProposals AS (
                SELECT id, ROW_NUMBER() OVER(ORDER BY hours ASC) AS p
                FROM proposals
                WHERE project_id = :project
            )

            UPDATE proposals
            SET position = (SELECT p FROM RankedProposals WHERE proposals.id = RankedProposals.id)
            WHERE project_id = :project2
        ', ['project' => $id, 'project2' => $id]);
    }
}
