<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\Project;
use Illuminate\Console\Command;

class UpdateActivityStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-activity-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $projects = Project::all();
        foreach ($projects as $project) {
            $project->updateStatus();
        }
        $this->info('Activity status updated successfully!');

        $exams = Exam::all();
        foreach ($exams as $exam) {
            $exam->updateStatus();
        }
        $this->info('Activity status updated successfully!');
    }
}
