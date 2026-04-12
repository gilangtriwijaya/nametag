<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;
use App\Models\Employee;

$jobs = DB::table("jobs")->get();
$job_map = [];
$all_employee_ids = Employee::pluck("id")->map(function($v){return (int)$v;})->toArray();
$employeeSet = array_flip($all_employee_ids);

foreach ($jobs as $job) {
    $p = $job->payload;
    $found = [];
    // try to find explicit ids arrays like "ids": [1,2,3]
    if (preg_match('/"ids"\s*:\s*\[([^\]]+)\]/', $p, $m)) {
        $nums = preg_split('/,\s*/', $m[1]);
        foreach ($nums as $n) {
            $n = preg_replace('/[^0-9]/','', $n);
            if ($n !== '') $found[] = (int)$n;
        }
    }
    // try fields like "id":123 or "employee_id":123
    if (preg_match_all('/"(?:employee_id|emp_id|id|employee)"\s*:\s*(\d{1,7})/', $p, $mm)) {
        foreach ($mm[1] as $nn) $found[] = (int)$nn;
    }
    // fallback: extract any integers and intersect with employees list
    if (empty($found)) {
        if (preg_match_all('/\b(\d{1,7})\b/', $p, $mm2)) {
            foreach ($mm2[1] as $nn) {
                $val = (int)$nn;
                if (isset($employeeSet[$val])) $found[] = $val;
            }
        }
    }
    $found = array_values(array_unique($found));
    $job_map[] = ['job_id'=>$job->id, 'queue'=>$job->queue, 'payload_snippet'=>substr($p,0,400), 'employee_ids'=>$found, 'available_at'=>$job->available_at, 'created_at'=>property_exists($job,'created_at') ? $job->created_at : null];
}

$queued_jobs_by_emp = [];
foreach ($job_map as $j) {
    foreach ($j['employee_ids'] as $eid) {
        $queued_jobs_by_emp[$eid][] = $j['job_id'];
    }
}

$employees = Employee::select('id','nametag_status')->get()->map(function($e){return ['id'=> (int)$e->id, 'status'=>$e->nametag_status];})->toArray();

$queued_or_processing = array_filter($employees, function($e){return in_array($e['status'], ['queued','processing']);});
$queued_or_processing_ids = array_map(function($e){return $e['id'];}, $queued_or_processing);

$with_files = [];
foreach ($all_employee_ids as $id) {
    $front = file_exists(__DIR__ . "/../public/nametag/front/{$id}.png");
    $back  = file_exists(__DIR__ . "/../public/nametag/back/{$id}.png");
    if ($front || $back) $with_files[$id] = ['front'=>$front,'back'=>$back];
}

// mismatches
$jobs_but_not_db = [];
foreach ($queued_jobs_by_emp as $eid => $jobsArr) {
    if (!in_array($eid, $queued_or_processing_ids)) {
        $dbst = null;
        foreach ($employees as $ee) if ($ee['id']==$eid) { $dbst=$ee['status']; break; }
        $jobs_but_not_db[] = ['id'=>$eid,'jobs'=>$jobsArr,'db_status'=>$dbst];
    }
}

$db_but_no_jobs = [];
foreach ($queued_or_processing_ids as $eid) {
    if (!isset($queued_jobs_by_emp[$eid])) $db_but_no_jobs[] = $eid;
}

$files_but_db_queued = [];
foreach ($with_files as $eid=>$v) {
    $st = null;
    foreach ($employees as $ee) if ($ee['id']==$eid) { $st=$ee['status']; break; }
    if (in_array($st, ['queued','processing'])) $files_but_db_queued[] = ['id'=>$eid,'status'=>$st,'front'=>$v['front'],'back'=>$v['back']];
}

$output = ['summary'=>['total_jobs'=>count($jobs),'unique_employee_ids_in_jobs'=>count(array_keys($queued_jobs_by_emp)),'total_employees'=>count($all_employee_ids),'employees_with_files'=>count($with_files),'queued_or_processing_db'=>count($queued_or_processing_ids)],
'jobs'=>$job_map,'queued_jobs_by_emp'=>$queued_jobs_by_emp,'queued_but_db_mismatch'=>$jobs_but_not_db,'db_queued_but_no_job'=>$db_but_no_jobs,'files_but_db_queued'=>$files_but_db_queued];

file_put_contents(__DIR__ . "/../storage/logs/inspect_queue_vs_db.json", json_encode($output, JSON_PRETTY_PRINT));
echo "Wrote report to storage/logs/inspect_queue_vs_db.json\n";
