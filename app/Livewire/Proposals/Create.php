<?php

namespace App\Livewire\Proposals;

use App\Actions\ArrangePositions;
use App\Models\Project;
use App\Models\Proposal;
use App\Notifications\NewProposal;
use App\Notifications\PositionLoss;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Rule;
use Livewire\Component;

class Create extends Component
{
    public Project $project;

    public bool $modal = false;

    #[Rule(['required', 'email'])]
    public string $email = '';
    #[Rule(['required', 'numeric', 'gt:0'])]
    public int $hours = 0;

    public bool $agree = false;

    public function save()
    {
        $this->validate();

        if (!$this->agree) {
            $this->addError('agree', 'Você precisa concordar com os termos de uso');
            return;
        }

        DB::transaction(function () {
            $proposal = $this->project->proposals()->updateOrCreate(
                ['email' => $this->email],
                ['hours' => $this->hours]
            );

            $this->arangePositions($proposal);
        });

        $this->project->author->notify(new NewProposal($this->project));

        $this->dispatch('proposal::created');
        $this->modal = false;
    }

    public function arangePositions(Proposal $proposal)
    {
        $query = DB::select('
            SELECT *, ROW_NUMBER() OVER(ORDER BY hours ASC) AS newPosition
            FROM proposals
            WHERE project_id = :project
        ', ['project' => $proposal->project_id]);

        $position = collect($query)->where('id', '=', $proposal->id)->first();
        $otherProposal = collect($query)->where('position', '=', $position->newPosition)->first();

        if ($otherProposal) {
            $proposal->update(['position_status' => 'up']);
            $oProposal = Proposal::find($otherProposal->id);
            $oProposal->update(['position_status' => 'down']);
            $oProposal->notify(new PositionLoss($this->project));
        }

        ArrangePositions::run($proposal->project_id);
    }

    public function render()
    {
        return view('livewire.proposals.create');
    }
}
